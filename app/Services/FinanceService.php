<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\IncomeCategory;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\User;
use Carbon\CarbonInterface;

class FinanceService
{
    use RestrictsByRole;

    /**
     * Catat pengeluaran manual (Admin/Finance/Owner) — kategori
     * material | perawatan | operasional.
     */
    public function createExpense(
        ExpenseCategory $kategori,
        float $nominal,
        User $by,
        ?string $tanggal = null,
        ?string $keterangan = null,
        ?string $bukti = null,
        ?int $orderId = null
    ): Expense {
        $this->assertRole($by, [RoleName::Admin, RoleName::Finance, RoleName::Owner]);

        if ($nominal <= 0) {
            throw new BusinessRuleException('Nominal pengeluaran harus lebih dari 0.');
        }

        return Expense::create([
            'order_id' => $orderId,
            'kategori' => $kategori,
            'nominal' => $nominal,
            'tanggal' => $tanggal ?? now()->toDateString(),
            'keterangan' => $keterangan,
            'bukti' => $bukti,
            'dicatat_oleh' => $by->id,
        ]);
    }

    public function hapusExpense(Expense $expense, User $by): void
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Finance, RoleName::Owner]);
        $expense->delete();
    }

    /**
     * Laporan laba-rugi sederhana per rentang tanggal (default: bulan berjalan).
     *
     * @return array{pendapatan: float, pendapatan_jasa: float, pendapatan_material: float, pengeluaran: float, pengeluaran_material: float, pengeluaran_perawatan: float, pengeluaran_operasional: float, laba_rugi: float}
     */
    public function labaRugi(?CarbonInterface $dari = null, ?CarbonInterface $sampai = null): array
    {
        $dari ??= now()->startOfMonth();
        $sampai ??= now()->endOfMonth();

        $pendapatan = fn () => \App\Models\Income::whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);
        $pengeluaran = fn () => Expense::whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()]);

        $totalPendapatan = (float) $pendapatan()->sum('nominal');
        $totalPengeluaran = (float) $pengeluaran()->sum('nominal');

        return [
            'pendapatan' => $totalPendapatan,
            'pendapatan_jasa' => (float) (clone $pendapatan())->where('kategori', IncomeCategory::Jasa->value)->sum('nominal'),
            'pendapatan_material' => (float) (clone $pendapatan())->where('kategori', IncomeCategory::Material->value)->sum('nominal'),
            'pengeluaran' => $totalPengeluaran,
            'pengeluaran_material' => (float) (clone $pengeluaran())->where('kategori', ExpenseCategory::Material->value)->sum('nominal'),
            'pengeluaran_perawatan' => (float) (clone $pengeluaran())->where('kategori', ExpenseCategory::Perawatan->value)->sum('nominal'),
            'pengeluaran_operasional' => (float) (clone $pengeluaran())->where('kategori', ExpenseCategory::Operasional->value)->sum('nominal'),
            'laba_rugi' => round($totalPendapatan - $totalPengeluaran, 2),
        ];
    }
}
