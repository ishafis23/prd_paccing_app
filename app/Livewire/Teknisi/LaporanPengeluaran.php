<?php

namespace App\Livewire\Teknisi;

use App\Enums\RoleName;
use App\Models\Order;
use App\Models\TeknisiExpense;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class LaporanPengeluaran extends Component
{
    use WithPagination;

    #[Validate('required|date|before_or_equal:today')]
    public string $tanggal_input = '';

    // Aturan `kategori` ada di rules() — atribut Validate tidak bisa membaca konstanta.
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

    // Rekan satu tim: pengeluaran ini ikut terhitung untuknya (tanpa input ulang).
    public $partner_id = null;

    public string $filterBulan = '';

    /** Mode filter periode riwayat/breakdown: bulanan | harian. */
    public string $modeFilter = 'bulanan';

    /** Tanggal (Y-m-d) saat mode harian. */
    public string $filterTanggal = '';

    public string $filterKategori = '';

    public string $filterStatus = '';

    public bool $showForm = false;

    /** Id pengeluaran yang sedang diubah; null = form catat baru. */
    public ?int $editId = null;

    public bool $isSubmitting = false;

    /** @return array<string, mixed> digabung dengan atribut #[Validate] di properti lain. */
    protected function rules(): array
    {
        return [
            'kategori' => ['required', Rule::in(TeknisiExpense::KATEGORI)],
            'partner_id' => ['nullable', Rule::in(array_keys($this->partnerOptions))],
        ];
    }

    private function partnerQuery()
    {
        return User::role(RoleName::Teknisi->value)->where('users.id', '!=', auth()->id());
    }

    /** Teknisi lain yang bisa dipilih sebagai partner/pendamping. */
    #[Computed]
    public function partnerOptions(): array
    {
        return $this->partnerQuery()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function mount(): void
    {
        $this->tanggal_input = today()->format('Y-m-d');
        $this->filterBulan = today()->format('Y-m');
        $this->filterTanggal = today()->format('Y-m-d');
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

    /** Batasi query ke periode terpilih: satu hari (harian) atau satu bulan (bulanan). */
    private function terapkanPeriode($query)
    {
        if ($this->modeFilter === 'harian') {
            return $this->filterTanggal ? $query->byDate($this->filterTanggal) : $query;
        }

        return $this->filterBulan ? $query->byMonth($this->filterBulan) : $query;
    }

    /** Label periode aktif untuk judul breakdown. */
    public function labelPeriode(): string
    {
        return $this->modeFilter === 'harian' ? $this->filterTanggal : $this->filterBulan;
    }

    /**
     * Get kategori breakdown
     */
    #[Computed]
    public function kategoriBreakdown(): array
    {
        $expenses = $this->terapkanPeriode(TeknisiExpense::query()->byTeknisi(auth()->id()))->get();

        $result = [];

        foreach (TeknisiExpense::KATEGORI as $kat) {
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
        // Termasuk pengeluaran rekan yang menautkan saya sebagai partner (tampil, tidak bisa dihapus).
        $query = TeknisiExpense::query()
            ->tercatatUntuk(auth()->id())
            ->with(['teknisi', 'partner']);

        $this->terapkanPeriode($query);

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

    /** Buka form dalam mode ubah untuk pengeluaran milik sendiri (pencatat). */
    public function mulaiEdit(int $id): void
    {
        $e = TeknisiExpense::query()->where('teknisi_id', auth()->id())->find($id);

        if ($e === null) {
            session()->flash('error', 'Hanya pencatat yang bisa mengubah pengeluaran ini.');

            return;
        }

        $this->resetValidation();
        $this->editId = $e->id;
        $this->tanggal_input = $e->tanggal_input->format('Y-m-d');
        $this->order_id = $e->order_id;
        $this->partner_id = $e->partner_id;
        $this->kategori = (string) $e->kategori;
        $this->qty = $e->qty ?? 1;
        $this->harga = $e->harga ?? 0;
        $this->nominal = (int) $e->nominal;
        $this->keterangan = (string) $e->keterangan;
        $this->showForm = true;
    }

    public function batalForm(): void
    {
        $this->resetValidation();
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['tanggal_input', 'kategori', 'order_id', 'partner_id', 'qty', 'harga', 'nominal', 'keterangan', 'showForm', 'editId']);
        $this->tanggal_input = today()->format('Y-m-d');
        $this->qty = 1;
        $this->harga = 0;
    }

    /**
     * Submit pengeluaran baru / perubahan pengeluaran sendiri.
     *
     * Perubahan tanggal/kategori/qty/harga/nominal pada yang sudah diputuskan
     * admin (disetujui/ditolak) mengembalikannya ke Menunggu; ubah partner,
     * keterangan, atau order saja tidak mengubah status.
     */
    public function submitPengeluaran(): void
    {
        $this->validate();

        $this->isSubmitting = true;

        try {
            $data = [
                'partner_id' => filled($this->partner_id) ? (int) $this->partner_id : null,
                'order_id' => filled($this->order_id) ? $this->order_id : null,
                'tanggal_input' => $this->tanggal_input,
                'kategori' => $this->kategori,
                'qty' => $this->qty,
                'harga' => $this->harga,
                'nominal' => $this->nominal,
                'keterangan' => $this->keterangan,
            ];

            if ($this->editId !== null) {
                $e = TeknisiExpense::query()->where('teknisi_id', auth()->id())->findOrFail($this->editId);

                $uangBerubah = $e->tanggal_input->format('Y-m-d') !== $data['tanggal_input']
                    || $e->kategori !== $data['kategori']
                    || (int) $e->qty !== (int) $data['qty']
                    || (int) $e->harga !== (int) $data['harga']
                    || (int) $e->nominal !== (int) $data['nominal'];

                $kembaliMenunggu = $uangBerubah && $e->status !== 'pending';

                if ($kembaliMenunggu) {
                    $data += ['status' => 'pending', 'approved_by' => null, 'catatan_approval' => null];
                }

                $e->update($data);
                session()->flash('status', 'Pengeluaran berhasil diubah.'.($kembaliMenunggu ? ' Menunggu persetujuan ulang admin.' : ''));
            } else {
                TeknisiExpense::create($data + ['teknisi_id' => auth()->id(), 'status' => 'pending']);
                session()->flash('status', 'Pengeluaran berhasil dicatat.');
            }

            $this->resetForm();
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menyimpan pengeluaran: '.$e->getMessage());
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
        return TeknisiExpense::kategoriLabel($kategori);
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
