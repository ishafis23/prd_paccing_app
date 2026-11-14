<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\V1\Concerns\MencariOrder;
use App\Http\Controllers\Controller;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Models\OrderReview;
use App\Services\SyaratAdministrasiService;
use App\Services\UlasanService;
use App\Services\TeknisiService;
use App\Support\Api\Presenter;
use App\Support\ServiceTypeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Jadwal, riwayat pengerjaan, dan daftar "Lengkapi Laporan". */
class JadwalController extends Controller
{
    use MencariOrder;

    /** Order aktif (hari ini & mendatang) + yang tertinggal dari hari sebelumnya. */
    public function jadwal(Request $request): JsonResponse
    {
        $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);

        $u = $request->user();
        $hariIni = now()->toDateString();

        $base = Order::query()
            ->untukTeknisi($u->id)
            ->whereIn('status', [
                OrderStatus::Terjadwal->value,
                OrderStatus::MenujuLokasi->value,
                OrderStatus::Dikerjakan->value,
                OrderStatus::ButuhFollowup->value,
                OrderStatus::Terkendala->value,
            ])
            ->with(['customer', 'customerAddress', 'serviceCatalog', 'orderItems', 'titik'])
            ->orderBy('tanggal_jadwal')
            ->orderByRaw('jam_jadwal is null')
            ->orderBy('jam_jadwal')
            ->orderBy('id');

        if ($request->filled('tanggal')) {
            $base->whereDate('tanggal_jadwal', $request->input('tanggal'));
        }

        $sebelumnya = $request->filled('tanggal') ? collect() : (clone $base)->whereDate('tanggal_jadwal', '<', $hariIni)->get();
        $mendatang = $request->filled('tanggal') ? $base->get() : (clone $base)->whereDate('tanggal_jadwal', '>=', $hariIni)->get();

        $sudahAbsen = DailyAttendance::query()
            ->where('user_id', $u->id)
            ->whereDate('tanggal', $hariIni)
            ->whereNotNull('jam_datang')
            ->exists();

        $svc = app(SyaratAdministrasiService::class);
        $penghalang = $svc->penghalang($u);

        return response()->json(['data' => [
            'sudah_absen' => $sudahAbsen,
            'tertahan_setoran' => $svc->tertahanSetoran($penghalang),
            'penghalang' => $penghalang,
            'sebelumnya' => $sebelumnya->map(fn (Order $o): array => Presenter::orderRingkas($o) + Presenter::kunciTitik($o, $u))->values(),
            'jadwal' => $mendatang->map(fn (Order $o): array => Presenter::orderRingkas($o) + Presenter::kunciTitik($o, $u))->values(),
        ]]);
    }

    /** Riwayat order selesai: filter nama customer, tanggal, jenis layanan; paginasi. */
    public function riwayat(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'jenis_layanan' => ['nullable', 'string', 'max:50'],
        ]);

        $hasil = Order::query()
            ->untukTeknisi($request->user()->id)
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('nama', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('tanggal'), fn ($q) => $q->whereDate('tanggal_jadwal', $request->input('tanggal')))
            ->when($request->filled('bulan'), fn ($q) => $q->whereYear('tanggal_jadwal', substr((string) $request->input('bulan'), 0, 4))->whereMonth('tanggal_jadwal', substr((string) $request->input('bulan'), 5, 2)))
            ->when($request->filled('jenis_layanan'), fn ($q) => $q->whereHas('serviceCatalog', fn ($c) => $c->where('jenis_layanan', $request->input('jenis_layanan'))))
            ->with(['customer', 'customerAddress', 'serviceCatalog', 'latestPayment', 'orderItems', 'titik', 'workReports'])
            // Urut & saring menurut tanggal pengerjaan (yang ditampilkan), bukan updated_at
            // yang ikut berubah tiap order disentuh (bayar, tutup, dll).
            ->orderByDesc('tanggal_jadwal')
            ->orderByDesc('jam_jadwal')
            ->orderByDesc('id')
            ->paginate(10);

        return response()->json([
            'data' => $hasil->getCollection()->map(fn (Order $o): array => Presenter::orderRingkas($o))->values(),
            'meta' => ['page' => $hasil->currentPage(), 'per_page' => $hasil->perPage(), 'total' => $hasil->total()],
            'opsi_layanan' => collect(ServiceTypeResolver::options())->map(fn ($label, $kode): array => ['kode' => $kode, 'label' => $label])->values(),
        ]);
    }

    /** Ulasan customer untuk teknisi login: rata-rata + 20 ulasan terbaru. */
    public function ulasan(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json(['data' => [
            'ringkasan' => app(UlasanService::class)->ringkasanTeknisi($u->id),
            'daftar' => OrderReview::query()
                ->where('teknisi_id', $u->id)
                ->with('order.customer')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (OrderReview $r): array => [
                    'order_id' => $r->order_id,
                    'customer' => $r->order?->customer?->nama,
                    'rating' => $r->rating,
                    'komentar' => $r->komentar,
                    'dibuat_pada' => $r->created_at?->toIso8601String(),
                ])->values(),
        ]]);
    }

    /** Order yang laporannya sudah dikirim tetapi foto/keterangan unitnya belum lengkap. */
    public function lengkapi(Request $request): JsonResponse
    {
        $svc = app(TeknisiService::class);

        $daftar = Order::query()
            ->untukTeknisi($request->user()->id)
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->whereNull('ditutup_pada')
            ->with(['customer', 'customerAddress', 'orderItems', 'titik'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Order $order) use ($svc): ?array {
                $kurang = collect($svc->fotoWajibKurang($order));

                if ($kurang->isEmpty()) {
                    return null;
                }

                return Presenter::orderRingkas($order) + [
                    'kurang_foto' => $kurang->where('jenis', 'foto')->count(),
                    'kurang_keterangan' => $kurang->where('jenis', 'keterangan')->count(),
                    'rincian' => $kurang->pluck('label')->values(),
                    'unit_pertama_kurang' => $kurang->first(fn (array $k): bool => isset($k['unit_no']))['unit_no'] ?? null,
                ];
            })
            ->filter()
            ->values();

        return response()->json(['data' => $daftar, 'meta' => ['jumlah' => $daftar->count()]]);
    }
}
