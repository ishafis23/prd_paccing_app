<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\V1\Concerns\MencariOrder;
use App\Http\Controllers\Controller;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Services\SyaratAdministrasiService;
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
            'sebelumnya' => $sebelumnya->map(fn (Order $o): array => Presenter::orderRingkas($o))->values(),
            'jadwal' => $mendatang->map(fn (Order $o): array => Presenter::orderRingkas($o))->values(),
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
            ->when($request->filled('tanggal'), fn ($q) => $q->whereDate('updated_at', $request->input('tanggal')))
            ->when($request->filled('bulan'), fn ($q) => $q->whereYear('updated_at', substr((string) $request->input('bulan'), 0, 4))->whereMonth('updated_at', substr((string) $request->input('bulan'), 5, 2)))
            ->when($request->filled('jenis_layanan'), fn ($q) => $q->whereHas('serviceCatalog', fn ($c) => $c->where('jenis_layanan', $request->input('jenis_layanan'))))
            ->with(['customer', 'customerAddress', 'serviceCatalog', 'latestPayment', 'orderItems', 'titik'])
            ->orderByDesc('updated_at')
            ->paginate(10);

        return response()->json([
            'data' => $hasil->getCollection()->map(fn (Order $o): array => Presenter::orderRingkas($o))->values(),
            'meta' => ['page' => $hasil->currentPage(), 'per_page' => $hasil->perPage(), 'total' => $hasil->total()],
            'opsi_layanan' => collect(ServiceTypeResolver::options())->map(fn ($label, $kode): array => ['kode' => $kode, 'label' => $label])->values(),
        ]);
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
