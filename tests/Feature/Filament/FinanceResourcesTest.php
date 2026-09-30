<?php

use App\Enums\ExpenseCategory;
use App\Enums\ReminderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\ExpenseResource\Pages\CreateExpense;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Resources\IncomeResource\Pages\ListIncomes;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\ServiceReminderResource\Pages\ListServiceReminders;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Services\FinanceService;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
    $this->hr = User::factory()->create();
    $this->hr->assignRole(RoleName::Hr->value);
});

it('admin bisa mencatat pengeluaran lewat form -> lewat FinanceService', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateExpense::class)
        ->fillForm([
            'kategori' => 'material',
            'qty' => 5,
            'harga' => 10000,
            'nominal' => 50000,
            'tanggal' => now()->toDateString(),
            'keterangan' => 'Beli material',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $expense = Expense::first();
    expect($expense)->not->toBeNull();
    expect($expense->dicatat_oleh)->toBe($this->admin->id);
    expect((int) $expense->qty)->toBe(5);
    expect((float) $expense->harga)->toBe(10000.0);
});

it('hr tidak bisa melihat resource expense (403)', function () {
    Livewire::actingAs($this->hr)
        ->test(ListExpenses::class)
        ->assertForbidden();
});

it('income read-only, admin bisa lihat listnya', function () {
    Income::factory()->count(2)->create();

    Livewire::actingAs($this->admin)
        ->test(ListIncomes::class)
        ->assertSuccessful();
});

it('payment ledger read-only, admin bisa lihat listnya', function () {
    Payment::factory()->count(2)->create();

    Livewire::actingAs($this->admin)
        ->test(ListPayments::class)
        ->assertSuccessful();
});

it('admin tandai reminder sudah dihubungi lewat aksi tabel', function () {
    $reminder = ServiceReminder::factory()->create(['status_notice' => ReminderStatus::SiapDihubungi]);

    Livewire::actingAs($this->admin)
        ->test(ListServiceReminders::class)
        ->callTableAction('tandaiDihubungi', $reminder);

    expect($reminder->fresh()->status_notice)->toBe(ReminderStatus::SudahDihubungi);
});

it('admin bisa edit & hapus pengeluaran lewat endpoint tabel order', function () {
    $expense = app(FinanceService::class)->createExpense(
        ExpenseCategory::Material,
        50000,
        $this->admin,
        null,
        'Awal',
        null,
        null,
        2,
        25000,
    );

    $this->actingAs($this->admin);

    $this->putJson("/expenses/{$expense->id}", [
        'kategori' => 'perawatan',
        'qty' => 3,
        'harga' => 10000,
        'nominal' => 30000,
        'tanggal' => now()->toDateString(),
        'keterangan' => 'Diubah',
    ])->assertOk();

    $this->assertDatabaseHas('expenses', [
        'id' => $expense->id,
        'kategori' => 'perawatan',
        'qty' => 3,
    ]);

    $this->deleteJson("/expenses/{$expense->id}")->assertOk();
    $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
});

it('hr tidak bisa edit pengeluaran lewat endpoint', function () {
    $expense = app(FinanceService::class)->createExpense(
        ExpenseCategory::Material,
        50000,
        $this->admin,
    );

    $this->actingAs($this->hr);

    $this->putJson("/expenses/{$expense->id}", [
        'kategori' => 'material',
        'qty' => 1,
        'harga' => 50000,
        'nominal' => 50000,
        'tanggal' => now()->toDateString(),
    ])->assertForbidden();

    $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'qty' => null]);
});
