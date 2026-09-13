<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\StockItem;
use App\Services\PaymentChannelService;
use App\Services\StorageQuotaService;
use App\Services\TeknisiService;
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

    public string $catatan = '';

    public bool $butuhFollowup = false;

    public $fotoSebelum;

    public $fotoSesudah;

    public function mount(Order $order): void
    {
        abort_if(! $order->diassignkanKe(auth()->user()), 403, 'Order ini bukan tugas Anda.');

        $this->orderId = $order->id;
    }

    public function getOrderProperty(): Order
    {
        return Order::with(['customer', 'serviceCatalog', 'workReports.materials.stockItem', 'latestPayment'])
            ->findOrFail($this->orderId);
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

    public function checkIn(): void
    {
        try {
            app(TeknisiService::class)->checkIn($this->order, auth()->user());
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
        ]);

        // B25: cek kuota SEBELUM file foto disimpan ke disk.
        try {
            $quota = app(StorageQuotaService::class);
            $tambahBytes = (int) ($this->fotoSebelum?->getSize() ?? 0)
                + (int) ($this->fotoSesudah?->getSize() ?? 0);

            if ($tambahBytes > 0) {
                $quota->pastikanCukup($tambahBytes);
            }
        } catch (BusinessRuleException $e) {
            session()->flash('error', $e->getMessage());

            return;
        }

        $payload = [
            'catatan' => $this->catatan,
            'materials' => collect($this->materials)
                ->filter(fn ($m) => ! empty($m['stock_item_id']))
                ->map(fn ($m) => ['stock_item_id' => (int) $m['stock_item_id'], 'jumlah' => (int) $m['jumlah']])
                ->values()
                ->all(),
            'butuh_followup' => $this->butuhFollowup,
            'foto_sebelum' => $this->fotoSebelum?->store('work-reports', 'public'),
            'foto_sesudah' => $this->fotoSesudah?->store('work-reports', 'public'),
        ];

        try {
            app(TeknisiService::class)->submitLaporan($this->order, auth()->user(), $payload);
            StorageQuotaService::lupakanCache();
            session()->flash('status', 'Laporan berhasil disubmit.');
            $this->reset(['materials', 'catatan', 'butuhFollowup', 'fotoSebelum', 'fotoSesudah']);
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
        ])->layout('layouts.teknisi', ['title' => 'Detail Order']);
    }
}
