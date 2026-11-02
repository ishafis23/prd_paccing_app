<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Api\V1\Concerns\MencariOrder;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderUnitReport;
use App\Models\StockItem;
use App\Models\TemporaryPhotoUpload;
use App\Models\WorkReportPhoto;
use App\Services\StorageQuotaService;
use App\Services\TeknisiService;
use App\Services\UnitReportService;
use App\Support\Api\Presenter;
use App\Support\FotoLaporanSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Laporan pengerjaan: keterangan per unit, foto (satu per request, idempoten per slot),
 * finalisasi laporan, dan perbaikan foto sesudah laporan terkirim.
 *
 * Foto sebelum laporan dikirim ditampung di `temporary_photo_uploads` (field_name
 * `fotoKategori.{item}.{slot}` / `fotoUnit.{unit}.{slot}` / `fotoSebelum` / `fotoSesudah`,
 * satu slot satu foto — unggah ulang menggantikan) lalu dikonsumsi `POST /order/{id}/laporan`.
 * Setelah laporan ada, foto langsung ditautkan ke laporan.
 */
class LaporanController extends Controller
{
    use MencariOrder;

    /** Barang stok untuk material terpakai. */
    public function opsiMaterial(): JsonResponse
    {
        return response()->json(['data' => StockItem::query()->where('aktif', true)->orderBy('nama_barang')->get()
            ->map(fn (StockItem $s): array => [
                'id' => $s->id,
                'nama' => $s->nama_barang,
                'satuan' => $s->satuan,
                'stok' => (int) $s->stok_saat_ini,
            ])->values()]);
    }

    private function unitOrder(Order $order, int $unitId): OrderUnitReport
    {
        return OrderUnitReport::query()->where('order_id', $order->id)->with('orderItem')->findOrFail($unitId);
    }

    /** Simpan keterangan satu unit. */
    public function simpanUnit(Request $request, int $order, int $unit): JsonResponse
    {
        $o = $this->cariOrder($request, $order, tulis: true);
        $svc = app(UnitReportService::class);
        $u = $this->unitOrder($o, $unit);

        $simpan = $svc->simpan($u, $request->only(['lokasi_label', 'posisi', 'jenis_pekerjaan', 'bagian', 'suhu', 'rpm', 'kondisi', 'catatan_kondisi']), $request->user());

        return response()->json(['data' => Presenter::unit($simpan->load('orderItem'), $svc, collect())]);
    }

    /** Foto yang sudah tersimpan + yang masih ditampung (belum laporan). */
    public function daftarFoto(Request $request, int $order): JsonResponse
    {
        $o = $this->cariOrder($request, $order);

        $tersimpan = WorkReportPhoto::query()
            ->whereHas('workReport', fn ($q) => $q->where('order_id', $o->id))
            ->get()
            ->map(fn (WorkReportPhoto $f): array => Presenter::foto($f))->values();

        $sementara = TemporaryPhotoUpload::query()
            ->where('user_id', $request->user()->id)
            ->where('order_id', $o->id)
            ->get()
            ->filter(fn (TemporaryPhotoUpload $p): bool => filled($p->file_path) && Storage::disk('public')->exists($p->file_path))
            ->map(fn (TemporaryPhotoUpload $p): array => ['id' => $p->id, 'field_name' => $p->field_name, 'url' => Presenter::url($p->file_path)])
            ->values();

        return response()->json(['data' => [
            'tersimpan' => $tersimpan,
            'sementara' => $sementara,
            'foto_wajib_kurang' => collect(app(TeknisiService::class)->fotoWajibKurang($o->load('orderItems')))
                ->map(fn (array $k): array => [
                    'jenis' => $k['jenis'], 'label' => $k['label'], 'order_item_id' => $k['order_item']->id,
                    'kode_slot' => $k['kode_slot'], 'unit_id' => $k['unit_report_id'] ?? null,
                ])->values(),
        ]]);
    }

    /**
     * Unggah satu foto. Body multipart: `file`, `tipe` (kategori|unit|sebelum|sesudah),
     * `order_item_id` (kategori), `unit_id` (unit), `slot`.
     * Idempoten per slot: mengirim ulang foto slot yang sama menggantikan, tidak menggandakan.
     */
    public function unggahFoto(Request $request, int $order): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'image', 'max:5120'],
            'tipe' => ['required', 'in:kategori,unit,sebelum,sesudah'],
            'order_item_id' => ['required_if:tipe,kategori', 'nullable', 'integer'],
            'unit_id' => ['required_if:tipe,unit', 'nullable', 'integer'],
            'slot' => ['required_if:tipe,kategori,unit', 'nullable', 'string', 'max:100'],
        ]);

        $o = $this->cariOrder($request, $order, tulis: true);
        $u = $request->user();
        $file = $request->file('file');
        $tipe = $data['tipe'];

        app(StorageQuotaService::class)->pastikanCukup((int) $file->getSize());

        $laporanAda = $o->workReports()->exists();
        $selesai = in_array($o->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true);

        // Validasi slot sebelum menyimpan file apa pun.
        if ($tipe === 'kategori') {
            $item = $o->orderItems()->findOrFail((int) $data['order_item_id']);
            if (! array_key_exists($data['slot'], FotoLaporanSlot::untuk($item->kategori))) {
                throw new BusinessRuleException("Slot foto '{$data['slot']}' tidak valid untuk layanan {$item->nama_layanan}.");
            }
        }

        if ($tipe === 'unit') {
            $unit = $this->unitOrder($o, (int) $data['unit_id']);
        }

        // Jalur langsung: laporan sudah ada.
        if ($laporanAda && $selesai && $tipe === 'unit') {
            $foto = app(UnitReportService::class)->simpanFotoUnit($unit, $data['slot'], $file, $u);
            StorageQuotaService::lupakanCache();

            return response()->json(['data' => ['status' => 'tersimpan', 'foto' => Presenter::foto($foto)]], 201);
        }

        if ($laporanAda && $selesai && $tipe === 'kategori') {
            $path = $file->store('work-reports', 'public');
            try {
                app(TeknisiService::class)->lengkapiFotoWajib($o, $u, [[
                    'order_item_id' => (int) $data['order_item_id'],
                    'slot' => $data['slot'],
                    'path' => $path,
                ]]);
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($path);
                throw $e;
            }
            StorageQuotaService::lupakanCache();

            $foto = WorkReportPhoto::query()->where('order_item_id', (int) $data['order_item_id'])->where('slot', $data['slot'])->latest('id')->first();

            return response()->json(['data' => ['status' => 'tersimpan', 'foto' => $foto ? Presenter::foto($foto) : null]], 201);
        }

        if ($laporanAda && $selesai && in_array($tipe, ['sebelum', 'sesudah'], true)) {
            $path = $file->store('work-reports', 'public');
            app(TeknisiService::class)->perbaruiFotoLaporan($o, $u, $tipe === 'sebelum' ? $path : null, $tipe === 'sesudah' ? $path : null);
            StorageQuotaService::lupakanCache();

            return response()->json(['data' => ['status' => 'tersimpan', 'foto' => ['url' => Presenter::url($path)]]], 201);
        }

        // Jalur tampung: laporan belum dikirim.
        $fieldName = match ($tipe) {
            'kategori' => "fotoKategori.{$data['order_item_id']}.{$data['slot']}",
            'unit' => "fotoUnit.{$data['unit_id']}.{$data['slot']}",
            'sebelum' => 'fotoSebelum',
            'sesudah' => 'fotoSesudah',
        };

        TemporaryPhotoUpload::query()
            ->where('user_id', $u->id)->where('order_id', $o->id)->where('field_name', $fieldName)
            ->get()->each(function (TemporaryPhotoUpload $lama): void {
                $lama->deleteFile();
                $lama->forceDelete();
            });

        $path = $file->store('temporary-photos', 'public');
        $temp = TemporaryPhotoUpload::create([
            'user_id' => $u->id,
            'order_id' => $o->id,
            'field_name' => $fieldName,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);
        StorageQuotaService::lupakanCache();

        return response()->json(['data' => ['status' => 'ditampung', 'sementara' => ['id' => $temp->id, 'field_name' => $fieldName, 'url' => Presenter::url($path)]]], 201);
    }

    /** Hapus foto tampungan (belum laporan). */
    public function hapusFotoSementara(Request $request, int $order, int $id): JsonResponse
    {
        $o = $this->cariOrder($request, $order);
        $temp = TemporaryPhotoUpload::query()->where('user_id', $request->user()->id)->where('order_id', $o->id)->findOrFail($id);
        $temp->deleteFile();
        $temp->forceDelete();
        StorageQuotaService::lupakanCache();

        return response()->json(['data' => ['ok' => true]]);
    }

    /** Hapus satu foto laporan; slotnya muncul lagi sebagai "foto wajib kurang". */
    public function hapusFoto(Request $request, int $order, int $foto): JsonResponse
    {
        $o = $this->cariOrder($request, $order, tulis: true);
        $f = WorkReportPhoto::query()->whereHas('workReport', fn ($q) => $q->where('order_id', $o->id))->findOrFail($foto);

        if (filled($f->path)) {
            Storage::disk('public')->delete($f->path);
        }
        $f->delete();
        StorageQuotaService::lupakanCache();

        return response()->json(['data' => ['ok' => true]]);
    }

    /** Ganti file foto laporan yang sudah tersimpan (blur/salah sudut). */
    public function gantiFoto(Request $request, int $order, int $foto): JsonResponse
    {
        $request->validate(['file' => ['required', 'image', 'max:5120']]);

        $o = $this->cariOrder($request, $order, tulis: true);
        $f = WorkReportPhoto::query()->whereHas('workReport', fn ($q) => $q->where('order_id', $o->id))->findOrFail($foto);
        $file = $request->file('file');

        app(StorageQuotaService::class)->pastikanCukup((int) $file->getSize());
        $baru = $file->store('work-reports', 'public');

        if (filled($f->path)) {
            Storage::disk('public')->delete($f->path);
        }
        $f->update(['path' => $baru]);
        StorageQuotaService::lupakanCache();

        return response()->json(['data' => Presenter::foto($f->fresh())]);
    }

    /**
     * Finalisasi laporan. Body JSON: `catatan` (wajib), `materials[]` {stock_item_id,jumlah},
     * `butuh_followup`, `is_klaim`. Foto & keterangan unit yang sudah dikirim ikut dipakai;
     * kekurangan foto wajib tidak memblokir (dilengkapi lewat /foto, menahan berangkat berikutnya).
     */
    public function kirim(Request $request, int $order): JsonResponse
    {
        $data = $request->validate([
            'catatan' => ['required', 'string', 'min:3'],
            'materials' => ['nullable', 'array'],
            'materials.*.stock_item_id' => ['required', 'integer', 'exists:stock_items,id'],
            'materials.*.jumlah' => ['required', 'integer', 'min:1'],
            'butuh_followup' => ['nullable', 'boolean'],
            'is_klaim' => ['nullable', 'boolean'],
        ]);

        $o = $this->cariOrder($request, $order, tulis: true);
        $u = $request->user();

        $temp = TemporaryPhotoUpload::query()
            ->where('user_id', $u->id)->where('order_id', $o->id)->get()
            ->filter(fn (TemporaryPhotoUpload $p): bool => filled($p->file_path) && Storage::disk('public')->exists($p->file_path))
            ->keyBy('field_name');

        $salin = function (TemporaryPhotoUpload $p): string {
            $tujuan = 'work-reports/'.basename($p->file_path);
            Storage::disk('public')->copy($p->file_path, $tujuan);

            return $tujuan;
        };

        $fotoKategori = [];
        $fotoUnit = [];
        $fotoSebelum = null;
        $fotoSesudah = null;

        foreach ($temp as $nama => $p) {
            $bagian = explode('.', $nama);

            if ($nama === 'fotoSebelum') {
                $fotoSebelum = $salin($p);
            } elseif ($nama === 'fotoSesudah') {
                $fotoSesudah = $salin($p);
            } elseif ($bagian[0] === 'fotoKategori' && count($bagian) === 3) {
                $fotoKategori[] = ['order_item_id' => (int) $bagian[1], 'slot' => $bagian[2], 'path' => $salin($p)];
            } elseif ($bagian[0] === 'fotoUnit' && count($bagian) === 3) {
                $fotoUnit[] = ['order_unit_report_id' => (int) $bagian[1], 'slot' => $bagian[2], 'path' => $salin($p)];
            }
        }

        $salinan = collect([$fotoSebelum, $fotoSesudah])->filter()
            ->merge(collect($fotoKategori)->pluck('path'))
            ->merge(collect($fotoUnit)->pluck('path'))
            ->all();

        try {
            $svc = app(TeknisiService::class);
            $svc->submitLaporan($o, $u, [
                'catatan' => $data['catatan'],
                'materials' => collect($data['materials'] ?? [])
                    ->map(fn (array $m): array => ['stock_item_id' => (int) $m['stock_item_id'], 'jumlah' => (int) $m['jumlah']])
                    ->values()->all(),
                'butuh_followup' => (bool) ($data['butuh_followup'] ?? false),
                'is_klaim' => (bool) ($data['is_klaim'] ?? false),
                'foto_sebelum' => $fotoSebelum,
                'foto_sesudah' => $fotoSesudah,
                'foto_kategori' => $fotoKategori,
                'foto_unit' => $fotoUnit,
            ]);
        } catch (\Throwable $e) {
            foreach ($salinan as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        $temp->each(function (TemporaryPhotoUpload $p): void {
            $p->deleteFile();
            $p->delete();
        });
        StorageQuotaService::lupakanCache();

        $segar = Order::with('orderItems')->findOrFail($o->id);
        $kurang = collect($svc->fotoWajibKurang($segar));

        return response()->json(['data' => [
            'order' => Presenter::orderDetail($segar->fresh(), $u),
            'foto_wajib_kurang' => $kurang->pluck('label')->values(),
            'lengkap' => $kurang->isEmpty(),
        ]]);
    }
}
