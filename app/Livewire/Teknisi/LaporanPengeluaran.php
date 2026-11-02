<?php

namespace App\Livewire\Teknisi;

use App\Exceptions\BusinessRuleException;
use App\Models\TeknisiExpense;
use App\Services\TeknisiExpenseService;
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

    /** Teknisi lain yang bisa dipilih sebagai partner/pendamping. */
    #[Computed]
    public function partnerOptions(): array
    {
        return app(TeknisiExpenseService::class)->opsiPartner(auth()->user());
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
        return app(TeknisiExpenseService::class)->opsiOrder(auth()->user());
    }

    /**
     * Get summary untuk bulan ini
     */
    #[Computed]
    public function summaryBulanIni(): array
    {
        return app(TeknisiExpenseService::class)->ringkasanBulan(auth()->user(), $this->filterBulan);
    }

    /**
     * Get summary untuk hari ini
     */
    #[Computed]
    public function summaryHariIni(): array
    {
        return app(TeknisiExpenseService::class)->ringkasanHariIni(auth()->user());
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
        return app(TeknisiExpenseService::class)->breakdownKategori(auth()->user(), $this->modeFilter, $this->filterBulan, $this->filterTanggal);
    }

    /**
     * Get list expenses dengan filter
     */
    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        // Termasuk pengeluaran rekan yang menautkan saya sebagai partner (tampil, tidak bisa dihapus).
        return app(TeknisiExpenseService::class)->riwayat(auth()->user(), [
            'mode' => $this->modeFilter,
            'bulan' => $this->filterBulan,
            'tanggal' => $this->filterTanggal,
            'kategori' => $this->filterKategori,
            'status' => $this->filterStatus,
        ]);
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

            $service = app(TeknisiExpenseService::class);

            if ($this->editId !== null) {
                $e = TeknisiExpense::query()->where('teknisi_id', auth()->id())->findOrFail($this->editId);
                $hasil = $service->ubah(auth()->user(), $e, $data);
                session()->flash('status', 'Pengeluaran berhasil diubah.'.($hasil['perlu_acc_ulang'] ? ' Menunggu persetujuan ulang admin.' : ''));
            } else {
                $service->catat(auth()->user(), $data);
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
        try {
            app(TeknisiExpenseService::class)->hapus(auth()->user(), $expense);
            session()->flash('status', 'Pengeluaran berhasil dihapus.');
        } catch (BusinessRuleException $e) {
            session()->flash('error', $e->getMessage());
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
