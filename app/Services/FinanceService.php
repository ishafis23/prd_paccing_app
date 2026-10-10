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
        ?int $orderId = null,
        ?int $qty = null,
        ?float $harga = null
    ): Expense {
        $this->assertRole($by, [RoleName::Admin, RoleName::Finance, RoleName::Owner]);

        if ($nominal <= 0) {
            throw new BusinessRuleException('Nominal pengeluaran harus lebih dari 0.');
        }

        return Expense::create([
            'order_id' => $orderId,
            'kategori' => $kategori,
            'nominal' => $nominal,
            'qty' => $qty,
            'harga' => $harga,
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

        $akuntan = app(AkuntanService::class);
        $pendapatan = $akuntan->pendapatan($dari, $sampai);
        $pengeluaran = $akuntan->pengeluaran($dari, $sampai)->filter(fn (array $r): bool => $r['dihitung']);

        $pendapatanPer = fn (IncomeCategory $k): float => (float) $pendapatan->sum('total_'.$k->value);
        // Pengeluaran teknisi (bensin/makan/dll.) masuk operasional; 'material' tetap material.
        $pengeluaranPer = fn (ExpenseCategory $k): float => (float) $pengeluaran->filter(
            fn (array $r): bool => $r['kategori'] === $k->value
                || ($k === ExpenseCategory::Operasional && $r['sumber'] === 'Teknisi'
                    && ! in_array($r['kategori'], array_column(ExpenseCategory::cases(), 'value'), true))
        )->sum('nominal');

        $totalPendapatan = (float) $pendapatan->sum('total');
        $totalPengeluaran = (float) $pengeluaran->sum('nominal');

        return [
            'pendapatan' => $totalPendapatan,
            'pendapatan_jasa' => $pendapatanPer(IncomeCategory::Jasa),
            'pendapatan_material' => $pendapatanPer(IncomeCategory::Material),
            'pengeluaran' => $totalPengeluaran,
            'pengeluaran_material' => $pengeluaranPer(ExpenseCategory::Material),
            'pengeluaran_perawatan' => $pengeluaranPer(ExpenseCategory::Perawatan),
            'pengeluaran_operasional' => $pengeluaranPer(ExpenseCategory::Operasional),
            'laba_rugi' => round($totalPendapatan - $totalPengeluaran, 2),
        ];
    }
}
