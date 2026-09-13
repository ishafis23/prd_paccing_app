<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * dev-plan/12 §3.6: interval auto-reminder ikut kategori customer, bukan
 * angka tetap dari katalog — rumahan/perorangan 3 bulan, company
 * (sekolah/kantor dll) 1 bulan.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };
});

it('order lunas utk customer company -> reminder interval 1 bulan', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $catalog = ServiceCatalog::factory()->create(['interval_bulan' => 3]); // katalog beda, harus diabaikan
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin, '2026-08-15');

    $reminder = ServiceReminder::where('order_id', $order->id)->first();
    expect($reminder->interval_bulan)->toBe(1);
    expect($reminder->tanggal_servis_berikutnya->toDateString())->toBe('2026-09-15');
});

it('order lunas utk customer perorangan -> reminder interval 3 bulan', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan]);
    $catalog = ServiceCatalog::factory()->create(['interval_bulan' => 3]);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin, '2026-08-15');

    $reminder = ServiceReminder::where('order_id', $order->id)->first();
    expect($reminder->interval_bulan)->toBe(3);
    expect($reminder->tanggal_servis_berikutnya->toDateString())->toBe('2026-11-15');
});

it('order lunas tetap tidak membuat reminder kalau katalog tidak punya interval, apapun jenis customer', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Company]);
    $catalog = ServiceCatalog::factory()->pengadaan()->create(); // interval_bulan = null
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin);

    expect(ServiceReminder::where('order_id', $order->id)->count())->toBe(0);
});
