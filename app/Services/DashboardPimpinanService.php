<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DailyAttendanceStatus;
use App\Enums\IncomeCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReminderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\DailyAttendance;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ServiceReminder;
use App\Models\StockItem;
use App\Models\TeknisiExpense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Agregasi data utk halaman "Dashboard Pimpinan" — read-only, khusus
 * owner/admin. Semua angka dihitung ulang dari sumber yang sama dengan
 * halaman Akuntan (AkuntanService) supaya konsisten.
 *
 * Catatan versi dasar:
 * - Neraca memakai Kas = total pembayaran lunas dikurangi total pengeluaran;
 *   Persediaan = nilai stok; Piutang = order selesai yang belum lunas.
 *   Kewajiban = 0 (belum ada pencatatan utang).
 * - Arus Kas memakai kas riil (pembayaran lunas vs pengeluaran bulan itu).
 */
class DashboardPimpinanService
{
    /** Label manusiawi per kategori layanan (poin di dalam klasifikasi). */
    private const LABEL_LAYANAN = [
        'cuci_ac' => 'Cuci AC',
        'service_ac' => 'Service AC',
        'pengadaan_ac' => 'Pengadaan AC',
        'tambah_freon' => 'Tambah Freon',
        'instalasi' => 'Instalasi',
        'relokasi' => 'Relokasi',
        'bongkar' => 'Bongkar',
    ];

    public function __construct(private readonly AkuntanService $akuntan) {}

    /**
     * Ringkasan jumlah customer & pertumbuhan bulan ini.
     *
     * @return array{total: int, aktif: int, lead: int, nonaktif: int, baru_bulan_ini: int}
     */
    public function ringkasanCustomer(CarbonInterface $awal, CarbonInterface $akhir): array
    {
        $perStatus = Customer::query()
            ->selectRaw('status, COUNT(*) AS jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return [
            'total' => (int) $perStatus->sum(),
            'aktif' => (int) ($perStatus[CustomerStatus::Aktif->value] ?? 0),
            'lead' => (int) ($perStatus[CustomerStatus::Lead->value] ?? 0),
            'nonaktif' => (int) ($perStatus[CustomerStatus::Nonaktif->value] ?? 0),
            'baru_bulan_ini' => Customer::query()
                ->whereBetween('created_at', [$awal, $akhir])
                ->count(),
        ];
    }

    /**
     * Sebaran jumlah unit AC per customer.
     *
     * @return array{total_unit: int, customer_punya_unit: int, rata_rata: float, top: array<int, array{nama: string, jumlah_unit: int}>}
     */
    public function unitPerCustomer(): array
    {
        $totalUnit = CustomerAcUnit::query()->count();
        $customerPunyaUnit = (int) CustomerAcUnit::query()->distinct()->count('customer_id');

        $top = Customer::query()
            ->withCount('acUnits')
            ->whereHas('acUnits')
            ->orderByDesc('ac_units_count')
            ->orderBy('nama')
            ->limit(5)
            ->get()
            ->map(fn (Customer $c): array => [
                'nama' => $c->nama,
                'jumlah_unit' => (int) $c->ac_units_count,
            ])
            ->all();

        return [
            'total_unit' => (int) $totalUnit,
            'customer_punya_unit' => $customerPunyaUnit,
            'rata_rata' => $customerPunyaUnit > 0 ? round($totalUnit / $customerPunyaUnit, 1) : 0.0,
            'top' => $top,
        ];
    }

    /**
     * Follow-up jadwal cuci bulan terpilih (dari service_reminders):
     * - perlu_difollow_up : belum/siap dihubungi
     * - sudah_pending     : sudah dihubungi, menunggu pelaksanaan
     * - terlaksana        : sudah dihubungi & selesai
     *
     * @return array{total: int, perlu_difollow_up: int, sudah_pending: int, terlaksana: int}
     */
    public function followUp(CarbonInterface $awal, CarbonInterface $akhir): array
    {
        /** @var Collection<int, ServiceReminder> $reminders */
        $reminders = ServiceReminder::query()
            ->whereBetween('tanggal_servis_berikutnya', [$awal->toDateString(), $akhir->toDateString()])
            ->get();

        return [
            'total' => $reminders->count(),
            'perlu_difollow_up' => $reminders
                ->whereIn('status_notice', [ReminderStatus::BelumJatuhTempo, ReminderStatus::SiapDihubungi])
                ->count(),
            'sudah_pending' => $reminders->where('status_notice', ReminderStatus::SudahDihubungi)->count(),
            'terlaksana' => $reminders->where('status_notice', ReminderStatus::Selesai)->count(),
        ];
    }

    /**
     * Pendapatan, pengeluaran, dan laba/rugi bulan terpilih.
     *
     * @return array{pendapatan: float, pendapatan_jasa: float, pendapatan_material: float, pengeluaran: float, pengeluaran_pending: float, laba_rugi: float}
     */
    public function keuangan(CarbonInterface $awal, CarbonInterface $akhir): array
    {
        $pendapatan = $this->akuntan->pendapatan($awal, $akhir);
        $pengeluaran = $this->akuntan->pengeluaran($awal, $akhir);

        $totalPendapatan = (float) $pendapatan->sum('total');
        $totalPengeluaran = (float) $pengeluaran->where('dihitung', true)->sum('nominal');

        return [
            'pendapatan' => $totalPendapatan,
            'pendapatan_jasa' => (float) $pendapatan->sum('total_jasa'),
            'pendapatan_material' => (float) $pendapatan->sum('total_material'),
            'pengeluaran' => $totalPengeluaran,
            'pengeluaran_pending' => (float) $pengeluaran->where('dihitung', false)->sum('nominal'),
            'laba_rugi' => $totalPendapatan - $totalPengeluaran,
        ];
    }

    /**
     * Neraca sederhana (posisi saat ini, bukan per bulan).
     *
     * @return array{kas: float, persediaan: float, piutang: float, total_aset: float, kewajiban: float, ekuitas: float}
     */
    public function neraca(): array
    {
        $kasMasuk = (float) Payment::query()
            ->where('status', PaymentStatus::Lunas->value)
            ->sum('jumlah_dibayar');

        $kasKeluar = (float) Expense::query()->sum('nominal')
            + (float) TeknisiExpense::query()->where('status', 'approved')->sum('nominal');

        $kas = $kasMasuk - $kasKeluar;

        $persediaan = (float) StockItem::query()
            ->where('aktif', true)
            ->get()
            ->sum(fn (StockItem $s): float => (float) $s->stok_saat_ini * (float) $s->harga_beli);

        $piutang = (float) Order::query()
            ->where('status', OrderStatus::Selesai->value)
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', PaymentStatus::Lunas->value))
            ->with('orderItems')
            ->get()
            ->sum(fn (Order $o): float => $o->total());

        $totalAset = $kas + $persediaan + $piutang;
        $kewajiban = 0.0;

        return [
            'kas' => $kas,
            'persediaan' => $persediaan,
            'piutang' => $piutang,
            'total_aset' => $totalAset,
            'kewajiban' => $kewajiban,
            'ekuitas' => $totalAset - $kewajiban,
        ];
    }

    /**
     * Arus kas bulan terpilih (kas riil) + saldo.
     *
     * @return array{masuk: float, keluar: float, bersih: float, saldo_awal: float, saldo_akhir: float}
     */
    public function arusKas(CarbonInterface $awal, CarbonInterface $akhir): array
    {
        $masukBulan = (float) Payment::query()
            ->where('status', PaymentStatus::Lunas->value)
            ->whereBetween('tanggal_bayar', [$awal->toDateString(), $akhir->toDateString()])
            ->sum('jumlah_dibayar');

        $masukSebelum = (float) Payment::query()
            ->where('status', PaymentStatus::Lunas->value)
            ->where('tanggal_bayar', '<', $awal->toDateString())
            ->sum('jumlah_dibayar');

        $pengeluaranBulan = $this->akuntan->pengeluaran($awal, $akhir);
        $keluarBulan = $this->akuntan->totalPengeluaran($pengeluaranBulan);

        $keluarSebelum = (float) Expense::query()
            ->where('tanggal', '<', $awal->toDateString())
            ->sum('nominal')
            + (float) TeknisiExpense::query()
                ->where('status', 'approved')
                ->where('tanggal_input', '<', $awal->toDateString())
                ->sum('nominal');

        $saldoAwal = $masukSebelum - $keluarSebelum;
        $bersih = $masukBulan - $keluarBulan;

        return [
            'masuk' => $masukBulan,
            'keluar' => $keluarBulan,
            'bersih' => $bersih,
            'saldo_awal' => $saldoAwal,
            'saldo_akhir' => $saldoAwal + $bersih,
        ];
    }

    /**
     * Pengerjaan tiap teknisi aktif pada bulan terpilih: jumlah order,
     * unit yang dikerjakan, dan rupiah yang dihasilkan.
     *
     * @return array<int, array{nama: string, orders: int, unit: int, rupiah: float}>
     */
    public function performaTeknisi(CarbonInterface $awal, CarbonInterface $akhir): array
    {
        return $this->teknisiAktif()
            ->map(function (User $teknisi) use ($awal, $akhir): array {
                $orders = Order::query()
                    ->untukTeknisi($teknisi->id)
                    ->where('status', OrderStatus::Selesai->value)
                    ->whereBetween('updated_at', [$awal, $akhir])
                    ->with('orderItems')
                    ->get();

                $unit = (int) $orders->sum(
                    fn (Order $o): int => (int) $o->orderItems
                        ->reject(fn (OrderItem $i): bool => $i->dibatalkan())
                        ->sum('jumlah')
                );

                return [
                    'nama' => $teknisi->name,
                    'orders' => $orders->count(),
                    'unit' => $unit,
                    'rupiah' => (float) $orders->sum(fn (Order $o): float => $o->total()),
                ];
            })
            ->sortByDesc('rupiah')
            ->values()
            ->all();
    }

    /**
     * Rekap kehadiran harian teknisi pada bulan terpilih.
     *
     * @return array<int, array{nama: string, hadir: int, normal: int, bonus: int, telat: int}>
     */
    public function kehadiranTeknisi(CarbonInterface $awal, CarbonInterface $akhir): array
    {
        /** @var Collection<int, Collection<int, DailyAttendance>> $absen */
        $absen = DailyAttendance::query()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get()
            ->groupBy('user_id');

        return $this->teknisiAktif()
            ->map(function (User $teknisi) use ($absen): array {
                $rows = $absen->get($teknisi->id, collect());

                return [
                    'nama' => $teknisi->name,
                    'hadir' => $rows->count(),
                    'normal' => $rows->where('status_datang', DailyAttendanceStatus::Normal)->count(),
                    'bonus' => $rows->where('status_datang', DailyAttendanceStatus::Bonus)->count(),
                    'telat' => $rows
                        ->whereIn('status_datang', [DailyAttendanceStatus::Telat, DailyAttendanceStatus::TelatToleransi])
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Rekap pendapatan harian (desc) pada bulan terpilih.
     *
     * @return Collection<int, array{tanggal: string, jumlah: int, total: float, pending: float}>
     */
    public function pendapatanHarian(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        return $this->akuntan->rekapHarian($this->akuntan->pendapatan($awal, $akhir));
    }

    /**
     * Rekap pengeluaran harian (desc) pada bulan terpilih.
     *
     * @return Collection<int, array{tanggal: string, jumlah: int, total: float, pending: float}>
     */
    public function pengeluaranHarian(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        return $this->akuntan->rekapHarian($this->akuntan->pengeluaran($awal, $akhir));
    }

    /**
     * @return Collection<int, array{mulai: string, label: string, jumlah: int, total: float, pending: float}>
     */
    public function pendapatanPekanan(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        return $this->rekapPekanan($this->akuntan->pendapatan($awal, $akhir));
    }

    /**
     * @return Collection<int, array{mulai: string, label: string, jumlah: int, total: float, pending: float}>
     */
    public function pengeluaranPekanan(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        return $this->rekapPekanan($this->akuntan->pengeluaran($awal, $akhir));
    }

    /**
     * Laba/rugi per hari: pendapatan (accrual) dikurangi pengeluaran (yang
     * dihitung) pada tanggal yang sama.
     *
     * @return Collection<int, array{tanggal: string, pendapatan: float, pengeluaran: float, laba_rugi: float}>
     */
    public function labaRugiHarian(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        $pendapatan = $this->pendapatanHarian($awal, $akhir)->keyBy('tanggal');
        $pengeluaran = $this->pengeluaranHarian($awal, $akhir)->keyBy('tanggal');

        return $pendapatan->keys()
            ->merge($pengeluaran->keys())
            ->unique()
            ->sortDesc()
            ->values()
            ->map(function (string $tanggal) use ($pendapatan, $pengeluaran): array {
                $masuk = (float) (($pendapatan->get($tanggal)['total'] ?? null) ?? 0);
                $keluar = (float) (($pengeluaran->get($tanggal)['total'] ?? null) ?? 0);

                return [
                    'tanggal' => $tanggal,
                    'pendapatan' => $masuk,
                    'pengeluaran' => $keluar,
                    'laba_rugi' => $masuk - $keluar,
                ];
            });
    }

    /**
     * Laba/rugi per pekan (Senin–Minggu) pada bulan terpilih.
     *
     * @return Collection<int, array{mulai: string, label: string, pendapatan: float, pengeluaran: float, laba_rugi: float}>
     */
    public function labaRugiPekanan(CarbonInterface $awal, CarbonInterface $akhir): Collection
    {
        return $this->labaRugiHarian($awal, $akhir)
            ->groupBy(fn (array $r): string => CarbonImmutable::parse($r['tanggal'])
                ->startOfWeek(CarbonInterface::MONDAY)
                ->toDateString())
            ->map(function (Collection $grup, string $mulai): array {
                $awalPekan = CarbonImmutable::parse($mulai);
                $akhirPekan = $awalPekan->endOfWeek(CarbonInterface::SUNDAY);
                $pendapatan = (float) $grup->sum('pendapatan');
                $pengeluaran = (float) $grup->sum('pengeluaran');

                return [
                    'mulai' => $mulai,
                    'label' => $awalPekan->translatedFormat('d M').' – '.$akhirPekan->translatedFormat('d M'),
                    'pendapatan' => $pendapatan,
                    'pengeluaran' => $pengeluaran,
                    'laba_rugi' => $pendapatan - $pengeluaran,
                ];
            })
            ->sortBy('mulai')
            ->values();
    }

    /**
     * Rekap per pekan (Senin–Minggu) dari baris pendapatan/pengeluaran.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{mulai: string, label: string, jumlah: int, total: float, pending: float}>
     */
    public function rekapPekanan(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $r): string => CarbonImmutable::parse($r['tanggal'])
                ->startOfWeek(CarbonInterface::MONDAY)
                ->toDateString())
            ->map(function (Collection $grup, string $mulai): array {
                $awalPekan = CarbonImmutable::parse($mulai);
                $akhirPekan = $awalPekan->endOfWeek(CarbonInterface::SUNDAY);

                return [
                    'mulai' => $mulai,
                    'label' => $awalPekan->translatedFormat('d M').' – '.$akhirPekan->translatedFormat('d M'),
                    'jumlah' => $grup->count(),
                    'total' => (float) $grup
                        ->filter(fn (array $r): bool => $r['dihitung'] ?? true)
                        ->sum(fn (array $r) => $r['total'] ?? $r['nominal']),
                    'pending' => (float) $grup
                        ->filter(fn (array $r): bool => ! ($r['dihitung'] ?? true))
                        ->sum('nominal'),
                ];
            })
            ->sortBy('mulai')
            ->values();
    }

    /**
     * Pengerjaan per klasifikasi (cuci / service / pemasangan) bulan terpilih,
     * lengkap dengan rincian tiap jenis layanan di dalamnya.
     *
     * @return array<string, array{unit: int, rupiah: float, transaksi: int, items: array<int, array{label: string, unit: int, rupiah: float}>}>
     */
    public function klasifikasiPengerjaan(CarbonInterface $awal, CarbonInterface $akhir): array
    {
        $items = OrderItem::query()
            ->where('dibatalkan', false)
            ->whereHas('order', fn ($q) => $q
                ->where('status', OrderStatus::Selesai->value)
                ->whereBetween('updated_at', [$awal, $akhir]))
            ->get();

        $grup = [
            'cuci' => [ServiceType::CuciAc],
            'service' => [ServiceType::ServiceAc, ServiceType::TambahFreon],
            'pemasangan' => [ServiceType::PengadaanAc, ServiceType::Instalasi, ServiceType::Relokasi, ServiceType::Bongkar],
        ];

        $hasil = [];

        foreach ($grup as $nama => $jenis) {
            $rows = $items->whereIn('kategori', $jenis);

            $rincian = collect($jenis)
                ->map(function (ServiceType $st) use ($rows): array {
                    $perJenis = $rows->where('kategori', $st);

                    return [
                        'label' => self::LABEL_LAYANAN[$st->value] ?? $st->value,
                        'unit' => (int) $perJenis->sum('jumlah'),
                        'rupiah' => (float) $perJenis->sum(fn (OrderItem $i): float => (float) $i->harga * (int) $i->jumlah),
                    ];
                })
                ->filter(fn (array $x): bool => $x['unit'] > 0 || $x['rupiah'] > 0)
                ->values()
                ->all();

            $hasil[$nama] = [
                'unit' => (int) $rows->sum('jumlah'),
                'rupiah' => (float) $rows->sum(fn (OrderItem $i): float => (float) $i->harga * (int) $i->jumlah),
                'transaksi' => $rows->count(),
                'items' => $rincian,
            ];
        }

        return $hasil;
    }

    /**
     * @return Collection<int, User>
     */
    private function teknisiAktif(): Collection
    {
        return User::query()
            ->role(RoleName::Teknisi->value)
            ->where('status', UserStatus::Aktif->value)
            ->orderBy('name')
            ->get();
    }
}
