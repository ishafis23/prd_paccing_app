<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\ServiceReminderResource\Pages\CreateServiceReminder;
use App\Models\Order;
use App\Models\ServiceReminder;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->mkOrderCuci = function (): Order {
        return Order::factory()->create([
            'status' => OrderStatus::Selesai,
        ]); // factory: service_catalog interval_bulan = 3
    };
});

it('buatReminderManual membuat notice utk order cuci — interval & tanggal default dari katalog (B35)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $order = ($this->mkOrderCuci)();

    $reminder = app(PaymentService::class)->buatReminderManual($order, $admin);

    expect($reminder->order_id)->toBe($order->id)
        ->and($reminder->customer_id)->toBe($order->customer_id)
        ->and($reminder->interval_bulan)->toBe(3)
        ->and($reminder->tanggal_servis_berikutnya->toDateString())
            ->toBe(CarbonImmutable::now()->addMonthsNoOverflow(3)->toDateString())
        ->and($reminder->status_notice->value)->toBe('belum_jatuh_tempo');
});

it('buatReminderManual menerima override interval & tanggal', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $order = ($this->mkOrderCuci)();

    $reminder = app(PaymentService::class)->buatReminderManual($order, $admin, 6, '2027-01-15');

    expect($reminder->interval_bulan)->toBe(6)
        ->and($reminder->tanggal_servis_berikutnya->toDateString())->toBe('2027-01-15');
});

it('buatReminderManual menolak layanan tanpa interval (service/pengadaan)', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $katalog = ServiceCatalog::factory()->pengadaan()->create(); // interval_bulan = null
    $order = Order::factory()->create(['service_catalog_id' => $katalog->id, 'status' => OrderStatus::Selesai]);

    app(PaymentService::class)->buatReminderManual($order, $admin);
})->throws(BusinessRuleException::class, 'interval');

it('buatReminderManual menolak duplikat notice utk order yang sama', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $order = ($this->mkOrderCuci)();

    app(PaymentService::class)->buatReminderManual($order, $admin);

    app(PaymentService::class)->buatReminderManual($order->fresh(), $admin);
})->throws(BusinessRuleException::class, 'sudah punya notice');

it('buatReminderManual hanya boleh oleh Admin/Owner', function (string $role) {
    $user = ($this->mkUser)($role);
    $order = ($this->mkOrderCuci)();

    app(PaymentService::class)->buatReminderManual($order, $user);
})->with(['finance' => RoleName::Finance->value, 'teknisi' => RoleName::Teknisi->value])
    ->throws(AuthorizationException::class);

it('policy: create hanya Admin/Owner; viewAny termasuk Owner & Finance', function (string $role, bool $bolehBuat, bool $bolehLihat) {
    $user = ($this->mkUser)($role);
    $reminder = ServiceReminder::factory()->create();

    expect($user->can('create', ServiceReminder::class))->toBe($bolehBuat)
        ->and($user->can('viewAny', ServiceReminder::class))->toBe($bolehLihat)
        ->and($user->can('view', $reminder))->toBe($bolehLihat);
})->with([
    'owner' => [RoleName::Owner->value, true, true],
    'admin' => [RoleName::Admin->value, true, true],
    'finance' => [RoleName::Finance->value, false, true],
    'hr' => [RoleName::Hr->value, false, false],
    'teknisi' => [RoleName::Teknisi->value, false, false],
]);

it('halaman create notice bisa dibuka admin & menciptakan reminder via form', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $order = ($this->mkOrderCuci)();

    Livewire::actingAs($admin)
        ->test(CreateServiceReminder::class)
        ->assertOk()
        ->fillForm([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'interval_bulan' => 3,
            'tanggal_servis_berikutnya' => CarbonImmutable::now()->addMonthsNoOverflow(3)->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('service_reminders', [
        'order_id' => $order->id,
        'customer_id' => $order->customer_id,
        'interval_bulan' => 3,
    ]);
});

it('halaman create notice 403 utk finance (B35)', function () {
    $finance = ($this->mkUser)(RoleName::Finance->value);

    Livewire::actingAs($finance)
        ->test(CreateServiceReminder::class)
        ->assertForbidden();
});
