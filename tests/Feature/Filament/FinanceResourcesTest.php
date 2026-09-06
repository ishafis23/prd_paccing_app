<?php

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
            'kategori' => 'operasional',
            'nominal' => 50000,
            'tanggal' => now()->toDateString(),
            'keterangan' => 'BBM operasional',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $expense = Expense::first();
    expect($expense)->not->toBeNull();
    expect($expense->dicatat_oleh)->toBe($this->admin->id);
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
