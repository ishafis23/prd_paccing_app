<?php

namespace App\Services;

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
use App\Models\TechnicianEarlyLeave;
use App\Models\TechnicianIncentive;
use App\Models\TechnicianOffDay;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Penggajian teknisi (dev-plan/hrd/02). Fokus hitungan & status; slip dirender
 * dari PayrollRun + items.
 *
 * Gaji bersih = Gaji Pokok + Bonus Bulanan + Bonus Umum + Bonus Mingguan
 *               − Bonus Mingguan (sudah dibayar pekanan) − Denda Telat
 *               − Potongan OFF (kelebihan kuota) − BPJS TK.
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
     * Hitung (ulang) gaji satu teknisi untuk satu periode → draft PayrollRun.
     */
    public function hitung(TechnicianPayrollProfile $profil, CarbonInterface $mulaiAwal, User $actor): PayrollRun
    {
        $this->assertRole($actor, self::PENGELOLA_ROLES);

        [$mulai, $selesai] = $this->rentangPeriode($mulaiAwal);

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

        // Bonus dari ledger insentif (hanya yang sudah disetujui).
        $ledger = TechnicianIncentive::query()
            ->where('user_id', $profil->user_id)
            ->where('tipe', IncentiveTipe::Bonus->value)
            ->where('status_verifikasi', IncentiveStatusVerifikasi::Disetujui->value)
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

        return DB::transaction(function () use (
            $existing, $profil, $mulai, $selesai, $actor, $bulananPerKategori, $bonusBulanan,
            $bonusMingguan, $bonusUmum, $jumlahTelat, $dendaTelat, $jumlahOff, $kelebihanOff,
            $potonganOff, $jumlahSetengahHari, $potonganSetengahHari
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
                'potongan_bpjs_tk' => $profil->potongan_bpjs_tk,
                'status' => PayrollStatus::Draft,
                'dihitung_oleh' => $actor->id,
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

        $run->status = PayrollStatus::Disetujui;
        $run->disetujui_oleh = $actor->id;
        $run->disetujui_pada = now();
        $run->save();

        return $run->fresh();
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
            - (float) $run->potongan_bpjs_tk;

        $run->gaji_bersih = round($gaji, 2);
        $run->save();
    }
}
