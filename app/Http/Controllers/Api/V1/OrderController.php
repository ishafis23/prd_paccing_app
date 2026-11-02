<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IncomeCategory;
use App\Enums\PaymentMethod;
use App\Enums\ServiceType;
use App\Http\Controllers\Api\V1\Concerns\MencariOrder;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\OrderItem;
use App\Services\AttendanceService;
use App\Services\OrderService;
use App\Services\StorageQuotaService;
use App\Services\TeknisiService;
use App\Support\Api\Presenter;
use App\Support\ServiceTypeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Detail order & aksi status/layanan/pembayaran oleh teknisi (tim) di lapangan. */
class OrderController extends Controller
{
    use MencariOrder;

    private function detail(Request $request, int|string $id): JsonResponse
    {
        $order = $this->cariOrder($request, $id)->fresh();

        return response()->json(['data' => Presenter::orderDetail($order, $request->user())]);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return $this->detail($request, $order);
    }

    public function berangkat(Request $request, int $order): JsonResponse
    {
        app(TeknisiService::class)->berangkat($this->cariOrder($request, $order, tulis: true), $request->user());

        return $this->detail($request, $order);
    }

    /** Ping GPS opsional selama perjalanan; gagal diam-diam bila status sudah berpindah. */
    public function lokasi(Request $request, int $order): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            app(TeknisiService::class)->updateLokasi($this->cariOrder($request, $order), $request->user(), (float) $data['lat'], (float) $data['lng']);
        } catch (\App\Exceptions\BusinessRuleException) {
            return response()->json(['data' => ['tercatat' => false]]);
        }

        return response()->json(['data' => ['tercatat' => true]]);
    }

    /** Check-in; check-in pertama hari itu wajib `foto_titik_pertama` (Games 2). */
    public function checkIn(Request $request, int $order): JsonResponse
    {
        $request->validate(['foto_titik_pertama' => ['nullable', 'image', 'max:5120']]);

        $o = $this->cariOrder($request, $order, tulis: true);
        $u = $request->user();

        $butuhFoto = ! Attendance::query()->where('user_id', $u->id)->where('tanggal', now()->toDateString())->exists();

        if ($butuhFoto && ! $request->hasFile('foto_titik_pertama')) {
            return response()->json([
                'message' => 'Wajib foto bukti sampai lokasi (mis. buka cover AC indoor) — check in pertama hari ini (Games 2).',
                'code' => 'foto_titik_pertama_wajib',
                'errors' => ['foto_titik_pertama' => ['Foto bukti sampai lokasi wajib diisi.']],
            ], 422);
        }

        if ($butuhFoto) {
            app(StorageQuotaService::class)->pastikanCukup((int) $request->file('foto_titik_pertama')->getSize());
        }

        app(TeknisiService::class)->checkIn($o, $u);

        if ($butuhFoto) {
            app(AttendanceService::class)->catatTitikPertama($u, $request->file('foto_titik_pertama'));
        }

        return $this->detail($request, $order);
    }

    public function kendala(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:1000']]);

        app(TeknisiService::class)->tandaiKendala($this->cariOrder($request, $order, tulis: true), $request->user(), $data['alasan']);

        return $this->detail($request, $order);
    }

    public function perbaikan(Request $request, int $order): JsonResponse
    {
        $data = $request->validate([
            'catatan' => ['required', 'string', 'max:1000'],
            'estimasi_harga' => ['nullable', 'numeric', 'min:0'],
        ]);

        app(TeknisiService::class)->laporPerbaikan(
            $this->cariOrder($request, $order, tulis: true),
            $request->user(),
            $data['catatan'],
            isset($data['estimasi_harga']) ? (float) $data['estimasi_harga'] : null,
        );

        return $this->detail($request, $order);
    }

    public function tutup(Request $request, int $order): JsonResponse
    {
        app(TeknisiService::class)->tutupOrder($this->cariOrder($request, $order, tulis: true), $request->user());

        return $this->detail($request, $order);
    }

    // ---- Layanan & item ----

    /** Opsi form "Tambah Layanan": kategori + komponen default per kategori. */
    public function opsiLayanan(): JsonResponse
    {
        return response()->json(['data' => [
            'kategori' => collect(ServiceTypeResolver::options())->map(fn ($label, $kode): array => [
                'kode' => $kode,
                'label' => $label,
                'komponen_default' => IncomeCategory::defaultUntukBaris(ServiceType::tryFrom((string) $kode), '')->value,
            ])->values(),
            'komponen' => ['jasa', 'material'],
        ]]);
    }

    public function tambahLayanan(Request $request, int $order): JsonResponse
    {
        $data = $request->validate([
            'nama_layanan' => ['required', 'string', 'min:2', 'max:100'],
            'kategori' => ['required', Rule::in(array_keys(ServiceTypeResolver::options()))],
            'harga' => ['required', 'numeric', 'min:0'],
            'komponen' => ['nullable', Rule::in(['jasa', 'material'])],
            'jumlah' => ['required', 'integer', 'min:1', 'max:1000'],
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $komponen = $data['komponen'] ?? IncomeCategory::defaultUntukBaris(ServiceType::tryFrom($data['kategori']), $data['nama_layanan'])->value;

        app(OrderService::class)->tambahLayananOlehTeknisi($this->cariOrder($request, $order, tulis: true), $request->user(), [
            'nama_layanan' => $data['nama_layanan'],
            'kategori' => $data['kategori'],
            'komponen' => $komponen,
            'harga' => (float) $data['harga'],
            'jumlah' => (int) $data['jumlah'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        return $this->detail($request, $order);
    }

    public function hapusLayanan(Request $request, int $order, int $item): JsonResponse
    {
        $o = $this->cariOrder($request, $order, tulis: true);
        $baris = OrderItem::query()->where('order_id', $o->id)->findOrFail($item);

        app(OrderService::class)->hapusLayananOlehTeknisi($baris, $request->user());

        return $this->detail($request, $order);
    }

    /** Tandai satu baris layanan "tidak jadi/batal". */
    public function batalkanItem(Request $request, int $order, int $item): JsonResponse
    {
        $o = $this->cariOrder($request, $order, tulis: true);
        $baris = OrderItem::query()->where('order_id', $o->id)->findOrFail($item);

        app(OrderService::class)->batalkanItem($baris, $request->user(), $request->input('alasan'));

        return $this->detail($request, $order);
    }

    public function aktifkanItem(Request $request, int $order, int $item): JsonResponse
    {
        $o = $this->cariOrder($request, $order, tulis: true);
        $baris = OrderItem::query()->where('order_id', $o->id)->findOrFail($item);

        app(OrderService::class)->aktifkanItem($baris, $request->user());

        return $this->detail($request, $order);
    }

    // ---- Pembayaran ----

    /** Tandai metode bayar pilihan customer; `metode` null = hapus tanda. */
    public function metode(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['metode' => ['nullable', Rule::in(array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases()))]]);

        app(TeknisiService::class)->catatMetodeDipilih(
            $this->cariOrder($request, $order, tulis: true),
            $request->user(),
            isset($data['metode']) ? PaymentMethod::from($data['metode']) : null,
        );

        return $this->detail($request, $order);
    }

    public function bukti(Request $request, int $order): JsonResponse
    {
        $request->validate(['foto' => ['required', 'image', 'max:5120']]);

        $o = $this->cariOrder($request, $order, tulis: true);
        $file = $request->file('foto');

        app(StorageQuotaService::class)->pastikanCukup((int) $file->getSize());
        $path = $file->store('bukti-pembayaran', 'public');

        try {
            app(TeknisiService::class)->uploadBuktiPembayaran($o, $request->user(), $path);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
            throw $e;
        }

        StorageQuotaService::lupakanCache();

        return $this->detail($request, $order);
    }

    /** Tanda "customer sudah bayar" (sinyal ke Admin/Finance). */
    public function laporBayar(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['sudah' => ['required', 'boolean']]);

        app(TeknisiService::class)->laporBayar($this->cariOrder($request, $order, tulis: true), $request->user(), (bool) $data['sudah']);

        return $this->detail($request, $order);
    }
}
