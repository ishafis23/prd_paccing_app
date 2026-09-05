<?php

use App\Enums\ExpenseCategory;
use App\Enums\IncomeCategory;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use App\Services\FinanceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->financeService = new FinanceService;
});

function userBerRole(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('admin mencatat pengeluaran dengan kategori', function () {
    $admin = userBerRole(RoleName::Admin);

    $expense = $this->financeService->createExpense(
        ExpenseCategory::Operasional,
        50000,
        $admin,
        '2026-09-01',
        'BBM kendaraan operasional'
    );

    expect($expense)->toBeInstanceOf(Expense::class)
        ->and($expense->kategori)->toBe(ExpenseCategory::Operasional)
        ->and((float) $expense->nominal)->toBe(50000.0)
        ->and($expense->tanggal->toDateString())->toBe('2026-09-01')
        ->and($expense->dicatat_oleh)->toBe($admin->id);
});

it('nominal pengeluaran harus positif', function () {
    $admin = userBerRole(RoleName::Admin);

    $this->financeService->createExpense(ExpenseCategory::Material, 0, $admin);
})->throws(BusinessRuleException::class);

it('teknisi tidak boleh mencatat pengeluaran', function () {
    $teknisi = userBerRole(RoleName::Teknisi);

    $this->financeService->createExpense(ExpenseCategory::Material, 10000, $teknisi);
})->throws(AuthorizationException::class);

it('laba rugi bulan berjalan: pendapatan - pengeluaran + breakdown kategori', function () {
    // Pendapatan: jasa 100rb + material 3,5jt
    Income::factory()->create(['kategori' => IncomeCategory::Jasa, 'nominal' => 100000, 'tanggal' => now()->toDateString()]);
    Income::factory()->create(['kategori' => IncomeCategory::Material, 'nominal' => 3500000, 'tanggal' => now()->toDateString()]);

    // Pengeluaran: material 500rb, perawatan 200rb, operasional 150rb
    Expense::factory()->create(['kategori' => ExpenseCategory::Material, 'nominal' => 500000, 'tanggal' => now()->toDateString()]);
    Expense::factory()->create(['kategori' => ExpenseCategory::Perawatan, 'nominal' => 200000, 'tanggal' => now()->toDateString()]);
    Expense::factory()->create(['kategori' => ExpenseCategory::Operasional, 'nominal' => 150000, 'tanggal' => now()->toDateString()]);

    // Di luar periode (bulan lalu) — tidak ikut
    Income::factory()->create(['kategori' => IncomeCategory::Jasa, 'nominal' => 999999, 'tanggal' => now()->subMonths(2)->toDateString()]);

    $laporan = $this->financeService->labaRugi();

    expect($laporan['pendapatan'])->toBe(3600000.0)
        ->and($laporan['pendapatan_jasa'])->toBe(100000.0)
        ->and($laporan['pendapatan_material'])->toBe(3500000.0)
        ->and($laporan['pengeluaran'])->toBe(850000.0)
        ->and($laporan['pengeluaran_material'])->toBe(500000.0)
        ->and($laporan['pengeluaran_perawatan'])->toBe(200000.0)
        ->and($laporan['pengeluaran_operasional'])->toBe(150000.0)
        ->and($laporan['laba_rugi'])->toBe(2750000.0);
});

it('laba rugi bisa difilter rentang tanggal custom', function () {
    Income::factory()->create(['kategori' => IncomeCategory::Jasa, 'nominal' => 50000, 'tanggal' => '2026-08-10']);
    Expense::factory()->create(['kategori' => ExpenseCategory::Operasional, 'nominal' => 20000, 'tanggal' => '2026-08-12']);

    $laporan = $this->financeService->labaRugi(
        Carbon\Carbon::parse('2026-08-01'),
        Carbon\Carbon::parse('2026-08-31')
    );

    expect($laporan['pendapatan'])->toBe(50000.0)
        ->and($laporan['pengeluaran'])->toBe(20000.0)
        ->and($laporan['laba_rugi'])->toBe(30000.0);
});

it('owner boleh menghapus pengeluaran, teknisi tidak', function () {
    $owner = userBerRole(RoleName::Owner);
    $expense = Expense::factory()->create();

    $this->financeService->hapusExpense($expense, $owner);

    expect(Expense::find($expense->id))->toBeNull();
});

it('teknisi tidak boleh menghapus pengeluaran', function () {
    $teknisi = userBerRole(RoleName::Teknisi);
    $expense = Expense::factory()->create();

    $this->financeService->hapusExpense($expense, $teknisi);
})->throws(AuthorizationException::class);
