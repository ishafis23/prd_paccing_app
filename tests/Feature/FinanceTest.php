<?php

use App\Enums\ExpenseCategory;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\TeknisiExpense;
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

it('pengeluaran bisa dikaitkan ke order (uang jalan/trip) atau dibiarkan umum', function () {
    $admin = userBerRole(RoleName::Admin);
    $order = Order::factory()->create();

    $terkaitOrder = $this->financeService->createExpense(
        ExpenseCategory::Operasional,
        50000,
        $admin,
        null,
        'Uang jalan tim',
        null,
        $order->id,
    );
    expect($terkaitOrder->order_id)->toBe($order->id);
    expect($order->expenses()->count())->toBe(1);

    $umum = $this->financeService->createExpense(ExpenseCategory::Operasional, 100000, $admin, null, 'Sewa bulanan');
    expect($umum->order_id)->toBeNull();
});

it('teknisi tidak boleh mencatat pengeluaran', function () {
    $teknisi = userBerRole(RoleName::Teknisi);

    $this->financeService->createExpense(ExpenseCategory::Material, 10000, $teknisi);
})->throws(AuthorizationException::class);

function orderLunasUntukLaba(ServiceCatalog $katalog, string $tanggal): Order
{
    $order = Order::factory()->create([
        'service_catalog_id' => $katalog->id,
        'status' => OrderStatus::Selesai,
    ]);

    Payment::factory()->lunas()->create([
        'order_id' => $order->id,
        'total_tagihan' => $katalog->harga,
        'tanggal_bayar' => $tanggal,
    ]);

    return $order;
}

it('laba rugi bulan berjalan: sumber Akuntan (order selesai + expenses + teknisi approved) + breakdown', function () {
    $hari = now()->toDateString();

    // Pendapatan: jasa 100rb + material 3,5jt
    orderLunasUntukLaba(ServiceCatalog::factory()->create(['harga' => 100000]), $hari);
    orderLunasUntukLaba(ServiceCatalog::factory()->pengadaan()->create(), $hari);

    // Pengeluaran admin: material 500rb, perawatan 200rb, operasional 150rb
    Expense::factory()->create(['kategori' => ExpenseCategory::Material, 'nominal' => 500000, 'tanggal' => $hari]);
    Expense::factory()->create(['kategori' => ExpenseCategory::Perawatan, 'nominal' => 200000, 'tanggal' => $hari]);
    Expense::factory()->create(['kategori' => ExpenseCategory::Operasional, 'nominal' => 150000, 'tanggal' => $hari]);

    // Pengeluaran teknisi: approved ikut (bensin -> operasional, material -> material); pending/rejected tidak
    $teknisi = userBerRole(RoleName::Teknisi);
    $buat = fn (string $kategori, string $status, int $nominal) => TeknisiExpense::create([
        'teknisi_id' => $teknisi->id, 'tanggal_input' => $hari, 'kategori' => $kategori,
        'nominal' => $nominal, 'status' => $status,
    ]);
    $buat('bensin', 'approved', 20000);
    $buat('material', 'approved', 30000);
    $buat('bensin', 'pending', 99000);
    $buat('makan', 'rejected', 88000);

    // Di luar periode — tidak ikut
    orderLunasUntukLaba(ServiceCatalog::factory()->create(['harga' => 999999]), now()->subMonths(2)->toDateString());

    $laporan = $this->financeService->labaRugi();

    expect($laporan['pendapatan'])->toBe(3600000.0)
        ->and($laporan['pendapatan_jasa'])->toBe(100000.0)
        ->and($laporan['pendapatan_material'])->toBe(3500000.0)
        ->and($laporan['pengeluaran'])->toBe(900000.0)
        ->and($laporan['pengeluaran_material'])->toBe(530000.0)
        ->and($laporan['pengeluaran_perawatan'])->toBe(200000.0)
        ->and($laporan['pengeluaran_operasional'])->toBe(170000.0)
        ->and($laporan['laba_rugi'])->toBe(2700000.0);
});

it('laba rugi bisa difilter rentang tanggal custom', function () {
    orderLunasUntukLaba(ServiceCatalog::factory()->create(['harga' => 50000]), '2026-08-10');
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

it('form Expense di admin menampilkan field Order Terkait', function () {
    $admin = userBerRole(RoleName::Admin);

    $this->actingAs($admin)->get('/admin/expenses/create')
        ->assertOk()
        ->assertSee('Order Terkait');
});
