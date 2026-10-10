<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Models\User;
use App\Models\WorkReportPhoto;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Fase 4 (dev-plan/21 §5): keterangan per unit (`order_unit_reports`).
 *
 * Aturan "jangan menggantung": baris unit HANYA dibuat untuk order yang
 * sedang dikerjakan (atau yang sudah punya baris). Order tanpa baris unit
 * (data lama / sudah selesai sebelum fitur ini) dianggap tidak menuntut
 * keterangan — lihat keteranganKurang().
 */
class UnitReportService
{
    use RestrictsByRole;

    private const ADMIN_ROLES = [RoleName::Owner, RoleName::Admin, RoleName::Finance];

    public function __construct(private readonly PhotoReportTemplateService $templates) {}

    /**
     * Pastikan tiap unit order punya satu baris (prefill). Idempotent: hanya
     * menambah baris yang kurang — jumlah baris per item = `jumlah` item,
     * nomor unit berlanjut dari nomor terbesar. Baris tidak pernah dihapus
     * (item yang dibatalkan hanya diabaikan oleh pembaca).
     *
     * @return Collection<int, OrderUnitReport> seluruh baris unit order ini, urut unit_no
     */
    public function siapkan(Order $order): Collection
    {
        $items = OrderItem::query()
            ->where('order_id', $order->id)
            ->where('dibatalkan', false)
            ->where('penyesuaian', false)
            ->with('acUnit.customerAddress')
            ->orderBy('id')
            ->get();

        $ada = OrderUnitReport::query()->where('order_id', $order->id)->get();
        $perItem = $ada->countBy('order_item_id');
        $berikutnya = (int) $ada->max('unit_no');
        $lokasiOrder = $order->customer_address_id !== null
            ? CustomerAddress::query()->find($order->customer_address_id)?->nama_lokasi
            : null;

        foreach ($items as $item) {
            $kurang = max(1, (int) $item->jumlah) - (int) $perItem->get($item->id, 0);

            for ($i = 0; $i < $kurang; $i++) {
                OrderUnitReport::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'unit_no' => ++$berikutnya,
                    'customer_ac_unit_id' => $item->customer_ac_unit_id,
                    'lokasi_label' => $lokasiOrder ?: $item->acUnit?->customerAddress?->nama_lokasi,
                    'posisi' => $item->acUnit?->kode_ruangan,
                    'jenis_pekerjaan' => $this->namaPekerjaan($item),
                    'bagian' => 'indoor',
                ]);
            }
        }

        return $this->unitAktif($order);
    }

    /**
     * Baris unit milik item yang masih aktif, urut unit_no.
     *
     * @return Collection<int, OrderUnitReport>
     */
    public function unitAktif(Order $order): Collection
    {
        return OrderUnitReport::query()
            ->where('order_id', $order->id)
            ->whereHas('orderItem', fn ($q) => $q->where('dibatalkan', false))
            ->with('orderItem')
            ->orderBy('unit_no')
            ->get();
    }

    public function punyaDataUnit(Order $order): bool
    {
        return OrderUnitReport::query()->where('order_id', $order->id)->exists();
    }

    /**
     * Boleh disiapkan otomatis (dipanggil saat teknisi membuka order)?
     */
    public function bolehDisiapkanOtomatis(Order $order): bool
    {
        if ($order->sudahDitutup()) {
            return false;
        }

        return $order->status === OrderStatus::Dikerjakan || $this->punyaDataUnit($order);
    }

    /**
     * Field-set efektif unit untuk TAMPILAN form: indoor_lengkap | outdoor | bebas.
     */
    public function fieldSetTampil(OrderItem $item): string
    {
        return $this->templates->fieldSetUntuk($item->kategori, hanyaWajib: false);
    }

    /**
     * Apakah unit pada item ini WAJIB diberi keterangan (ada slot aktif+wajib
     * ber-field_set bukan `bebas`)?
     */
    public function butuhKeterangan(OrderItem $item): bool
    {
        return $this->templates->fieldSetUntuk($item->kategori, hanyaWajib: true) !== 'bebas';
    }

    public function suhuWajib(OrderItem $item): bool
    {
        return $this->templates->fieldSetUntuk($item->kategori, hanyaWajib: true) === 'indoor_lengkap';
    }

    /**
     * Keterangan unit sudah cukup lengkap sesuai aturan item-nya?
     */
    public function lengkap(OrderUnitReport $unit, OrderItem $item): bool
    {
        if (blank($unit->kondisi)) {
            return false;
        }

        if ($unit->kondisi === OrderUnitReport::KONDISI_TIDAK_NORMAL && blank($unit->catatan_kondisi)) {
            return false;
        }

        if ($this->suhuWajib($item) && $unit->suhu === null) {
            return false;
        }

        return true;
    }

    /**
     * Keterangan unit yang masih kurang — dipakai TeknisiService::fotoWajibKurang().
     * Kosong untuk order tanpa data unit (lama) dan untuk unit yang tidak
     * menuntut keterangan menurut template.
     *
     * @return array<int, array{jenis: string, order_item: OrderItem, kode_slot: string, label: string, unit_no: int, unit_report_id: int}>
     */
    public function keteranganKurang(Order $order): array
    {
        $unit = $this->unitAktif($order);
        $kurang = [];

        foreach ($unit as $u) {
            $item = $u->orderItem;

            if (! $this->butuhKeterangan($item) || $this->lengkap($u, $item)) {
                continue;
            }

            $kurang[] = [
                'jenis' => 'keterangan',
                'order_item' => $item,
                'kode_slot' => 'keterangan_unit_'.$u->unit_no,
                'label' => "Keterangan Unit {$u->unit_no}",
                'unit_no' => $u->unit_no,
                'unit_report_id' => $u->id,
            ];
        }

        return $kurang;
    }

    /**
     * Aturan validasi simpan keterangan satu unit. `$prefix` dipakai Livewire
     * (mis. `unitForm.12.`) agar pesan error menempel di field yang tepat.
     *
     * @return array<string, array<int, mixed>>
     */
    public function aturan(OrderItem $item, string $prefix = ''): array
    {
        $tampil = $this->fieldSetTampil($item);

        return [
            $prefix.'lokasi_label' => ['nullable', 'string', 'max:255'],
            $prefix.'posisi' => ['nullable', 'string', 'max:255'],
            $prefix.'jenis_pekerjaan' => ['nullable', 'string', 'max:255'],
            $prefix.'bagian' => ['required', 'in:indoor,outdoor'],
            $prefix.'kondisi' => ['required', 'in:normal,tidak_normal'],
            $prefix.'catatan_kondisi' => [
                'nullable', 'string', 'max:1000',
                'required_if:'.$prefix.'kondisi,tidak_normal',
            ],
            $prefix.'suhu' => $tampil === 'indoor_lengkap'
                ? [$this->suhuWajib($item) ? 'required' : 'nullable', 'numeric', 'between:-50,100']
                : ['nullable'],
            $prefix.'rpm' => $tampil === 'indoor_lengkap'
                ? ['nullable', 'numeric', 'min:0', 'max:99999']
                : ['nullable'],
        ];
    }

    /**
     * Simpan keterangan satu unit. Teknisi: harus anggota tim order, order
     * belum ditutup/batal. Owner/Admin/Finance: boleh kapan pun (menambal).
     * Validasi di sisi server; `catatan_kondisi` wajib bila tidak_normal,
     * `suhu` wajib untuk field-set indoor_lengkap. Suhu/RPM yang tidak
     * relevan (outdoor/bebas) dibuang.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function simpan(OrderUnitReport $unit, array $data, User $oleh): OrderUnitReport
    {
        $order = Order::query()->findOrFail($unit->order_id);
        $this->pastikanBolehMengisi($order, $oleh);

        $item = $unit->orderItem()->firstOrFail();
        if ($item->dibatalkan()) {
            throw new BusinessRuleException('Layanan unit ini sudah dibatalkan.');
        }

        $bersih = Validator::make($data, $this->aturan($item))->validate();

        $tampil = $this->fieldSetTampil($item);
        if ($tampil !== 'indoor_lengkap') {
            $bersih['suhu'] = null;
            $bersih['rpm'] = null;
        }

        foreach (['suhu', 'rpm'] as $kolom) {
            if (array_key_exists($kolom, $bersih) && $bersih[$kolom] === '') {
                $bersih[$kolom] = null;
            }
        }

        if ($bersih['kondisi'] === OrderUnitReport::KONDISI_NORMAL && ! filled($bersih['catatan_kondisi'] ?? null)) {
            $bersih['catatan_kondisi'] = null;
        }

        $unit->fill(collect($bersih)->only([
            'lokasi_label', 'posisi', 'jenis_pekerjaan', 'suhu', 'rpm', 'kondisi', 'catatan_kondisi', 'bagian',
        ])->all())->save();

        return $unit->fresh();
    }

    /**
     * Simpan hanya data deskriptif (lokasi/posisi/pekerjaan/bagian) tanpa
     * menuntut kondisi — dipakai admin menambal sebagian data unit.
     *
     * @param  array<string, mixed>  $data
     */
    public function simpanDeskriptif(OrderUnitReport $unit, array $data, User $oleh): OrderUnitReport
    {
        $this->pastikanBolehMengisi(Order::query()->findOrFail($unit->order_id), $oleh);

        $bersih = Validator::make($data, [
            'lokasi_label' => ['nullable', 'string', 'max:255'],
            'posisi' => ['nullable', 'string', 'max:255'],
            'jenis_pekerjaan' => ['nullable', 'string', 'max:255'],
            'bagian' => ['required', 'in:indoor,outdoor'],
        ])->validate();

        $unit->fill($bersih)->save();

        return $unit->fresh();
    }

    /**
     * Foto susulan oleh Owner/Admin/Finance untuk slot sebuah item — ditautkan
     * ke laporan terakhir order (dan unit pertama item bila ada).
     */
    public function tambahFotoSusulan(Order $order, User $oleh, int $orderItemId, string $slot, string $path): WorkReportPhoto
    {
        $this->assertRole($oleh, self::ADMIN_ROLES);

        $item = $order->orderItems()->whereKey($orderItemId)->first()
            ?? throw new BusinessRuleException('Baris layanan tidak ditemukan pada order ini.');

        $slots = array_keys(\App\Support\FotoLaporanSlot::untuk($item->kategori));
        $urutan = array_search($slot, $slots, true);
        if ($urutan === false) {
            throw new BusinessRuleException("Slot foto '{$slot}' tidak valid untuk layanan {$item->nama_layanan}.");
        }

        $laporan = $order->workReports()->latest('id')->first()
            ?? throw new BusinessRuleException('Belum ada laporan pengerjaan pada order ini — foto susulan belum bisa dilampirkan.');

        if (trim($path) === '') {
            throw new BusinessRuleException('Path foto tidak valid.');
        }

        return DB::transaction(fn () => WorkReportPhoto::create([
            'work_report_id' => $laporan->id,
            'order_item_id' => $item->id,
            'unit_no' => 1,
            'order_unit_report_id' => $this->unitPertamaItem($item->id)?->id,
            'slot' => $slot,
            'path' => $path,
            'urutan' => $urutan,
        ]));
    }

    /**
     * Unit pertama (nomor terkecil) sebuah baris layanan, bila datanya ada.
     */
    public function unitPertamaItem(int $orderItemId): ?OrderUnitReport
    {
        return OrderUnitReport::query()
            ->where('order_item_id', $orderItemId)
            ->orderBy('unit_no')
            ->first();
    }

    private function pastikanBolehMengisi(Order $order, User $oleh): void
    {
        if ($oleh->hasAnyRole(array_map(fn (RoleName $r) => $r->value, self::ADMIN_ROLES))) {
            return;
        }

        $this->assertRole($oleh, [RoleName::Teknisi]);

        if (! $order->diassignkanKe($oleh)) {
            throw new AuthorizationException('Order ini bukan tugas teknisi Anda.');
        }

        if ($order->sudahDitutup() || $order->status === OrderStatus::Batal) {
            throw new BusinessRuleException('Order sudah ditutup — keterangan unit tidak bisa diubah teknisi.');
        }
    }

    private function namaPekerjaan(OrderItem $item): ?string
    {
        $nama = (string) $item->nama_layanan;

        return str_contains($nama, '_') ? str($nama)->headline()->toString() : ($nama !== '' ? $nama : null);
    }
}
