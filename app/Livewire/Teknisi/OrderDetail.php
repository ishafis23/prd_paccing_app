<?php

namespace App\Livewire\Teknisi;

use App\Enums\IncomeCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Models\StockItem;
use App\Models\TemporaryPhotoUpload;
use App\Models\WorkReport;
use App\Models\WorkReportPhoto;
use App\Services\AttendanceService;
use App\Services\OrderService;
use App\Services\PaymentChannelService;
use App\Services\StorageQuotaService;
use App\Services\TeknisiService;
use App\Services\UnitReportService;
use App\Support\FotoLaporanSlot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class OrderDetail extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $orderId;

    public array $materials = [];

    public string $alasanKendala = '';

    public string $catatanPerbaikan = '';

    public string $estimasiHargaPerbaikan = '';

    public string $catatan = '';

    public bool $butuhFollowup = false;

    public bool $isKlaim = false;

    public $fotoTitikPertama;

    public $fotoSebelum;

    public $fotoSesudah;

    /** Foto pengganti utk "Perbarui Foto Laporan" (setelah laporan tersubmit). */
    public $fotoSebelumBaru;

    public $fotoSesudahBaru;

    /** @var array<int, array<string, mixed>> [order_item_id => [slot => UploadedFile]] */
    public array $fotoKategori = [];

    /** @var array<int, array<string, mixed>> [order_item_id => [slot => UploadedFile]] — dev-plan/17, B63 (revisi): lengkapi foto wajib setelah laporan tersubmit. */
    public array $fotoLengkapi = [];

    /** @var array<int, UploadedFile> [work_report_photo_id => UploadedFile] — ganti foto pengerjaan yang sudah tersimpan (salah/blur). */
    public array $fotoGanti = [];

    /** Form tambah layanan oleh teknisi saat order berjalan. */
    public bool $tambahLayananTerbuka = false;

    public string $layananBaruNama = '';

    public string $layananBaruKategori = '';

    public string $layananBaruHarga = '';

    /** Komponen omset baris baru: jasa|material (default otomatis dari kategori/nama, bisa diganti). */
    public string $layananBaruKomponen = 'jasa';

    public int $layananBaruJumlah = 1;

    public string $layananBaruCatatan = '';

    public $buktiPembayaran;

    /** @var array<int, bool> Track which order items are expanded [order_item_id => isExpanded] */
    public array $expandedItems = [];

    /** @var array<string, array> Track temporary photo uploads [fieldName => ['id' => tempId, 'file_path' => path]] */
    public array $tempPhotos = [];

    /**
     * Fase 4: form keterangan per unit [order_unit_report_id => [field => nilai]].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $unitForm = [];

    /** Nomor unit (order_unit_reports.unit_no) yang accordion-nya terbuka; bisa di-deep-link lewat ?unit=N. */
    #[Url(as: 'unit')]
    public ?int $unitBuka = null;

    public function mount(Order $order): void
    {
        abort_if(! $order->diassignkanKe(auth()->user()), 403, 'Order ini bukan tugas Anda.');

        $this->orderId = $order->id;

        // Initialize expanded items (all expanded by default)
        foreach ($order->orderItems as $item) {
            $this->expandedItems[$item->id] = true;
        }

    }

    public function getOrderProperty(): Order
    {
        return Order::with(['customer', 'serviceCatalog', 'orderItems.acUnit', 'workReports.materials.stockItem', 'workReports.photos.orderItem.acUnit', 'latestPayment'])
            ->findOrFail($this->orderId);
    }

    /**
     * Template slot foto per order_item (dev-plan/13 §3), dipakai form
     * submitLaporan utk merender input per kategori.
     *
     * @return array<int, array<string, string>>
     */
    public function getFotoSlotsProperty(): array
    {
        return $this->order->orderItems
            ->mapWithKeys(fn ($item) => [$item->id => FotoLaporanSlot::untuk($item->kategori)])
            ->all();
    }

    /**
     * Kode slot yang wajib diisi per order_item (dev-plan/17, B63) —
     * dipakai blade utk tampilkan badge "Wajib".
     *
     * @return array<int, array<int, string>>
     */
    public function getFotoSlotsWajibProperty(): array
    {
        return $this->order->orderItems
            ->mapWithKeys(fn ($item) => [$item->id => array_keys(FotoLaporanSlot::wajibUntuk($item->kategori))])
            ->all();
    }

    /**
     * Opsi kategori layanan (sama dgn kategori katalog/ServiceType) — dipakai
     * dropdown "Tambah Layanan" teknisi. Kategori inilah yg menentukan
     * template grup foto yg muncul.
     *
     * @return array<string, string>
     */
    public function getKategoriLayananProperty(): array
    {
        return collect(ServiceType::cases())
            ->mapWithKeys(fn (ServiceType $c): array => [$c->value => str($c->value)->headline()->toString()])
            ->all();
    }

    /**
     * Baris layanan yang SUDAH punya foto tersimpan [order_item_id => id] —
     * dipakai blade utk menyembunyikan tombol "Hapus" (baris berfoto tidak
     * boleh dihapus; pakai "tidak jadi/batal").
     *
     * @return array<int, int>
     */
    public function getItemPunyaFotoProperty(): array
    {
        return WorkReportPhoto::query()
            ->whereIn('order_item_id', $this->order->orderItems->pluck('id'))
            ->pluck('order_item_id')
            ->unique()
            ->flip()
            ->all();
    }

    /**
     * Foto wajib yang masih kurang utk order ini (dev-plan/17, B63 revisi)
     * — dipakai tampilkan bagian "Lengkapi Foto Wajib" setelah laporan
     * tersubmit (Selesai/ButuhFollowup) tapi dokumentasinya belum lengkap.
     *
     * @return array<int, array{order_item: OrderItem, kode_slot: string, label: string}>
     */
    public function getFotoWajibKurangProperty(): array
    {
        if (! in_array($this->order->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)) {
            return [];
        }

        return app(TeknisiService::class)->fotoWajibKurang($this->order);
    }

    /**
     * Laporan TERAKHIR order ini — target timpa foto sebelum/sesudah utk
     * blok "Perbarui Foto Laporan" setelah laporan disubmit.
     */
    public function getLaporanTerakhirProperty(): ?WorkReport
    {
        return $this->order->workReports->sortByDesc('id')->first();
    }

    /**
     * Ganti foto sebelum/sesudah laporan yang SUDAH tersubmit (mis. hasilnya
     * ternyata blur setelah dicek admin). Kuota dicek dulu sebelum file
     * disimpan ke disk, sama seperti submit laporan biasa.
     */
    public function simpanPerbaikanFoto(): void
    {
        $this->validate([
            'fotoSebelumBaru' => ['nullable', 'image', 'max:5120'],
            'fotoSesudahBaru' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($this->fotoSebelumBaru === null && $this->fotoSesudahBaru === null) {
            $this->addError('fotoSebelumBaru', 'Pilih minimal satu foto untuk diperbarui.');

            return;
        }

        $tambahBytes = (int) ($this->fotoSebelumBaru?->getSize() ?? 0)
            + (int) ($this->fotoSesudahBaru?->getSize() ?? 0);

        try {
            if ($tambahBytes > 0) {
                app(StorageQuotaService::class)->pastikanCukup($tambahBytes);
            }
        } catch (BusinessRuleException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        try {
            app(TeknisiService::class)->perbaruiFotoLaporan(
                $this->order,
                auth()->user(),
                $this->fotoSebelumBaru?->store('work-reports', 'public'),
                $this->fotoSesudahBaru?->store('work-reports', 'public'),
            );
            StorageQuotaService::lupakanCache();

            // Relasi workReports di memori masih menyimpan laporan lama —
            // lepas dulu supaya galeri/ressor di bawah ikut re-query (Livewire
            // meng-cache computed property getOrder() selama satu request).
            $this->order->unsetRelation('workReports');

            session()->flash('status', 'Foto laporan diperbarui.');
            $this->reset(['fotoSebelumBaru', 'fotoSesudahBaru']);

            // Reload penuh supaya galeri & status di halaman segar, lalu toast
            // sukses tampil di paling atas.
            $this->redirect(\App\Support\Url::absolute('teknisi.order', ['order' => $this->orderId]));
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Ganti satu foto pengerjaan (per baris layanan) yang sudah tersimpan di
     * `work_report_photos` — dipakai tombol "Ganti" di galeri bila foto salah
     * (blur/salah sudut). File lama dihapus, file baru disimpan ke disk.
     * Dipanggil otomatis oleh JS setelah upload selesai.
     */
    public function gantiFotoKategori(int $photoId): void
    {
        $file = $this->fotoGanti[$photoId] ?? null;

        if (! $file instanceof UploadedFile) {
            return;
        }

        $foto = WorkReportPhoto::query()
            ->whereHas('workReport', fn ($q) => $q->where('order_id', $this->orderId))
            ->find($photoId);

        if ($foto === null) {
            session()->flash('error', 'Foto tidak ditemukan pada order ini.');

            return;
        }

        try {
            app(StorageQuotaService::class)->pastikanCukup((int) $file->getSize());

            $pathBaru = $file->store('work-reports', 'public');

            if (filled($foto->path)) {
                Storage::disk('public')->delete($foto->path);
            }

            $foto->update(['path' => $pathBaru]);
            StorageQuotaService::lupakanCache();
            $this->order->unsetRelation('workReports');

            session()->flash('status', 'Foto berhasil diganti.');
        } catch (BusinessRuleException $e) {
            session()->flash('error', $e->getMessage());
        } finally {
            unset($this->fotoGanti[$photoId]);
        }
    }

    /**
     * Hapus satu foto pengerjaan (per baris layanan) yang salah. Setelah
     * dihapus, slot itu otomatis muncul lagi di blok "Lengkapi Foto Wajib"
     * sehingga wajib difoto ulang sebelum bisa berangkat ke order berikutnya.
     */
    public function hapusFotoKategori(int $photoId): void
    {
        $foto = WorkReportPhoto::query()
            ->whereHas('workReport', fn ($q) => $q->where('order_id', $this->orderId))
            ->find($photoId);

        if ($foto === null) {
            session()->flash('error', 'Foto tidak ditemukan pada order ini.');

            return;
        }

        if (filled($foto->path)) {
            Storage::disk('public')->delete($foto->path);
        }

        $foto->delete();
        StorageQuotaService::lupakanCache();
        $this->order->unsetRelation('workReports');

        session()->flash('status', 'Foto dihapus. Lengkapi foto itu lagi sebelum berangkat ke order berikutnya.');
    }

    public function lengkapiFotoWajib(): void
    {
        $fotoKategori = [];
        foreach ($this->fotoLengkapi as $orderItemId => $slots) {
            if (! is_array($slots)) {
                continue;
            }

            foreach ($slots as $slot => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $fotoKategori[] = [
                    'order_item_id' => (int) $orderItemId,
                    'slot' => (string) $slot,
                    'path' => $file->store('work-reports', 'public'),
                ];
            }
        }

        // Jalur cadangan: kalau binding file Livewire tidak sampai (mis. JS
        // lama masih ter-cache di HP teknisi sehingga upload dikirim ke
        // endpoint temp-photo lama), ambil foto dari record
        // `temporary_photo_uploads` dengan field_name "fotoLengkapi.<item>.<slot>".
        // Ini yang bikin tombol "Simpan Foto" dulu tidak menyimpan apa-apa.
        $fotoTemp = [];
        $terpakai = [];
        foreach ($fotoKategori as $row) {
            $terpakai[$row['order_item_id'].'|'.$row['slot']] = true;
        }

        foreach ($this->fotoLengkapiDariTemp() as $temp) {
            if (isset($terpakai[$temp['order_item_id'].'|'.$temp['slot']])) {
                continue;
            }

            $fotoKategori[] = [
                'order_item_id' => $temp['order_item_id'],
                'slot' => $temp['slot'],
                'path' => $temp['path'],
            ];
            $fotoTemp[] = $temp['record'];
        }

        if ($fotoKategori === []) {
            $this->addError('fotoLengkapi', 'Tidak ada foto yang terkirim. Pilih ulang foto, tunggu sampai selesai mengunggah, lalu tekan Simpan Foto lagi.');

            return;
        }

        try {
            $teknisiService = app(TeknisiService::class);
            $teknisiService->lengkapiFotoWajib($this->order, auth()->user(), $fotoKategori);
            StorageQuotaService::lupakanCache();

            foreach ($fotoTemp as $photo) {
                $photo->deleteFile();
                $photo->delete();
            }

            $sisaKurang = $teknisiService->fotoWajibKurang($this->order->fresh('orderItems'));

            session()->flash('status', $sisaKurang === []
                ? 'Foto wajib sudah lengkap — Anda sekarang bisa berangkat ke order berikutnya.'
                : 'Foto tersimpan. Masih ada '.collect($sisaKurang)->pluck('label')->implode(', ').' yang belum diisi.');

            $this->reset('fotoLengkapi');

            // Reload penuh supaya halaman & galeri segar, lalu toast sukses
            // tampil di paling atas.
            $this->redirect(\App\Support\Url::absolute('teknisi.order', ['order' => $this->orderId]));
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Ambil foto wajib dari record temp-photo lama (endpoint
     * `teknisi.temp-photo.store`). File disalin ke `work-reports` supaya
     * tidak ikut terhapus saat record temp dibersihkan.
     *
     * @return array<int, array{order_item_id: int, slot: string, path: string, record: TemporaryPhotoUpload}>
     */
    private function fotoLengkapiDariTemp(): array
    {
        $hasil = [];

        foreach ($this->fotoTempTersedia() as $fieldName => $photo) {
            if (! str_starts_with($fieldName, 'fotoLengkapi.')) {
                continue;
            }

            $bagian = explode('.', $fieldName);
            if (count($bagian) !== 3) {
                continue;
            }

            $hasil[] = [
                'order_item_id' => (int) $bagian[1],
                'slot' => (string) $bagian[2],
                'path' => $this->salinFotoTemp($photo->file_path, 'work-reports'),
                'record' => $photo,
            ];
        }

        return $hasil;
    }

    /**
     * Foto yang telanjur masuk ke `temporary_photo_uploads` lewat jalur
     * cadangan lama (endpoint `teknisi.temp-photo.store`, dipakai saat
     * upload Livewire gagal) — dipetakan field_name => record, hanya yang
     * filenya masih ada di disk. Ini yang dulu bikin foto "tidak terbaca":
     * `submitLaporan()` cuma membaca property Livewire, padahal file-nya
     * tersimpan di tabel temp ini.
     *
     * @return array<string, TemporaryPhotoUpload>
     */
    private function fotoTempTersedia(): array
    {
        return TemporaryPhotoUpload::query()
            ->where('user_id', auth()->id())
            ->where('order_id', $this->orderId)
            ->get()
            ->filter(fn (TemporaryPhotoUpload $p): bool => filled($p->file_path) && Storage::disk('public')->exists($p->file_path))
            ->keyBy('field_name')
            ->all();
    }

    /**
     * Salin file temp-photo ke folder tujuan (`work-reports`/`order-photos`)
     * agar tidak ikut terhapus saat record temp dibersihkan. Balikin path
     * tujuan.
     */
    private function salinFotoTemp(string $sumber, string $folder): string
    {
        $tujuan = $folder.'/'.basename($sumber);
        Storage::disk('public')->copy($sumber, $tujuan);

        return $tujuan;
    }

    /**
     * Hapus record temp-photo (beserta filenya) yang `field_name`-nya
     * diawali salah satu prefix.
     *
     * @param  array<int, string>  $prefixes
     */
    private function bersihkanFotoTemp(array $prefixes): void
    {
        TemporaryPhotoUpload::query()
            ->where('user_id', auth()->id())
            ->where('order_id', $this->orderId)
            ->where(function ($query) use ($prefixes): void {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('field_name', 'like', $prefix.'%');
                }
            })
            ->get()
            ->each(function (TemporaryPhotoUpload $photo): void {
                $photo->deleteFile();
                $photo->delete();
            });
    }

    public function tambahMaterial(): void
    {
        $this->materials[] = ['stock_item_id' => null, 'jumlah' => 1];
    }

    public function hapusMaterial(int $index): void
    {
        unset($this->materials[$index]);
        $this->materials = array_values($this->materials);
    }

    public function incMaterial(int $index): void
    {
        $this->materials[$index]['jumlah'] = (int) ($this->materials[$index]['jumlah'] ?? 1) + 1;
    }

    public function decMaterial(int $index): void
    {
        $this->materials[$index]['jumlah'] = max(1, (int) ($this->materials[$index]['jumlah'] ?? 1) - 1);
    }

    public function berangkat(): void
    {
        try {
            app(TeknisiService::class)->berangkat($this->order, auth()->user());
            session()->flash('status', 'Status diperbarui: menuju lokasi.');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Tandai/aktifkan kembali satu baris layanan (unit) sebagai "tidak
     * jadi/batal" (revisi customer). Total tagihan & kewajiban foto otomatis
     * menyesuaikan — baris batal tidak dihitung & tidak perlu difoto.
     */
    public function toggleItemBatal(int $itemId): void
    {
        $item = $this->order->orderItems->firstWhere('id', $itemId);

        if ($item === null) {
            session()->flash('error', 'Baris layanan tidak ditemukan.');

            return;
        }

        try {
            $orderService = app(OrderService::class);

            if ($item->dibatalkan()) {
                $orderService->aktifkanItem($item, auth()->user());
                session()->flash('status', "{$item->nama_layanan} diaktifkan kembali.");
            } else {
                $orderService->batalkanItem($item, auth()->user());
                session()->flash('status', "{$item->nama_layanan} ditandai tidak jadi/batal.");
            }

            $this->order->unsetRelation('orderItems');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function toggleTambahLayanan(): void
    {
        $this->tambahLayananTerbuka = ! $this->tambahLayananTerbuka;
        $this->resetValidation();
    }

    public function updatedLayananBaruNama(): void
    {
        $this->setKomponenDefault();
    }

    public function updatedLayananBaruKategori(): void
    {
        $this->setKomponenDefault();
    }

    /** Default Jasa/Material: material bila Pengadaan atau nama terbaca barang. */
    private function setKomponenDefault(): void
    {
        $this->layananBaruKomponen = IncomeCategory::defaultUntukBaris(
            ServiceType::tryFrom($this->layananBaruKategori),
            $this->layananBaruNama,
        )->value;
    }

    /**
     * Teknisi menambah baris layanan saat order berjalan (menuju
     * lokasi/dikerjakan). Isian manual: nama + kategori (menentukan grup
     * foto) + harga + jumlah. Grup foto baris baru otomatis muncul.
     */
    public function tambahLayanan(): void
    {
        $this->validate([
            'layananBaruNama' => ['required', 'string', 'min:2', 'max:100'],
            'layananBaruKategori' => ['required', Rule::in(array_keys($this->kategoriLayanan))],
            'layananBaruHarga' => ['required', 'numeric', 'min:0'],
            'layananBaruKomponen' => ['required', Rule::in(['jasa', 'material'])],
            'layananBaruJumlah' => ['required', 'integer', 'min:1', 'max:1000'],
            'layananBaruCatatan' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            app(OrderService::class)->tambahLayananOlehTeknisi($this->order, auth()->user(), [
                'nama_layanan' => $this->layananBaruNama,
                'kategori' => $this->layananBaruKategori,
                'komponen' => $this->layananBaruKomponen,
                'harga' => (float) $this->layananBaruHarga,
                'jumlah' => (int) $this->layananBaruJumlah,
                'catatan' => $this->layananBaruCatatan,
            ]);

            session()->flash('status', 'Layanan ditambahkan. Lengkapi grup fotonya.');
            $this->redirect(\App\Support\Url::absolute('teknisi.order', ['order' => $this->orderId]));
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Teknisi menghapus baris layanan yang ditambahkan saat order berjalan.
     * Hanya baris tanpa foto tersimpan (lihat OrderService).
     */
    public function hapusLayanan(int $itemId): void
    {
        $item = $this->order->orderItems->firstWhere('id', $itemId);

        if ($item === null) {
            session()->flash('error', 'Baris layanan tidak ditemukan.');

            return;
        }

        try {
            app(OrderService::class)->hapusLayananOlehTeknisi($item, auth()->user());

            session()->flash('status', 'Layanan dihapus.');
            $this->redirect(\App\Support\Url::absolute('teknisi.order', ['order' => $this->orderId]));
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Tombol "Terkendala / Gagal": order tidak bisa dilanjutkan (mis.
     * customer tidak jadi / tidak ada di lokasi). Menunggu admin
     * menjadwalkan ulang.
     */
    public function tandaiKendala(): void
    {
        try {
            app(TeknisiService::class)->tandaiKendala($this->order, auth()->user(), $this->alasanKendala);
            session()->flash('status', 'Order ditandai terkendala. Admin akan menjadwalkan ulang.');
            $this->reset('alasanKendala');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Tombol "Ada Perbaikan" (dev-plan/13 §2): lapor kebutuhan sparepart/
     * perbaikan tambahan yg sudah dibicarakan dgn customer, tanpa
     * menghentikan progres order (beda dari tandaiKendala).
     */
    public function laporPerbaikan(): void
    {
        try {
            app(TeknisiService::class)->laporPerbaikan(
                $this->order,
                auth()->user(),
                $this->catatanPerbaikan,
                $this->estimasiHargaPerbaikan !== '' ? (float) $this->estimasiHargaPerbaikan : null,
            );
            session()->flash('status', 'Laporan perbaikan terkirim. Menunggu konfirmasi admin.');
            $this->reset(['catatanPerbaikan', 'estimasiHargaPerbaikan']);
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Ping GPS berkala dari browser teknisi (lihat order-detail.blade.php).
     * Gagal diam-diam kalau order sudah berpindah status — ini cuma ping
     * latar belakang, bukan aksi yang perlu ditampilkan ke teknisi.
     */
    public function updateLokasi(float $lat, float $lng): void
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return;
        }

        try {
            app(TeknisiService::class)->updateLokasi($this->order, auth()->user(), $lat, $lng);
        } catch (BusinessRuleException|AuthorizationException $e) {
            // diam-diam diabaikan.
        }
    }

    /**
     * Games 2 (dev-plan/15, B48): true bila check-in ini akan jadi
     * check-in job-site PERTAMA teknisi hari ini — perlu foto tambahan.
     */
    public function getButuhFotoTitikPertamaProperty(): bool
    {
        return ! Attendance::query()
            ->where('user_id', auth()->id())
            ->where('tanggal', now()->toDateString())
            ->exists();
    }

    public function checkIn(): void
    {
        $butuhFotoTitikPertama = $this->butuhFotoTitikPertama;

        if ($butuhFotoTitikPertama && $this->fotoTitikPertama === null) {
            $this->addError('fotoTitikPertama', 'Wajib upload foto bukti (mis. buka cover AC indoor) — ini check-in pertama Anda hari ini (Games 2).');

            return;
        }

        try {
            if ($butuhFotoTitikPertama) {
                // B25: cek kuota SEBELUM status order berubah, supaya tidak nanggung.
                app(StorageQuotaService::class)->pastikanCukup($this->fotoTitikPertama->getSize());
            }

            app(TeknisiService::class)->checkIn($this->order, auth()->user());

            if ($butuhFotoTitikPertama) {
                app(AttendanceService::class)->catatTitikPertama(auth()->user(), $this->fotoTitikPertama);
                $this->reset('fotoTitikPertama');
            }

            session()->flash('status', 'Check-in berhasil. Selamat bekerja!');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function submitLaporan(): void
    {
        $this->validate([
            'catatan' => ['required', 'string', 'min:3'],
            'materials.*.stock_item_id' => ['nullable', 'exists:stock_items,id'],
            'materials.*.jumlah' => ['nullable', 'integer', 'min:1'],
            'fotoSebelum' => ['nullable', 'image', 'max:5120'],
            'fotoSesudah' => ['nullable', 'image', 'max:5120'],
            'fotoKategori.*.*' => ['nullable', 'image', 'max:5120'],
        ]);

        // B25: cek kuota SEBELUM file foto disimpan ke disk.
        try {
            $quota = app(StorageQuotaService::class);
            $tambahBytes = (int) ($this->fotoSebelum?->getSize() ?? 0)
                + (int) ($this->fotoSesudah?->getSize() ?? 0);

            foreach ($this->fotoKategori as $slots) {
                foreach ($slots as $file) {
                    $tambahBytes += (int) ($file?->getSize() ?? 0);
                }
            }

            if ($tambahBytes > 0) {
                $quota->pastikanCukup($tambahBytes);
            }
        } catch (BusinessRuleException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $fotoKategori = [];
        foreach ($this->fotoKategori as $orderItemId => $slots) {
            foreach ($slots as $slot => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $fotoKategori[] = [
                    'order_item_id' => (int) $orderItemId,
                    'slot' => (string) $slot,
                    'path' => $file->store('work-reports', 'public'),
                ];
            }
        }

        $fotoSebelum = $this->fotoSebelum?->store('work-reports', 'public');
        $fotoSesudah = $this->fotoSesudah?->store('work-reports', 'public');

        // Jalur cadangan: kalau binding file Livewire tidak sampai (mis. JS
        // lama ter-cache di HP teknisi sehingga upload dikirim ke endpoint
        // temp-photo lama), ambil foto dari record `temporary_photo_uploads`.
        // Sebelum ini, foto fallback diabaikan lalu ikut terhapus — teknisi
        // jadi harus memfoto/double kerja.
        $temp = $this->fotoTempTersedia();

        if ($fotoSebelum === null && isset($temp['fotoSebelum'])) {
            $fotoSebelum = $this->salinFotoTemp($temp['fotoSebelum']->file_path, 'work-reports');
        }

        if ($fotoSesudah === null && isset($temp['fotoSesudah'])) {
            $fotoSesudah = $this->salinFotoTemp($temp['fotoSesudah']->file_path, 'work-reports');
        }

        $sudahKategori = collect($fotoKategori)
            ->mapWithKeys(fn (array $r): array => [$r['order_item_id'].'|'.$r['slot'] => true])
            ->all();

        foreach ($temp as $fieldName => $photo) {
            if (! str_starts_with($fieldName, 'fotoKategori.')) {
                continue;
            }

            $bagian = explode('.', $fieldName);
            if (count($bagian) !== 3) {
                continue;
            }

            $kunci = ((int) $bagian[1]).'|'.$bagian[2];
            if (isset($sudahKategori[$kunci])) {
                continue;
            }

            $fotoKategori[] = [
                'order_item_id' => (int) $bagian[1],
                'slot' => (string) $bagian[2],
                'path' => $this->salinFotoTemp($photo->file_path, 'work-reports'),
            ];
            $sudahKategori[$kunci] = true;
        }

        $payload = [
            'catatan' => $this->catatan,
            'materials' => collect($this->materials)
                ->filter(fn ($m) => ! empty($m['stock_item_id']))
                ->map(fn ($m) => ['stock_item_id' => (int) $m['stock_item_id'], 'jumlah' => (int) $m['jumlah']])
                ->values()
                ->all(),
            'butuh_followup' => $this->butuhFollowup,
            'is_klaim' => $this->isKlaim,
            'foto_sebelum' => $fotoSebelum,
            'foto_sesudah' => $fotoSesudah,
            'foto_kategori' => $fotoKategori,
        ];

        try {
            $teknisiService = app(TeknisiService::class);
            $teknisiService->submitLaporan($this->order, auth()->user(), $payload);
            StorageQuotaService::lupakanCache();

            // dev-plan/17, B63 (revisi): kasih tahu langsung di notifikasi
            // foto wajib mana yang masih kurang supaya teknisi tidak kaget
            // baru pas mau berangkat. Foto per baris layanan boleh diisi
            // belakangan lewat blok "Lengkapi Foto Wajib".
            $orderFresh = Order::with('orderItems')->findOrFail($this->orderId);
            $kurang = collect($teknisiService->fotoWajibKurang($orderFresh));

            if ($kurang->isEmpty()) {
                session()->flash('status', 'Laporan berhasil disubmit. Semua foto sudah lengkap.');
            } else {
                $daftar = $kurang->pluck('label')->implode(', ');
                session()->flash(
                    'status',
                    "Laporan berhasil disubmit. Masih ada {$daftar} yang belum diisi — lengkapi dulu di bawah sebelum bisa berangkat ke order berikutnya."
                );
            }

            // Cleanup temp-photo yang sudah dikonsumsi jalur laporan
            // (fotoSebelum/Sesudah/fotoKategori). Sisa temp yang belum
            // terpakai sengaja dibiarkan agar bisa dilengkapi belakangan.
            $this->bersihkanFotoTemp(['fotoSebelum', 'fotoSesudah', 'fotoKategori.']);

            $this->reset(['materials', 'catatan', 'butuhFollowup', 'isKlaim', 'fotoSebelum', 'fotoSesudah', 'fotoKategori']);
            $this->tempPhotos = [];
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Teknisi menandai metode pembayaran yang dipilih customer (B13b).
     * `null` berarti menghapus tanda. Pencatatan resmi tetap oleh Admin/Finance.
     */
    public function pilihMetode(?string $metode = null): void
    {
        if ($metode !== null && PaymentMethod::tryFrom($metode) === null) {
            session()->flash('error', 'Metode pembayaran tidak valid.');

            return;
        }

        try {
            app(TeknisiService::class)->catatMetodeDipilih(
                $this->order,
                auth()->user(),
                $metode === null ? null : PaymentMethod::tryFrom($metode),
            );

            session()->flash('status', $metode === null
                ? 'Tanda metode pembayaran customer dihapus.'
                : 'Metode yang dipilih customer diperbarui.');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Upload/ganti bukti pembayaran (B-bukti-bayar) — wajib utk customer
     * rumahan sebelum order bisa ditutup, opsional utk instansi.
     */
    public function uploadBuktiPembayaran(): void
    {
        $this->validate(['buktiPembayaran' => ['required', 'image', 'max:5120']]);

        try {
            app(StorageQuotaService::class)->pastikanCukup((int) $this->buktiPembayaran->getSize());
        } catch (BusinessRuleException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        try {
            $path = $this->buktiPembayaran->store('bukti-pembayaran', 'public');
            app(TeknisiService::class)->uploadBuktiPembayaran($this->order, auth()->user(), $path);
            StorageQuotaService::lupakanCache();
            session()->flash('status', 'Bukti pembayaran tersimpan.');
            $this->reset('buktiPembayaran');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Slider penutup order (B32): mengunci metode pembayaran & mencatat
     * waktu penutupan. Hanya tampil saat order selesai & metode sudah dipilih.
     */
    public function tutupOrder(): void
    {
        try {
            app(TeknisiService::class)->tutupOrder($this->order, auth()->user());

            session()->flash('order_ditutup', true);
            session()->flash('status', 'Order ditutup. Metode pembayaran terkunci. Terima kasih!');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Fase 4: unit-unit order ini untuk bagian "Keterangan Unit". Baris unit
     * disiapkan (prefill) hanya saat order sedang dikerjakan / sudah punya
     * data unit — order lama tanpa data unit tidak ikut menuntut keterangan.
     * Daftar kosong = bagian ini tidak ditampilkan.
     *
     * @return \Illuminate\Support\Collection<int, OrderUnitReport>
     */
    public function unitReports(): \Illuminate\Support\Collection
    {
        if (! in_array($this->order->status, [OrderStatus::Dikerjakan, OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)) {
            return collect();
        }

        $service = app(UnitReportService::class);

        return $service->bolehDisiapkanOtomatis($this->order)
            ? $service->siapkan($this->order)
            : $service->unitAktif($this->order);
    }

    /**
     * Isi $unitForm untuk unit yang belum punya state (tidak menimpa ketikan).
     *
     * @param  \Illuminate\Support\Collection<int, OrderUnitReport>  $units
     */
    private function muatUnitForm($units): void
    {
        foreach ($units as $unit) {
            if (isset($this->unitForm[$unit->id])) {
                continue;
            }

            $this->unitForm[$unit->id] = $this->stateUnit($unit);
        }
    }

    /** @return array<string, mixed> */
    private function stateUnit(OrderUnitReport $unit): array
    {
        return [
            'lokasi_label' => (string) $unit->lokasi_label,
            'posisi' => (string) $unit->posisi,
            'jenis_pekerjaan' => (string) $unit->jenis_pekerjaan,
            'bagian' => $unit->bagian ?: 'indoor',
            'suhu' => $unit->suhu === null ? '' : (string) $unit->suhu,
            'rpm' => $unit->rpm === null ? '' : (string) (int) $unit->rpm,
            'kondisi' => (string) $unit->kondisi,
            'catatan_kondisi' => (string) $unit->catatan_kondisi,
        ];
    }

    public function toggleUnit(int $unitNo): void
    {
        $this->unitBuka = $this->unitBuka === $unitNo ? null : $unitNo;
    }

    public function bukaUnit(int $unitNo): void
    {
        $this->unitBuka = $unitNo;
    }

    /**
     * Salin posisi dari unit sebelumnya (nomor terdekat di bawahnya) ke form
     * unit ini — hanya mengisi form, tersimpan saat tombol Simpan ditekan.
     */
    public function salinPosisiSebelumnya(int $unitReportId): void
    {
        $unit = OrderUnitReport::query()->where('order_id', $this->orderId)->find($unitReportId);
        if ($unit === null) {
            return;
        }

        $sebelumnya = OrderUnitReport::query()
            ->where('order_id', $this->orderId)
            ->where('unit_no', '<', $unit->unit_no)
            ->orderByDesc('unit_no')
            ->first();

        if ($sebelumnya === null) {
            session()->flash('error', 'Ini unit pertama — tidak ada unit sebelumnya.');

            return;
        }

        $this->muatUnitForm(collect([$sebelumnya, $unit]));
        $this->unitForm[$unit->id]['posisi'] = $this->unitForm[$sebelumnya->id]['posisi'] ?? (string) $sebelumnya->posisi;
    }

    public function simpanUnit(int $unitReportId): void
    {
        $unit = OrderUnitReport::query()->where('order_id', $this->orderId)->with('orderItem')->find($unitReportId);
        if ($unit === null) {
            session()->flash('error', 'Unit tidak ditemukan pada order ini.');

            return;
        }

        $service = app(UnitReportService::class);
        $prefix = "unitForm.{$unitReportId}.";

        // Validasi sisi server (Livewire) supaya pesan error menempel di field.
        $this->validate($service->aturan($unit->orderItem, $prefix));

        try {
            $service->simpan($unit, $this->unitForm[$unitReportId] ?? [], auth()->user());
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $this->unitForm[$unitReportId] = $this->stateUnit($unit->fresh());
        $this->order->unsetRelation('workReports');
        session()->flash('status', "Keterangan Unit {$unit->unit_no} tersimpan.");
    }

    /**
     * Toggle expanded/collapsed state untuk order item
     */
    public function toggleItemExpanded(int $itemId): void
    {
        $this->expandedItems[$itemId] = ! ($this->expandedItems[$itemId] ?? true);
    }

    /**
     * Check apakah semua foto wajib untuk item sudah terupload
     */
    public function isFotoItemLengkap(int $itemId): bool
    {
        $fotoWajib = $this->fotoSlotsWajib[$itemId] ?? [];
        if (empty($fotoWajib)) {
            return true;
        }

        $uploadedSlots = array_keys(array_filter($this->fotoKategori[$itemId] ?? []));

        return count(array_intersect($fotoWajib, $uploadedSlots)) === count($fotoWajib);
    }

    /**
     * Get count foto yang sudah terupload untuk item
     */
    public function countUploadedFotoForItem(int $itemId): int
    {
        return count(array_filter($this->fotoKategori[$itemId] ?? []));
    }

    /**
     * Get total slot foto untuk item
     */
    public function countTotalFotoSlotsForItem(int $itemId): int
    {
        return count($this->fotoSlots[$itemId] ?? []);
    }

    public function render()
    {
        $order = $this->order;

        // Build item photo status data for each order item
        $itemPhotoStatus = [];
        foreach ($order->orderItems as $item) {
            $itemPhotoStatus[$item->id] = [
                'isLengkap' => $this->isFotoItemLengkap($item->id),
                'uploadedCount' => $this->countUploadedFotoForItem($item->id),
                'totalSlots' => $this->countTotalFotoSlotsForItem($item->id),
            ];
        }

        $unitService = app(UnitReportService::class);
        $unitReports = $this->unitReports();
        $this->muatUnitForm($unitReports);

        $unitView = $unitReports->map(function (OrderUnitReport $unit) use ($unitService, $order, $unitReports): array {
            $item = $unit->orderItem;
            $pertamaItem = $unitReports->where('order_item_id', $item->id)->min('unit_no') === $unit->unit_no;

            return [
                'unit' => $unit,
                'item' => $item,
                'tampil' => $unitService->fieldSetTampil($item),
                'suhuWajib' => $unitService->suhuWajib($item),
                'butuh' => $unitService->butuhKeterangan($item),
                'lengkap' => $unitService->lengkap($unit, $item),
                // Foto slot milik baris layanan dipajang di unit pertama baris itu.
                'foto' => $pertamaItem
                    ? $order->workReports->flatMap->photos->where('order_item_id', $item->id)->sortBy('urutan')->values()
                    : collect(),
            ];
        });

        return view('livewire.teknisi.order-detail', [
            'unitView' => $unitView,
            'order' => $order,
            'stockItems' => StockItem::query()->where('aktif', true)->orderBy('nama_barang')->get(),
            'orderStatus' => OrderStatus::class,
            'paymentStatus' => PaymentStatus::class,
            'latestPayment' => $order->latestPayment,
            'paymentChannels' => app(PaymentChannelService::class)->daftarAktif(),
            'fotoSlots' => $this->fotoSlots,
            'itemPhotoStatus' => $itemPhotoStatus,
        ])->layout('layouts.teknisi', ['title' => 'Detail Order']);
    }
}
