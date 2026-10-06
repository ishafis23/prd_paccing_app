<?php

namespace App\Livewire\Admin;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\TeknisiExpense;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class ManajemenPengeluaranTeknisi extends Component
{
    use WithPagination;

    // Filter
    public string $filterBulan = '';

    public ?int $filterTeknisi = null;

    public string $filterKategori = '';

    public string $filterStatus = 'pending';

    // Approval form
    public ?TeknisiExpense $selectedExpense = null;

    public ?int $approvalNominal = null;

    #[Validate('nullable|string|max:500')]
    public string $approvalCatatan = '';

    public bool $isApproving = false;

    public string $approvalAction = 'approve'; // approve atau reject

    private const NOMINAL_RULE = 'required|integer|min:1000|max:5000000';

    /**
     * Hanya owner/admin/finance yang boleh memproses pengeluaran.
     */
    private function authorizeAcc(): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value])) {
            throw new AuthorizationException('Anda tidak berwenang memproses pengeluaran.');
        }
    }

    /**
     * Hanya pengeluaran berstatus pending yang boleh diproses.
     */
    private function pastikanPending(TeknisiExpense $expense): bool
    {
        if ($expense->status !== 'pending') {
            session()->flash('error', 'Hanya pengeluaran berstatus menunggu yang bisa diproses.');

            return false;
        }

        return true;
    }

    public function mount(): void
    {
        $this->filterBulan = today()->format('Y-m');
    }

    /**
     * Get summary untuk filter bulan
     */
    #[Computed]
    public function summaryBulan(): array
    {
        $query = TeknisiExpense::query()->byMonth($this->filterBulan);

        $expenses = $query->get();

        return [
            'total_pending' => $expenses->where('status', 'pending')->sum('nominal'),
            'total_approved' => $expenses->where('status', 'approved')->sum('nominal'),
            'total_rejected' => $expenses->where('status', 'rejected')->sum('nominal'),
            'count_pending' => $expenses->where('status', 'pending')->count(),
            'count_approved' => $expenses->where('status', 'approved')->count(),
        ];
    }

    /**
     * Get all expenses dengan filter
     */
    #[Computed]
    public function expenses(): LengthAwarePaginator
    {
        $query = TeknisiExpense::query();

        if ($this->filterBulan) {
            $query->byMonth($this->filterBulan);
        }

        if ($this->filterTeknisi) {
            $query->byTeknisi($this->filterTeknisi);
        }

        if ($this->filterKategori) {
            $query->where('kategori', $this->filterKategori);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->with('teknisi', 'approvedBy')
            ->orderBy('status', 'asc') // pending first
            ->orderBy('tanggal_input', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);
    }

    /**
     * Get list teknisi untuk filter
     */
    #[Computed]
    public function teknisList(): \Illuminate\Database\Eloquent\Collection
    {
        return User::role(RoleName::Teknisi->value)
            ->where('status', UserStatus::Aktif->value)
            ->orderBy('name')
            ->get(['users.id', 'users.name']);
    }

    /**
     * Open approval modal
     */
    public function openApproval(TeknisiExpense $expense, string $action = 'approve'): void
    {
        $this->authorizeAcc();

        if (! $this->pastikanPending($expense)) {
            return;
        }

        $this->selectedExpense = $expense;
        $this->approvalNominal = $expense->nominal;
        $this->approvalAction = $action;
        $this->approvalCatatan = '';
    }

    /**
     * Close approval modal
     */
    public function closeApproval(): void
    {
        $this->selectedExpense = null;
        $this->approvalNominal = null;
        $this->approvalAction = 'approve';
        $this->approvalCatatan = '';
    }

    /**
     * Submit approval/rejection
     */
    public function submitApproval(): void
    {
        $this->authorizeAcc();

        if (! $this->selectedExpense) {
            return;
        }

        // Segarkan dari DB: status bisa berubah sejak modal dibuka.
        $this->selectedExpense->refresh();

        if (! $this->pastikanPending($this->selectedExpense)) {
            $this->closeApproval();

            return;
        }

        if ($this->approvalAction === 'reject') {
            $this->validate([
                'approvalCatatan' => 'required|string|min:5',
            ], [
                'approvalCatatan.required' => 'Catatan penolakan wajib diisi.',
                'approvalCatatan.min' => 'Catatan minimal 5 karakter.',
            ]);
        } else {
            $this->validate([
                'approvalNominal' => self::NOMINAL_RULE,
            ]);
        }

        $this->isApproving = true;

        try {
            if ($this->approvalAction === 'approve') {
                $this->selectedExpense->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'nominal' => $this->approvalNominal,
                    'catatan_approval' => $this->approvalCatatan ?: null,
                    'tanggal_approve' => now(),
                ]);

                session()->flash('status', 'Pengeluaran disetujui.');
            } else {
                $this->selectedExpense->update([
                    'status' => 'rejected',
                    'approved_by' => auth()->id(),
                    'catatan_approval' => $this->approvalCatatan,
                    'tanggal_approve' => now(),
                ]);

                session()->flash('status', 'Pengeluaran ditolak.');
            }

            $this->closeApproval();
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal memproses pengeluaran: ' . $e->getMessage());
        } finally {
            $this->isApproving = false;
        }
    }

    /**
     * Quick approve without modal
     */
    public function quickApprove(TeknisiExpense $expense): void
    {
        $this->authorizeAcc();

        if (! $this->pastikanPending($expense)) {
            return;
        }

        if (validator(['nominal' => $expense->nominal], ['nominal' => self::NOMINAL_RULE])->fails()) {
            session()->flash('error', 'Nominal di luar batas 1.000–5.000.000; gunakan form persetujuan untuk mengoreksi.');

            return;
        }

        try {
            $expense->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'tanggal_approve' => now(),
            ]);

            session()->flash('status', 'Pengeluaran disetujui.');
        } catch (\Exception $e) {
            session()->flash('error', 'Gagal menyetujui pengeluaran.');
        }
    }

    /**
     * Get kategori breakdown
     */
    #[Computed]
    public function kategoriBreakdown(): array
    {
        $query = TeknisiExpense::query();

        if ($this->filterBulan) {
            $query->byMonth($this->filterBulan);
        }

        $expenses = $query->get();

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
            'pending' => ['label' => 'Menunggu', 'color' => 'yellow', 'icon' => 'o-clock'],
            'approved' => ['label' => 'Disetujui', 'color' => 'green', 'icon' => 'o-check-circle'],
            'rejected' => ['label' => 'Ditolak', 'color' => 'red', 'icon' => 'o-x-circle'],
            default => ['label' => $status, 'color' => 'gray', 'icon' => 'o-question-mark-circle'],
        };
    }

    public function render()
    {
        return view('livewire.admin.manajemen-pengeluaran-teknisi');
    }
}
