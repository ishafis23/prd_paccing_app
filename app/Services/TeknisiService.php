<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockItem;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportMaterial;
use App\Models\WorkReportPhoto;
use App\Support\FotoLaporanSlot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class TeknisiService
{
    use RestrictsByRole;

    public function __construct(private readonly StockService $stockService) {}

    /**
     * Slider "mulai berangkat ke lokasi" (gaya ojek online).
     * Order: terjadwal -> menuju_lokasi.
     */
    public function berangkat(Order $order, User $teknisi): Order
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->status !== OrderStatus::Terjadwal) {
            throw new BusinessRuleException('Order harus berstatus terjadwal sebelum berangkat.');
        }

        // dev-plan/17, B63 (revisi 18 Sep): tidak boleh berangkat ke order
        // berikutnya selama masih ada order lain (belum ditutup) yang
        // laporannya kurang foto wajib — dorong teknisi melengkapi dulu.
        $tertunda = $this->orderDenganFotoBelumLengkap($teknisi);
        if ($tertunda !== null) {
            $daftar = collect($this->fotoWajibKurang($tertunda))->pluck('label')->implode(', ');
            throw new BusinessRuleException(
                "Lengkapi dulu foto wajib pada laporan order #{$tertunda->id} ({$tertunda->customer?->nama}) sebelum berangkat ke order berikutnya: {$daftar}."
            );
        }

        $order->status = OrderStatus::MenujuLokasi;
        $order->save();

        return $order->fresh();
    }

    /**
     * Order lain milik teknisi ini (belum ditutup — B32) yang laporannya
     * sudah disubmit tapi masih kurang foto wajib (dev-plan/17, B63).
     */
    private function orderDenganFotoBelumLengkap(User $teknisi): ?Order
    {
        $orders = Order::query()
            ->untukTeknisi($teknisi->id)
            ->whereIn('status', [OrderStatus::Selesai, OrderStatus::ButuhFollowup])
            ->whereNull('ditutup_pada')
            ->with('orderItems')
            ->get();

        foreach ($orders as $order) {
            if ($this->fotoWajibKurang($order) !== []) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Simpan posisi GPS terakhir teknisi (dipanggil berkala dari HP selagi
     * order menuju_lokasi/dikerjakan) agar admin tahu posisinya saat ini.
     */
    public function updateLokasi(Order $order, User $teknisi, float $lat, float $lng): void
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if (! in_array($order->status, [OrderStatus::MenujuLokasi, OrderStatus::Dikerjakan], true)) {
            throw new BusinessRuleException('Lacak lokasi hanya aktif saat menuju lokasi atau mengerjakan order.');
        }

        $teknisi->update([
            'last_latitude' => $lat,
            'last_longitude' => $lng,
            'last_location_at' => now(),
        ]);
    }

    /**
     * Check-in di lokasi customer: mencatat attendance dan mengunci status
     * order menjadi `dikerjakan`.
     */
    public function checkIn(Order $order, User $teknisi, ?string $lokasi = null): Attendance
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->status !== OrderStatus::MenujuLokasi) {
            throw new BusinessRuleException('Check-in hanya bisa setelah status menuju_lokasi.');
        }

        $attendance = Attendance::create([
            'user_id' => $teknisi->id,
            'order_id' => $order->id,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => now(),
            'jam_keluar' => null,
            'lokasi' => $lokasi,
            'status' => AttendanceStatus::Hadir,
        ]);

        $order->status = OrderStatus::Dikerjakan;
        $order->save();

        return $attendance;
    }

    /**
     * Submit laporan pengerjaan + material terpakai.
     * Efek otomatis (PRD alur 5): stok keluar per material, status order
     * menjadi `selesai` atau `butuh_followup`, check-out attendance.
     *
     * @param  array{catatan: string, materials: array<int, array{stock_item_id: int, jumlah: int}>, foto_sebelum?: ?string, foto_sesudah?: ?string, foto_kategori?: array<int, array{order_item_id: int, slot: string, path: string}>, butuh_followup?: bool}  $payload
     */
    public function submitLaporan(Order $order, User $teknisi, array $payload): WorkReport
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if (! in_array($order->status, [OrderStatus::Dikerjakan, OrderStatus::ButuhFollowup], true)) {
            throw new BusinessRuleException('Laporan hanya bisa disubmit saat order dikerjakan/ditindaklanjuti.');
        }

        $catatan = trim($payload['catatan'] ?? '');
        if ($catatan === '') {
            throw new BusinessRuleException('Catatan pengerjaan wajib diisi.');
        }

        // Validasi material SELURUHNYA dulu (sebelum ada satu pun tulisan)
        // supaya tidak ada paruh-tulis saat baris belakangan invalid.
        $materials = $this->validasiMaterial($payload['materials'] ?? []);

        // dev-plan/13 §3: foto per kategori order_item (menggantikan pola
        // foto_sebelum/foto_sesudah generik utk laporan baru).
        $fotoKategori = $this->validasiFotoKategori($order, $payload['foto_kategori'] ?? []);

        // dev-plan/17, B63 (direvisi 18 Sep): foto wajib yang kurang TIDAK
        // lagi memblokir submit di sini (supaya pembayaran tidak ikut
        // tertahan) — sebagai gantinya, teknisi ditahan sebelum berangkat
        // ke order berikutnya lewat berangkat() sampai laporan ini
        // dilengkapi (lihat fotoWajibKurang()/lengkapiFotoWajib()).

        // B25 (lapis kedua): kalau penyimpanan sudah penuh dan laporan
        // membawa foto, tolak sejak awal.
        $bawaFoto = filled($payload['foto_sebelum'] ?? null) || filled($payload['foto_sesudah'] ?? null) || $fotoKategori !== [];
        if ($bawaFoto) {
            $quota = app(StorageQuotaService::class);
            if ($quota->pakaiBytes(segar: true) >= $quota->kuotaBytes()) {
                throw new BusinessRuleException('Penyimpanan foto penuh — foto tidak bisa dilampirkan. Hubungi admin untuk menaikkan kuota.');
            }
        }

        return DB::transaction(function () use ($order, $teknisi, $payload, $catatan, $materials, $fotoKategori): WorkReport {
            $attendance = $order->attendances()
                ->where('user_id', $teknisi->id)
                ->whereNull('jam_keluar')
                ->latest('id')
                ->first();

            $waktuMulai = $attendance?->jam_masuk ?? now();

            $report = WorkReport::create([
                'order_id' => $order->id,
                'teknisi_id' => $teknisi->id,
                'catatan_pengerjaan' => $catatan,
                'foto_sebelum' => $payload['foto_sebelum'] ?? null,
                'foto_sesudah' => $payload['foto_sesudah'] ?? null,
                'waktu_mulai' => $waktuMulai,
                'waktu_selesai' => now(),
            ]);

            $this->catatMaterial($report, $teknisi, $materials);
            $this->catatFotoKategori($report, $fotoKategori);

            $order->status = ! empty($payload['butuh_followup'])
                ? OrderStatus::ButuhFollowup
                : OrderStatus::Selesai;

            // dev-plan/15 B50: teknisi juga bisa menandai klaim/garansi saat laporan.
            if (array_key_exists('is_klaim', $payload)) {
                $order->is_klaim = (bool) $payload['is_klaim'];
            }

            $order->save();

            // Resi publik tersedia begitu order selesai (B14a).
            if ($order->status === OrderStatus::Selesai) {
                $order->pastikanResiToken();
            }

            // B21: attendance terbuka seluruh anggota yang hadir ikut ditutup,
            // supaya setiap teknisi yang check-in tercatat waktu selesainya.
            $anggotaIds = $this->anggotaTimIds($order);
            if ($anggotaIds !== []) {
                $order->attendances()
                    ->whereIn('user_id', $anggotaIds)
                    ->whereNull('jam_keluar')
                    ->update(['jam_keluar' => now()]);
            }

            return $report->fresh();
        });
    }

    /**
     * @param  array<int, array{stock_item_id: int, jumlah: int}>  $materials
     * @return array<int, array{item: StockItem, jumlah: int}>
     */
    private function validasiMaterial(array $materials): array
    {
        $hasil = [];

        foreach ($materials as $baris) {
            $item = StockItem::find($baris['stock_item_id'] ?? null)
                ?? throw new BusinessRuleException('Material tidak ditemukan.');

            $jumlah = (int) ($baris['jumlah'] ?? 0);
            if ($jumlah <= 0) {
                throw new BusinessRuleException('Jumlah material harus lebih dari 0.');
            }

            $hasil[] = ['item' => $item, 'jumlah' => $jumlah];
        }

        return $hasil;
    }

    /**
     * @param  array<int, array{item: StockItem, jumlah: int}>  $materials
     */
    private function catatMaterial(WorkReport $report, User $teknisi, array $materials): void
    {
        foreach ($materials as $baris) {
            $item = $baris['item'];
            $jumlah = $baris['jumlah'];

            WorkReportMaterial::create([
                'work_report_id' => $report->id,
                'stock_item_id' => $item->id,
                'jumlah' => $jumlah,
            ]);

            // Stok keluar otomatis; keputusan B4: boleh minus.
            $this->stockService->keluar(
                $item,
                $jumlah,
                $teknisi,
                'work_report:'.$report->id,
                'Material laporan order #'.$report->order_id
            );
        }
    }

    /**
     * dev-plan/13 §3: validasi seluruh baris foto_kategori SEBELUM ada
     * satu pun tulisan — order_item harus milik order ini & slot harus
     * sesuai template kategori order_item tsb (App\Support\FotoLaporanSlot).
     *
     * @param  array<int, array{order_item_id: int, slot: string, path: string}>  $fotoKategori
     * @return array<int, array{order_item: OrderItem, slot: string, path: string, urutan: int}>
     */
    private function validasiFotoKategori(Order $order, array $fotoKategori): array
    {
        $items = $order->orderItems->keyBy('id');
        $hasil = [];

        foreach ($fotoKategori as $baris) {
            $item = $items->get((int) ($baris['order_item_id'] ?? 0))
                ?? throw new BusinessRuleException('Baris layanan untuk foto tidak ditemukan pada order ini.');

            $slot = trim((string) ($baris['slot'] ?? ''));
            $template = FotoLaporanSlot::untuk($item->kategori);
            $urutan = array_search($slot, array_keys($template), true);
            if ($urutan === false) {
                throw new BusinessRuleException("Slot foto '{$slot}' tidak valid untuk kategori {$item->nama_layanan}.");
            }

            $path = trim((string) ($baris['path'] ?? ''));
            if ($path === '') {
                throw new BusinessRuleException('Path foto tidak valid.');
            }

            $hasil[] = ['order_item' => $item, 'slot' => $slot, 'path' => $path, 'urutan' => $urutan];
        }

        return $hasil;
    }

    /**
     * dev-plan/17, B63 (direvisi 18 Sep): daftar slot wajib (aktif, per
     * kategori order_item) yang BELUM ada fotonya tersimpan sama sekali
     * (dicek ke `work_report_photos`, lintas seluruh laporan order ini —
     * bukan cuma laporan yang barusan disubmit). Balikin kosong kalau
     * sudah lengkap. Dipakai `berangkat()` (gate) & UI "Lengkapi Foto".
     *
     * @return array<int, array{order_item: OrderItem, kode_slot: string, label: string}>
     */
    public function fotoWajibKurang(Order $order): array
    {
        $terisi = WorkReportPhoto::query()
            ->whereIn('order_item_id', $order->orderItems->pluck('id'))
            ->get()
            ->map(fn (WorkReportPhoto $p): string => $p->order_item_id.'|'.$p->slot)
            ->flip();

        $kurang = [];
        foreach ($order->orderItems as $item) {
            foreach (FotoLaporanSlot::wajibUntuk($item->kategori) as $kodeSlot => $label) {
                if (! $terisi->has($item->id.'|'.$kodeSlot)) {
                    $kurang[] = ['order_item' => $item, 'kode_slot' => $kodeSlot, 'label' => $label];
                }
            }
        }

        return $kurang;
    }

    /**
     * Lengkapi foto wajib yang kurang pada order yang laporannya SUDAH
     * disubmit (Selesai/ButuhFollowup) — dev-plan/17, B63 (revisi). Foto
     * baru ditautkan ke laporan TERAKHIR order ini.
     *
     * @param  array<int, array{order_item_id: int, slot: string, path: string}>  $fotoKategori
     */
    public function lengkapiFotoWajib(Order $order, User $teknisi, array $fotoKategori): WorkReport
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if (! in_array($order->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)) {
            throw new BusinessRuleException('Laporan belum disubmit — lengkapi lewat form laporan biasa.');
        }

        $report = $order->workReports()->latest('id')->first();
        if ($report === null) {
            throw new BusinessRuleException('Belum ada laporan tersimpan utk order ini.');
        }

        $baris = $this->validasiFotoKategori($order, $fotoKategori);

        if ($baris !== []) {
            $quota = app(StorageQuotaService::class);
            if ($quota->pakaiBytes(segar: true) >= $quota->kuotaBytes()) {
                throw new BusinessRuleException('Penyimpanan foto penuh — foto tidak bisa dilampirkan. Hubungi admin untuk menaikkan kuota.');
            }
        }

        $this->catatFotoKategori($report, $baris);

        return $report->fresh('photos');
    }

    /**
     * @param  array<int, array{order_item: OrderItem, slot: string, path: string, urutan: int}>  $fotoKategori
     */
    private function catatFotoKategori(WorkReport $report, array $fotoKategori): void
    {
        foreach ($fotoKategori as $baris) {
            WorkReportPhoto::create([
                'work_report_id' => $report->id,
                'order_item_id' => $baris['order_item']->id,
                'slot' => $baris['slot'],
                'path' => $baris['path'],
                'urutan' => $baris['urutan'],
            ]);
        }
    }

    /**
     * Tombol "Terkendala / Gagal": teknisi lapor order tidak bisa
     * dilanjutkan di lapangan (mis. customer tidak jadi / tidak ada di
     * lokasi). Menutup attendance terbuka (kalau ada) dan menunggu admin
     * menjadwalkan ulang lewat OrderService::reschedule().
     */
    public function tandaiKendala(Order $order, User $teknisi, string $alasan): Order
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if (! in_array($order->status, [OrderStatus::Terjadwal, OrderStatus::MenujuLokasi, OrderStatus::Dikerjakan], true)) {
            throw new BusinessRuleException('Order harus berstatus terjadwal/menuju lokasi/dikerjakan untuk ditandai terkendala.');
        }

        $alasan = trim($alasan);
        if ($alasan === '') {
            throw new BusinessRuleException('Alasan kendala wajib diisi.');
        }

        return DB::transaction(function () use ($order, $alasan): Order {
            $order->status = OrderStatus::Terkendala;
            $order->alasan_kendala = $alasan;
            $order->catatan_admin = trim(($order->catatan_admin ?? '')."\n[KENDALA] ".$alasan);
            $order->save();

            // B21: attendance terbuka (kalau order sempat dikerjakan) ikut ditutup.
            $anggotaIds = $this->anggotaTimIds($order);
            if ($anggotaIds !== []) {
                $order->attendances()
                    ->whereIn('user_id', $anggotaIds)
                    ->whereNull('jam_keluar')
                    ->update(['jam_keluar' => now()]);
            }

            return $order->fresh();
        });
    }

    /**
     * Tombol "Ada Perbaikan" (dev-plan/13 §2): teknisi lapor kebutuhan
     * sparepart/perbaikan tambahan yg sudah dibicarakan dgn customer.
     * Beda dari tandaiKendala — order TETAP `dikerjakan`, cuma menambah
     * flag "menunggu konfirmasi" supaya admin tahu tanpa mengganggu
     * progres teknisi di lokasi.
     */
    public function laporPerbaikan(Order $order, User $teknisi, string $catatan, ?float $estimasiHarga = null): Order
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->status !== OrderStatus::Dikerjakan) {
            throw new BusinessRuleException('Lapor perbaikan hanya bisa saat order sedang dikerjakan.');
        }

        if ($order->perbaikan_menunggu_konfirmasi) {
            throw new BusinessRuleException('Masih ada laporan perbaikan yang menunggu konfirmasi admin.');
        }

        $catatan = trim($catatan);
        if ($catatan === '') {
            throw new BusinessRuleException('Catatan perbaikan wajib diisi.');
        }

        if ($estimasiHarga !== null && $estimasiHarga < 0) {
            throw new BusinessRuleException('Estimasi harga tidak valid.');
        }

        $order->perbaikan_menunggu_konfirmasi = true;
        $order->perbaikan_catatan = $catatan;
        $order->perbaikan_estimasi_harga = $estimasiHarga;
        $order->perbaikan_dilaporkan_oleh = $teknisi->id;
        $order->perbaikan_dilaporkan_pada = now();
        $order->save();

        return $order->fresh();
    }

    /**
     * Teknisi menutup order yang sudah selesai & metode sudah ditandai (B32).
     * Slider "Selesaikan Order": metode pembayaran terkunci (tak bisa diubah)
     * dan waktu penutupan tercatat di `orders.ditutup_pada`.
     */
    public function tutupOrder(Order $order, User $teknisi): Order
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->status !== OrderStatus::Selesai) {
            throw new BusinessRuleException('Order harus berstatus selesai sebelum ditutup.');
        }

        if ($order->sudahDitutup()) {
            throw new BusinessRuleException('Order ini sudah ditutup.');
        }

        $sudahLunas = $order->payments()
            ->where('status', PaymentStatus::Lunas->value)
            ->exists();

        if ($sudahLunas) {
            throw new BusinessRuleException('Order sudah lunas — tidak perlu ditutup teknisi.');
        }

        if ($order->metode_dipilih === null) {
            throw new BusinessRuleException('Tandai dulu metode pembayaran pilihan customer sebelum menutup order.');
        }

        // B-bukti-bayar: wajib utk rumahan (termasuk data lama tanpa
        // jenis_pelanggan), opsional kalau customer-nya instansi.
        if ($order->jenis_pelanggan !== CustomerJenis::Company && blank($order->bukti_pembayaran)) {
            throw new BusinessRuleException('Upload bukti pembayaran dulu sebelum menutup order (wajib untuk customer rumahan).');
        }

        $order->ditutup_pada = now();
        $order->save();

        return $order->fresh();
    }

    /**
     * Teknisi menandai metode pembayaran yang dipilih customer (B13b) —
     * info tambahan utk Admin; `null` untuk menghapus tanda.
     * Pencatatan resmi pembayaran tetap lewat PaymentService (Admin/Finance).
     */
    public function catatMetodeDipilih(Order $order, User $teknisi, ?PaymentMethod $metode): Order
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->status === OrderStatus::Batal) {
            throw new BusinessRuleException('Order batal tidak bisa diubah.');
        }

        // B32: setelah order ditutup teknisi, metode terkunci.
        if ($order->sudahDitutup()) {
            throw new BusinessRuleException('Metode pembayaran terkunci — order sudah ditutup teknisi.');
        }

        $sudahLunas = $order->payments()
            ->where('status', PaymentStatus::Lunas->value)
            ->exists();

        if ($sudahLunas) {
            throw new BusinessRuleException('Order sudah lunas; metode pembayaran tidak bisa diubah.');
        }

        $order->metode_dipilih = $metode?->value;
        $order->save();

        return $order->fresh();
    }

    /**
     * Upload/ganti bukti pembayaran (foto) — wajib utk customer rumahan
     * sebelum order bisa ditutup (lihat tutupOrder), opsional utk instansi.
     */
    public function uploadBuktiPembayaran(Order $order, User $teknisi, string $path): Order
    {
        $this->pastikanAnggota($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->sudahDitutup()) {
            throw new BusinessRuleException('Order sudah ditutup — bukti pembayaran tidak bisa diubah.');
        }

        $order->bukti_pembayaran = $path;
        $order->save();

        return $order->fresh();
    }

    private function pastikanAnggota(Order $order, User $teknisi): void
    {
        if (! $order->diassignkanKe($teknisi)) {
            throw new AuthorizationException('Order ini bukan tugas teknisi Anda.');
        }
    }

    /**
     * ID seluruh anggota tim pengerjaan (PIC + baris order_technicians).
     * Legacy order tanpa baris tim tetap terwakili oleh PIC.
     *
     * @return array<int, int>
     */
    private function anggotaTimIds(Order $order): array
    {
        return $order->orderTechnicians()
            ->pluck('teknisi_id')
            ->push($order->teknisi_id)
            ->unique()
            ->filter()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
