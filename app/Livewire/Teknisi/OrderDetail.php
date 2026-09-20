<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockItem;
use App\Models\WorkReport;
use App\Services\AttendanceService;
use App\Services\PaymentChannelService;
use App\Services\StorageQuotaService;
use App\Services\TeknisiService;
use App\Support\FotoLaporanSlot;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Locked;
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

    public $buktiPembayaran;

    public function mount(Order $order): void
    {
        abort_if(! $order->diassignkanKe(auth()->user()), 403, 'Order ini bukan tugas Anda.');

        $this->orderId = $order->id;
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
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function lengkapiFotoWajib(): void
    {
        $fotoKategori = [];
        foreach ($this->fotoLengkapi as $orderItemId => $slots) {
            foreach ($slots as $slot => $file) {
                if ($file === null) {
                    continue;
                }

                $fotoKategori[] = [
                    'order_item_id' => (int) $orderItemId,
                    'slot' => $slot,
                    'path' => $file->store('work-reports', 'public'),
                ];
            }
        }

        try {
            $teknisiService = app(TeknisiService::class);
            $teknisiService->lengkapiFotoWajib($this->order, auth()->user(), $fotoKategori);
            StorageQuotaService::lupakanCache();

            $sisaKurang = $teknisiService->fotoWajibKurang($this->order->fresh('orderItems'));

            session()->flash('status', $sisaKurang === []
                ? 'Foto wajib sudah lengkap — Anda sekarang bisa berangkat ke order berikutnya.'
                : 'Foto tersimpan. Masih ada '.collect($sisaKurang)->pluck('label')->implode(', ').' yang belum diisi.');

            $this->reset('fotoLengkapi');
        } catch (BusinessRuleException|AuthorizationException $e) {
            session()->flash('error', $e->getMessage());
        }
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
                if ($file === null) {
                    continue;
                }

                $fotoKategori[] = [
                    'order_item_id' => (int) $orderItemId,
                    'slot' => $slot,
                    'path' => $file->store('work-reports', 'public'),
                ];
            }
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
            'foto_sebelum' => $this->fotoSebelum?->store('work-reports', 'public'),
            'foto_sesudah' => $this->fotoSesudah?->store('work-reports', 'public'),
            'foto_kategori' => $fotoKategori,
        ];

        try {
            $teknisiService = app(TeknisiService::class);
            $teknisiService->submitLaporan($this->order, auth()->user(), $payload);
            StorageQuotaService::lupakanCache();

            // dev-plan/17, B63 (revisi): kasih tahu langsung di notifikasi
            // sukses foto wajib mana yang masih kurang, jangan cuma
            // mengandalkan teknisi ngeh sendiri dari blok "Lengkapi Foto
            // Wajib" di bawah — supaya mereka tidak kaget baru pas mau
            // berangkat ke order berikutnya.
            $kurang = $teknisiService->fotoWajibKurang(Order::with('orderItems')->findOrFail($this->orderId));

            if ($kurang === []) {
                session()->flash('status', 'Laporan berhasil disubmit. Semua foto wajib sudah lengkap.');
            } else {
                $daftar = collect($kurang)->pluck('label')->implode(', ');
                session()->flash(
                    'status',
                    "Laporan berhasil disubmit. Masih ada {$daftar} yang belum diisi — lengkapi dulu di bawah sebelum bisa berangkat ke order berikutnya."
                );
            }

            $this->reset(['materials', 'catatan', 'butuhFollowup', 'isKlaim', 'fotoSebelum', 'fotoSesudah', 'fotoKategori']);
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

    public function render()
    {
        $order = $this->order;

        return view('livewire.teknisi.order-detail', [
            'order' => $order,
            'stockItems' => StockItem::query()->where('aktif', true)->orderBy('nama_barang')->get(),
            'orderStatus' => OrderStatus::class,
            'paymentStatus' => PaymentStatus::class,
            'latestPayment' => $order->latestPayment,
            'paymentChannels' => app(PaymentChannelService::class)->daftarAktif(),
            'fotoSlots' => $this->fotoSlots,
        ])->layout('layouts.teknisi', ['title' => 'Detail Order']);
    }
}
