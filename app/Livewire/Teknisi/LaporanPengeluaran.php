<?php

namespace App\Livewire\Teknisi;

use App\Models\Order;
use App\Models\TeknisiExpense;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class LaporanPengeluaran extends Component
{
    use WithPagination;

    #[Validate('required|date|before_or_equal:today')]
    public string $tanggal_input = '';

    #[Validate('required|in:bensin,makan,material,transport,lainnya')]
    public string $kategori = '';

    // CATATAN: properti angka di bawah sengaja TIDAK diketik (bukan `int`).
    // Kalau diketik `int`, saat user mengosongkan field (wire:model kirim ''),
    // Livewire menangkap TypeError lalu meng-unset properti (lihat
    // HandleComponents::setComponentPropertyAwareOfTypes). Setelah itu hook
    // updatedHarga/updatedQty -> recalcNominal membaca properti yg sudah unset
    // -> PropertyNotFoundException. Tanpa type, nilai '' aman, divalidasi
    // saat submit, dan di-cast (int) saat dipakai.
    #[Validate('nullable|exists:orders,id')]
    public $order_id = null;

    #[Validate('required|integer|min:1|max:100000')]
    public $qty = 1;

    #[Validate('required|integer|min:0|max:5000000')]
    public $harga = 0;

    #[Validate('required|integer|min:1000|max:5000000')]
    public $nominal = 0;

    #[Validate('nullable|string|max:255')]
    public string $keterangan = '';

    public string $filterBulan = '';

    public string $filterKategori = '';

    public string $filterStatus = '';

    public bool $showForm = false;

    public bool $isSubmitting = false;

    public function mount(): void
    {
        $this->tanggal_input = today()->format('Y-m-d');
        $this->filterBulan = today()->format('Y-m');
    }

    public function updatedQty(): void
    {
        $this->recalcNominal();
    }

    public function updatedHarga(): void
    {
        $this->recalcNominal();
    }

    private function recalcNominal(): void
    {
        // Cast (int) per operand — di PHP 8 aritmetika string kosong ("")
        // melempar TypeError, jadi jangan langsung dikalikan.
        $this->nominal = ((int) $this->qty) * ((int) $this->harga);
    }

    /**
     * Order yang ditugaskan ke teknisi ini (untuk ditautkan ke pengeluaran).
     */
    #[Computed]
    public function orderOptions(): array
    {
        $userId = auth()->id();

        return Order::query()
            ->where(function ($q) use ($userId) {
                $q->where('teknisi_id', $userId)
                    ->orWhereHas('orderTechnicians', fn ($t) => $t->where('teknisi_id', $userId));
            })
            ->with('customer')
            ->latest('id')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (Order $o) => [$o->id => "#{$o->id} — ".($o->customer?->nama ?? 'Tanpa nama')])
            ->all();
    }

    /**
     * Get summary untuk bulan ini
     */
    #[Computed]
    public function summaryBulanIni(): array
    {
        $expenses = TeknisiExpense::query()
            ->byTeknisi(auth()->id())
            ->byMonth($this->filterBulan)
            ->get();

        return [
            'total_pending' => $expenses->where('status', 'pending')->sum('nominal'),
            'total_approved' => $expenses->where('status', 'approved')->sum('nominal'),
            'total_rejected' => $expenses->where('status', 'rejected')->sum('nominal'),
            'total_items' => $expenses->count(),
        ];
    }

    /**
     * Get summary untuk hari ini
     */
    #[Computed]
    public function summaryHariIni(): array
    {
        $expenses = TeknisiExpense::query()
            ->byTeknisi(auth()->id())
            ->byDate(today()->format('Y-m-d'))
            ->get();

        return [
            'total_pending' => $expenses->where('status', 'pending')->sum('nominal'),
            'total_approved' => $expenses->where('status', 'approved')->sum('nominal'),
            'total_items' => $expenses->count(),
        ];
    }

    /**
     * Get kategori breakdown
     */
    #[Computed]
    public function kategoriBreakdown(): array
    {
        $expenses = TeknisiExpense::query()
            ->byTeknisi(auth()->id())
            ->byMonth($this->filterBulan)
            ->get();

        $kategoris = ['bensin', 'makan', 'material', 'transport', 'lainnya'];
        $result = [];

        foreach ($kategoris as $kat) {
            $filtered = $expenses->where('kategori', $kat);
            $result[$kat] = [
                'pending' => $filtered->where('status', 'pending')->sum('nominal'),
                'approved' => $filtered->where('status', 'approved')->sum('nominal'),
                'total' => $filtered->sum('nominal'),
                'count' => $filtered->count(),
            ];
        }

        return $result;
    }

    /**
     * Get list expenses dengan filter
     */
    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        $query = TeknisiExpense::query()
            ->byTeknisi(auth()->id());

        if ($this->filterBulan) {
            $query->byMonth($this->filterBulan);
        }

        if ($this->filterKategori) {
            $query->where('kategori', $this->filterKategori);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->orderBy('tanggal_input', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15);
    }

    /**
     * Submit pengeluaran baru
     */
    public function submitPengeluaran(): void
    {
        $this->validate();

        $this->isSubmitting = true;

        try {
            TeknisiExpense::create([
                'teknisi_id' => auth()->id(),
                'order_id' => $this->order_id,
                'tanggal_input' => $this->tanggal_input,
                'kategori' => $this->kategori,
                'qty' => $this->qty,
                'harga' => $this->harga,
                'nominal' => $this->nominal,
                'keterangan' => $this->keterangan,
                'status' => 'pending',
            ]);

            session()->flash('status', 'Pengeluaran berhasil dicatat.');
            $this->reset(['tanggal_input', 'kategori', 'order_id', 'qty', 'harga', 'nominal', 'keterangan', 'showForm']);
            $this->tanggal_input = today()->format('Y-m-d');
            $this->qty = 1;
            $this->harga = 0;

        } catch (\Exception $e) {
            session()->flash('error', 'Gagal mencatat pengeluaran: ' . $e->getMessage());
        } finally {
            $this->isSubmitting = false;
        }
    }

    /**
     * Delete pengeluaran (hanya jika pending)
     */
    public function deletePengeluaran(TeknisiExpense $expense): void
    {
        if ($expense->teknisi_id !== auth()->id()) {
            session()->flash('error', 'Anda tidak memiliki akses.');
            return;
        }

        if ($expense->status !== 'pending') {
            session()->flash('error', 'Hanya pengeluaran pending yang bisa dihapus.');
            return;
        }

        try {
            $expense->delete();
            session()->flash('status', 'Pengeluaran berhasil dihapus.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menghapus pengeluaran.');
        }
    }

    /**
     * Format rupiah
     */
    public function formatRupiah(int $nominal): string
    {
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }

    /**
     * Get kategori label
     */
    public function getKategoriLabel(string $kategori): string
    {
        return match ($kategori) {
            'bensin' => '⛽ Bensin',
            'makan' => '🍜 Makan',
            'material' => '🔧 Material',
            'transport' => '🚗 Transport',
            'lainnya' => '📦 Lainnya',
            default => $kategori,
        };
    }

    /**
     * Get status badge
     */
    public function getStatusBadge(string $status): array
    {
        return match ($status) {
            'pending' => ['label' => 'Menunggu', 'color' => 'yellow'],
            'approved' => ['label' => 'Disetujui', 'color' => 'green'],
            'rejected' => ['label' => 'Ditolak', 'color' => 'red'],
            default => ['label' => $status, 'color' => 'gray'],
        };
    }

    public function render()
    {
        return view('livewire.teknisi.laporan-pengeluaran');
    }
}
