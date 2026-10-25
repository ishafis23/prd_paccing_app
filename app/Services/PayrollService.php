<?php

namespace App\Services;

use App\Enums\AdvanceStatus;
use App\Enums\DailyAttendanceStatus;
use App\Enums\EarlyLeaveStatus;
use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveTipe;
use App\Enums\PayrollItemJenis;
use App\Enums\PayrollStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\DailyAttendance;
use App\Models\PayrollRun;
use App\Models\TechnicianAdvance;
use App\Models\TechnicianAdvanceRepayment;
use App\Models\TechnicianEarlyLeave;
use App\Models\TechnicianIncentive;
use App\Models\TechnicianOffDay;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penggajian teknisi (dev-plan/hrd/02). Fokus hitungan & status; slip dirender
 * dari PayrollRun + items.
 *
 * Gaji bersih = Gaji Pokok + Bonus Bulanan + Bonus Umum + Bonus Mingguan
 *               − Bonus Mingguan (sudah dibayar pekanan) − Denda Telat
 *               − Potongan OFF (kelebihan kuota) − BPJS TK − Kasbon.
 */
class PayrollService
{
    use RestrictsByRole;

    /** Jatah OFF per periode (seragam). */
    public const KUOTA_OFF = 5;

    private const PENGELOLA_ROLES = [RoleName::Owner, RoleName::Hr, RoleName::Admin];

    public static function boleh(?User $user): bool
    {
        return $user?->hasAnyRole([RoleName::Owner->value, RoleName::Hr->value, RoleName::Admin->value]) ?? false;
    }

    /**
     * Rentang periode gaji bulanan dari sebuah tanggal mulai (mengikuti
     * tanggal masuk): [mulai, mulai + 1 bulan − 1 hari].
     * Contoh: mulai 15 Okt → 15 Okt s/d 14 Nov.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rentangPeriode(CarbonInterface $mulai): array
    {
        $awal = CarbonImmutable::parse($mulai)->startOfDay();

        return [$awal, $awal->addMonthNoOverflow()->subDay()];
    }

    /**
     * Rentang periode gaji sebuah profil yang memuat $acuan. Tanpa cut-off
     * → perilaku lama (rentangPeriode). Dengan cut-off (mis. 23) → tanggal 24
     * bulan lalu s.d. 23 bulan berjalan; bulan pendek memakai akhir bulan
     * (cut-off 31 di Februari = 28/29 Feb, periode berikutnya mulai 1 Mar).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rentangPeriodeProfil(TechnicianPayrollProfile $profil, CarbonInterface $acuan): array
    {
        $cutoff = $profil->cutoff_hari;

        if ($cutoff === null) {
            return $this->rentangPeriode($acuan);
        }

        $cutoff = (int) $cutoff;
        $tgl = CarbonImmutable::parse($acuan)->startOfDay();
        $akhirDiBulan = fn (CarbonImmutable $bulan): CarbonImmutable => CarbonImmutable::create(
            $bulan->year, $bulan->month, min($cutoff, $bulan->daysInMonth)
        )->startOfDay();

        $bulan = $tgl->startOfMonth();
        $akhir = $akhirDiBulan($bulan);

        if ($tgl->gt($akhir)) {
            $bulan = $bulan->addMonthNoOverflow();
            $akhir = $akhirDiBulan($bulan);
        }

        // Awal = sehari setelah akhir periode sebelumnya.
        $awal = $akhirDiBulan($bulan->subMonthNoOverflow())->addDay();

        return [$awal, $akhir->endOfDay()];
    }

    /**
     * Periode gaji asal sebuah kasbon (periode yang memuat tanggal kasbon).
     * Null bila teknisi belum punya profil gaji atau profilnya tanpa cut-off —
     * tanpa cut-off periode tidak punya batas tetap, jadi kasbon mengikuti
     * perilaku lama (dipotong di gaji berikutnya).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function periodeKasbon(TechnicianAdvance $kasbon): ?array
    {
        $profil = TechnicianPayrollProfile::where('user_id', $kasbon->user_id)->first();

        if ($profil === null || $profil->cutoff_hari === null) {
            return null;
        }

        return $this->rentangPeriodeProfil($profil, $kasbon->tanggal);
    }

    /**
     * Kasbon aktif yang periode asalnya sudah lewat dan belum pernah dipotong
     * (perlu tindakan Admin).
     *
     * @return Collection<int, TechnicianAdvance>
     */
    public function kasbonBelumTerpotongLewatPeriode(?int $userId = null): Collection
    {
        $profil = TechnicianPayrollProfile::query()
            ->whereNotNull('cutoff_hari')
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->get()
            ->keyBy('user_id');

        if ($profil->isEmpty()) {
            return collect();
        }

        return TechnicianAdvance::query()
            ->whereIn('user_id', $profil->keys())
            ->where('status', AdvanceStatus::Aktif->value)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get()
            ->filter(function (TechnicianAdvance $k) use ($profil): bool {
                $p = $profil[$k->user_id];
                $berjalanMulai = $this->rentangPeriodeProfil($p, today())[0];

                return $k->terbayar() <= 0
                    && $this->rentangPeriodeProfil($p, $k->tanggal)[1]->lt($berjalanMulai);
            })
            ->values();
    }

    /**
     * Hitung (ulang) gaji satu teknisi untuk satu periode → draft PayrollRun.
     */
    public function hitung(TechnicianPayrollProfile $profil, CarbonInterface $mulaiAwal, User $actor): PayrollRun
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        return $this->proses($profil, $mulaiAwal, $actor->id);
    }

    /**
     * Segarkan draft gaji teknisi yang periodenya memuat $tanggal (dipanggil
     * otomatis saat OFF / pulang cepat / absensi / insentif berubah). Gaji
     * yang sudah di-ACC/dibayar tidak disentuh.
     */
    public function segarkanDraft(int $userId, CarbonInterface|string|null $tanggal): void
    {
        if ($tanggal === null) {
            return;
        }

        $tgl = CarbonImmutable::parse($tanggal)->toDateString();

        $run = PayrollRun::query()
            ->where('user_id', $userId)
            ->where('status', PayrollStatus::Draft->value)
            ->whereDate('periode_mulai', '<=', $tgl)
            ->whereDate('periode_selesai', '>=', $tgl)
            ->first();

        $profil = $run === null ? null : TechnicianPayrollProfile::where('user_id', $userId)->first();

        if ($run !== null && $profil !== null) {
            $this->proses($profil, $run->periode_mulai, $run->dihitung_oleh);
        }
    }

    /**
     * Segarkan semua draft gaji teknisi yang periodenya berakhir pada/setelah
     * $tanggal — dipakai saat kasbon berubah (kasbon lama tetap ikut dipotong
     * di periode berikutnya, jadi tidak cukup mencari periode yang memuat
     * tanggalnya saja).
     */
    public function segarkanDraftSejak(int $userId, CarbonInterface|string|null $tanggal): void
    {
        if ($tanggal === null) {
            return;
        }

        $profil = TechnicianPayrollProfile::where('user_id', $userId)->first();

        if ($profil === null) {
            return;
        }

        PayrollRun::query()
            ->where('user_id', $userId)
            ->where('status', PayrollStatus::Draft->value)
            ->whereDate('periode_selesai', '>=', CarbonImmutable::parse($tanggal)->toDateString())
            ->get()
            ->each(fn (PayrollRun $run) => $this->proses($profil, $run->periode_mulai, $run->dihitung_oleh));
    }

    /**
     * Alokasi potongan kasbon aktif teknisi untuk gaji periode
     * [$periodeMulai, $periodeSelesai]: tiap kasbon dipotong min(sisa,
     * cicilan/periode), tanpa cicilan = lunas sekaligus. Total dibatasi $batas
     * supaya gaji tidak jadi negatif; sisanya terbawa ke periode berikutnya.
     *
     * Kasbon mengikuti periode gaji asalnya (yang memuat tanggal kasbon, hanya
     * bila teknisi punya cut-off): periode mendatang dilewati, dan kasbon dari
     * periode lampau yang belum pernah dipotong sama sekali juga dilewati
     * (periodenya sudah tutup — Admin yang memutuskan lewat pelunasan manual).
     * Kasbon yang sudah pernah dicicil tetap lanjut dipotong.
     *
     * @return array<int, array{advance: TechnicianAdvance, nominal: float}>
     */
    private function alokasiKasbon(int $userId, CarbonInterface $periodeMulai, CarbonInterface $periodeSelesai, float $batas): array
    {
        $alokasi = [];
        $tersisa = max(0.0, $batas);
        $mulai = $periodeMulai->toDateString();

        // Profil dimuat sekali di luar loop; tanpa profil/cut-off = perilaku lama.
        $profil = TechnicianPayrollProfile::where('user_id', $userId)->first();
        $berCutoff = $profil !== null && $profil->cutoff_hari !== null;

        $kasbon = TechnicianAdvance::query()
            ->where('user_id', $userId)
            ->where('status', AdvanceStatus::Aktif->value)
            ->whereDate('tanggal', '<=', $periodeSelesai->toDateString())
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        foreach ($kasbon as $k) {
            if ($berCutoff) {
                $asalMulai = $this->rentangPeriodeProfil($profil, $k->tanggal)[0]->toDateString();

                if ($asalMulai > $mulai) {
                    continue;
                }

                if ($asalMulai < $mulai && $k->terbayar() <= 0) {
                    continue;
                }
            }

            $cicilan = (float) $k->cicilan_per_periode;
            $sisa = $k->sisa();
            $potong = min($sisa, $cicilan > 0 ? $cicilan : $sisa, $tersisa);

            if ($potong > 0) {
                $alokasi[] = ['advance' => $k, 'nominal' => round($potong, 2)];
                $tersisa -= $potong;
            }
        }

        return $alokasi;
    }

    private function proses(TechnicianPayrollProfile $profil, CarbonInterface $mulaiAwal, ?int $actorId): PayrollRun
    {
        [$mulai, $selesai] = $this->rentangPeriodeProfil($profil, $mulaiAwal);

        $existing = PayrollRun::query()
            ->where('user_id', $profil->user_id)
            ->whereDate('periode_mulai', $mulai->toDateString())
            ->whereDate('periode_selesai', $selesai->toDateString())
            ->first();

        if ($existing !== null && ! $existing->draft()) {
            throw new BusinessRuleException('Gaji periode ini sudah disetujui/dibayar — tidak bisa dihitung ulang.');
        }

        $dari = $mulai->toDateString();
        $sampai = $selesai->toDateString();

        // Bonus dari ledger insentif: semua kecuali yang ditolak. Entri berfoto
        // (Games 1/2/3/4) berstatus `menunggu` sampai diverifikasi, tapi tetap
        // harus masuk gaji — hanya yang ditolak Admin/HR yang dikeluarkan.
        $ledger = TechnicianIncentive::query()
            ->where('user_id', $profil->user_id)
            ->where('tipe', IncentiveTipe::Bonus->value)
            ->where('status_verifikasi', '!=', IncentiveStatusVerifikasi::Ditolak->value)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->get();

        $bulananPerKategori = [];
        $bonusMingguan = 0.0;

        foreach ($ledger as $entri) {
            /** @var TechnicianIncentive $entri */
            $nominal = (float) $entri->nominal;

            if ($entri->kategori->mingguan()) {
                $bonusMingguan += $nominal;

                continue;
            }

            $bulananPerKategori[$entri->kategori->value] = ($bulananPerKategori[$entri->kategori->value] ?? 0.0) + $nominal;
        }

        $bonusBulanan = array_sum($bulananPerKategori);

        // Denda telat dari absensi: hari berstatus telat (tidak dikecualikan).
        $jumlahTelat = DailyAttendance::query()
            ->where('user_id', $profil->user_id)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->where('status_datang', DailyAttendanceStatus::Telat->value)
            ->where('dikecualikan_denda', false)
            ->count();

        $dendaTelat = $jumlahTelat * (float) $profil->denda_telat_per_hari;

        // OFF: kelebihan dari kuota yang dipotong.
        $jumlahOff = TechnicianOffDay::query()
            ->where('user_id', $profil->user_id)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->count();

        $kelebihanOff = max(0, $jumlahOff - self::KUOTA_OFF);
        $potonganOff = $kelebihanOff * (float) $profil->potongan_off_per_hari;

        // Pulang cepat disetujui (dev-plan/hrd/02) = setengah hari:
        // potongan 0,5 × potongan OFF/hari tiap kejadian.
        $jumlahSetengahHari = TechnicianEarlyLeave::query()
            ->where('user_id', $profil->user_id)
            ->where('status', EarlyLeaveStatus::Disetujui->value)
            ->whereBetween('tanggal', [$dari, $sampai])
            ->count();

        $potonganSetengahHari = $jumlahSetengahHari * 0.5 * (float) $profil->potongan_off_per_hari;

        $bonusUmum = (float) ($existing->bonus_umum ?? 0);

        // Kasbon dipotong setelah semua komponen lain (gaji tidak sampai negatif).
        $sebelumKasbon = (float) $profil->gaji_pokok + $bonusBulanan + $bonusUmum
            - $dendaTelat - $potonganOff - $potonganSetengahHari - (float) $profil->potongan_bpjs_tk;
        $potonganKasbon = round(array_sum(array_column(
            $this->alokasiKasbon($profil->user_id, $mulai, $selesai, $sebelumKasbon),
            'nominal'
        )), 2);

        $adaKasbonTerlewat = $this->kasbonBelumTerpotongLewatPeriode($profil->user_id)->isNotEmpty();

        return DB::transaction(function () use (
            $existing, $profil, $mulai, $selesai, $actorId, $bulananPerKategori, $bonusBulanan,
            $bonusMingguan, $bonusUmum, $jumlahTelat, $dendaTelat, $jumlahOff, $kelebihanOff,
            $potonganOff, $jumlahSetengahHari, $potonganSetengahHari, $potonganKasbon, $adaKasbonTerlewat
        ): PayrollRun {
            $run = $existing ?? new PayrollRun;

            $run->fill([
                'user_id' => $profil->user_id,
                'periode_mulai' => $mulai->toDateString(),
                'periode_selesai' => $selesai->toDateString(),
                'gaji_pokok' => $profil->gaji_pokok,
                'bonus_bulanan' => $bonusBulanan,
                'bonus_umum' => $bonusUmum,
                'bonus_mingguan' => $bonusMingguan,
                'jumlah_hari_telat' => $jumlahTelat,
                'total_denda_telat' => round($dendaTelat, 2),
                'jumlah_hari_off' => $jumlahOff,
                'total_potongan_off' => round($potonganOff, 2),
                'jumlah_setengah_hari' => $jumlahSetengahHari,
                'total_potongan_setengah_hari' => round($potonganSetengahHari, 2),
                'total_potongan_kasbon' => $potonganKasbon,
                'potongan_bpjs_tk' => $profil->potongan_bpjs_tk,
                'status' => PayrollStatus::Draft,
                'dihitung_oleh' => $actorId,
            ]);
            $run->save();

            $run->items()->delete();
            $urutan = 0;

            foreach ($bulananPerKategori as $kategori => $nominal) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Bonus->value,
                    'kategori' => $kategori,
                    'label' => IncentiveKategori::from($kategori)->label(),
                    'nominal' => round($nominal, 2),
                    'urutan' => ++$urutan,
                ]);
            }

            if ($bonusUmum > 0) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Bonus->value,
                    'kategori' => 'bonus_umum',
                    'label' => 'Bonus Umum',
                    'nominal' => round($bonusUmum, 2),
                    'urutan' => ++$urutan,
                ]);
            }

            if ($bonusMingguan > 0) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Bonus->value,
                    'kategori' => 'bonus_mingguan',
                    'label' => 'Bonus Mingguan (Games 4,5,6)',
                    'nominal' => round($bonusMingguan, 2),
                    'urutan' => ++$urutan,
                ]);
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Potongan->value,
                    'kategori' => 'dibayar_mingguan',
                    'label' => 'Sudah dibayar mingguan (Games 4,5,6)',
                    'nominal' => round($bonusMingguan, 2),
                    'urutan' => ++$urutan,
                ]);
            }

            if ($dendaTelat > 0) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Potongan->value,
                    'kategori' => 'telat',
                    'label' => 'Denda keterlambatan ('.$jumlahTelat.' hari)',
                    'nominal' => round($dendaTelat, 2),
                    'urutan' => ++$urutan,
                ]);
            }

            if ($potonganOff > 0) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Potongan->value,
                    'kategori' => 'off',
                    'label' => 'Potongan OFF ('.$kelebihanOff.' hari)',
                    'nominal' => round($potonganOff, 2),
                    'urutan' => ++$urutan,
                ]);
            }

            if ($potonganSetengahHari > 0) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Potongan->value,
                    'kategori' => 'setengah_hari',
                    'label' => 'Pulang cepat / setengah hari ('.$jumlahSetengahHari.'×)',
                    'nominal' => round($potonganSetengahHari, 2),
                    'urutan' => ++$urutan,
                ]);
            }

            if ($potonganKasbon > 0) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Potongan->value,
                    'kategori' => 'kasbon',
                    'label' => 'Potongan Kasbon',
                    'nominal' => $potonganKasbon,
                    'urutan' => ++$urutan,
                ]);
            }

            // Item informasi (nominal 0): tidak mengubah gaji karena recalc() memakai
            // kolom total, bukan penjumlahan item.
            if ($adaKasbonTerlewat) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Potongan->value,
                    'kategori' => 'kasbon_belum_terpotong',
                    'label' => 'Kasbon periode lalu belum terpotong (perlu tindakan Admin)',
                    'nominal' => 0,
                    'urutan' => ++$urutan,
                ]);
            }

            if ((float) $profil->potongan_bpjs_tk > 0) {
                $run->items()->create([
                    'jenis' => PayrollItemJenis::Potongan->value,
                    'kategori' => 'bpjs',
                    'label' => 'Potongan BPJS TK',
                    'nominal' => round((float) $profil->potongan_bpjs_tk, 2),
                    'urutan' => ++$urutan,
                ]);
            }

            $this->recalc($run);

            return $run->fresh('items');
        });
    }

    /**
     * Ubah Bonus Umum (nominal dari Owner, diinput admin) selama draft.
     */
    public function setBonusUmum(PayrollRun $run, float $nominal, User $actor): PayrollRun
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        if (! $run->draft()) {
            throw new BusinessRuleException('Bonus umum hanya bisa diubah saat gaji masih draft.');
        }

        if ($nominal < 0) {
            throw new BusinessRuleException('Bonus umum tidak boleh negatif.');
        }

        $run->bonus_umum = round($nominal, 2);

        $run->items()->where('kategori', 'bonus_umum')->delete();

        if ($nominal > 0) {
            $run->items()->create([
                'jenis' => PayrollItemJenis::Bonus->value,
                'kategori' => 'bonus_umum',
                'label' => 'Bonus Umum',
                'nominal' => round($nominal, 2),
                'urutan' => 100,
            ]);
        }

        $this->recalc($run);

        return $run->fresh('items');
    }

    public function acc(PayrollRun $run, User $actor): PayrollRun
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        if ($run->status !== PayrollStatus::Draft) {
            throw new BusinessRuleException('Hanya gaji berstatus draft yang bisa di-ACC.');
        }

        DB::transaction(function () use ($run, $actor): void {
            $this->catatPelunasanKasbon($run);

            $run->status = PayrollStatus::Disetujui;
            $run->disetujui_oleh = $actor->id;
            $run->disetujui_pada = now();
            $run->save();
        });

        return $run->fresh();
    }

    /**
     * Saat ACC: potongan kasbon pada gaji dikunci jadi pelunasan; kasbon yang
     * sisanya habis ditandai lunas. Bila kasbon berubah sejak gaji dihitung,
     * ACC ditolak supaya gaji dihitung ulang dulu.
     */
    private function catatPelunasanKasbon(PayrollRun $run): void
    {
        $batas = (float) $run->gaji_bersih + (float) $run->total_potongan_kasbon;
        $alokasi = $this->alokasiKasbon($run->user_id, $run->periode_mulai, $run->periode_selesai, $batas);

        $total = round(array_sum(array_column($alokasi, 'nominal')), 2);

        if (abs($total - (float) $run->total_potongan_kasbon) > 0.005) {
            throw new BusinessRuleException('Data kasbon berubah sejak gaji dihitung — hitung ulang gaji dulu sebelum ACC.');
        }

        foreach ($alokasi as $a) {
            TechnicianAdvanceRepayment::updateOrCreate(
                ['technician_advance_id' => $a['advance']->id, 'payroll_run_id' => $run->id],
                ['nominal' => $a['nominal']]
            );

            if ($a['advance']->sisa() <= 0) {
                $a['advance']->update(['status' => AdvanceStatus::Lunas]);
            }
        }
    }

    /**
     * Admin menandai kasbon sudah dibayar/dipotong di luar perhitungan gaji
     * (mis. gaji periode lalu yang diproses di luar sistem). Pelunasan dicatat
     * tanpa payroll_run_id.
     */
    public function lunasiManual(TechnicianAdvance $kasbon, float $nominal, string $catatan, User $actor): TechnicianAdvance
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        if (trim($catatan) === '') {
            throw new BusinessRuleException('Catatan wajib diisi.');
        }

        // Muat ulang: status/sisa harus dari DB, bukan dari instance yang mungkin basi.
        $kasbon->refresh();

        if ($kasbon->status !== AdvanceStatus::Aktif) {
            throw new BusinessRuleException('Kasbon ini sudah lunas.');
        }

        $sisa = $kasbon->sisa();

        if ($nominal <= 0) {
            throw new BusinessRuleException('Nominal harus lebih dari 0.');
        }

        if ($nominal - $sisa > 0.005) {
            throw new BusinessRuleException('Nominal melebihi sisa kasbon.');
        }

        DB::transaction(function () use ($kasbon, $nominal, $catatan, $actor): void {
            TechnicianAdvanceRepayment::create([
                'technician_advance_id' => $kasbon->id,
                'payroll_run_id' => null,
                'nominal' => round($nominal, 2),
                'catatan' => trim($catatan),
                'dicatat_oleh' => $actor->id,
            ]);

            if ($kasbon->sisa() <= 0.005) {
                $kasbon->update(['status' => AdvanceStatus::Lunas]);
            }
        });

        $this->segarkanDraftSejak($kasbon->user_id, $kasbon->tanggal);

        return $kasbon->fresh();
    }

    public function bayar(PayrollRun $run, User $actor): PayrollRun
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        if ($run->status !== PayrollStatus::Disetujui) {
            throw new BusinessRuleException('ACC gaji dulu sebelum menandai dibayar.');
        }

        $run->status = PayrollStatus::Dibayar;
        $run->dibayar_pada = now();
        $run->save();

        return $run->fresh();
    }

    private function recalc(PayrollRun $run): void
    {
        $gaji = (float) $run->gaji_pokok
            + (float) $run->bonus_bulanan
            + (float) $run->bonus_umum
            + (float) $run->bonus_mingguan
            - (float) $run->bonus_mingguan // sudah dibayar pekanan
            - (float) $run->total_denda_telat
            - (float) $run->total_potongan_off
            - (float) $run->total_potongan_setengah_hari
            - (float) $run->total_potongan_kasbon
            - (float) $run->potongan_bpjs_tk;

        $run->gaji_bersih = round($gaji, 2);
        $run->save();
    }
}
