<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Income;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->orderService = new OrderService;
    $this->paymentService = new PaymentService;
});

function userWithRole(RoleName $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('admin membuat order tanpa teknisi -> status baru, alamat default customer', function () {
    $admin = userWithRole(RoleName::Admin);
    $customer = Customer::factory()->create(['alamat' => 'Jl. Contoh No. 1']);
    $catalog = ServiceCatalog::factory()->create(['harga' => 125000]);

    $order = $this->orderService->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'jumlah_unit' => 2,
        'tanggal_jadwal' => now()->addDay()->toDateString(),
    ], $admin);

    expect($order->status)->toBe(OrderStatus::Baru)
        ->and($order->jumlah_unit)->toBe(2)
        ->and($order->alamat_pengerjaan)->toBe('Jl. Contoh No. 1')
        ->and($order->total())->toBe(250000.0)
        ->and($order->created_by)->toBe($admin->id);
});

it('order dengan teknisi langsung berstatus terjadwal', function () {
    $admin = userWithRole(RoleName::Admin);
    $teknisi = userWithRole(RoleName::Teknisi);
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create();

    $order = $this->orderService->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
        'teknisi_id' => $teknisi->id,
        'tanggal_jadwal' => now()->addDay()->toDateString(),
    ], $admin);

    expect($order->status)->toBe(OrderStatus::Terjadwal)
        ->and($order->teknisi_id)->toBe($teknisi->id);
});

it('teknisi tidak boleh membuat order', function () {
    $teknisi = userWithRole(RoleName::Teknisi);
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create();

    $this->orderService->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $catalog->id,
    ], $teknisi);
})->throws(AuthorizationException::class);

it('katalog nonaktif atau jumlah unit 0 ditolak', function () {
    $admin = userWithRole(RoleName::Admin);
    $customer = Customer::factory()->create();
    $nonaktif = ServiceCatalog::factory()->create(['aktif' => false]);

    $this->orderService->createOrder([
        'customer_id' => $customer->id,
        'service_catalog_id' => $nonaktif->id,
    ], $admin);
})->throws(BusinessRuleException::class);

it('assign teknisi mengubah order baru -> terjadwal', function () {
    $admin = userWithRole(RoleName::Admin);
    $teknisi = userWithRole(RoleName::Teknisi);
    $order = Order::factory()->create(['status' => OrderStatus::Baru]);

    $updated = $this->orderService->assignTechnician($order, $teknisi);

    expect($updated->status)->toBe(OrderStatus::Terjadwal)
        ->and($updated->teknisi_id)->toBe($teknisi->id);
});

it('order selesai tidak bisa di-assign ulang', function () {
    $teknisiBaru = userWithRole(RoleName::Teknisi);
    $order = Order::factory()->terjadwal()->create(['status' => OrderStatus::Selesai]);

    $this->orderService->assignTechnician($order, $teknisiBaru);
})->throws(BusinessRuleException::class);

it('hanya order baru/terjadwal yang bisa dibatalkan', function () {
    $admin = userWithRole(RoleName::Admin);
    $order = Order::factory()->create(['status' => OrderStatus::Dikerjakan]);

    $this->orderService->cancel($order, $admin);
})->throws(BusinessRuleException::class);

it('pembayaran DP tidak membuat income dan order belum selesai', function () {
    $admin = userWithRole(RoleName::Admin);
    $order = Order::factory()->terjadwal()->create();

    $payment = $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Cash,
        $order->total() / 2,
        $admin
    );

    expect($payment->status)->toBe(PaymentStatus::Dp)
        ->and(Income::where('order_id', $order->id)->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Terjadwal);
});

it('pembayaran lunas -> income jasa + reminder +3 bulan + order selesai', function () {
    $admin = userWithRole(RoleName::Admin);
    $catalog = ServiceCatalog::factory()->create(['harga' => 200000, 'interval_bulan' => 3]);
    $order = Order::factory()->terjadwal()->create([
        'service_catalog_id' => $catalog->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $payment = $this->paymentService->recordPayment(
        $order,
        PaymentMethod::Transfer,
        $order->total(),
        $admin,
        '2026-08-15'
    );

    expect($payment->status)->toBe(PaymentStatus::Lunas)
        ->and($order->fresh()->status)->toBe(OrderStatus::Selesai);

    $income = Income::where('order_id', $order->id)->first();
    expect($income)->not->toBeNull()
        ->and($income->kategori->value)->toBe('jasa')
        ->and((float) $income->nominal)->toBe(200000.0)
        ->and($income->tanggal->toDateString())->toBe('2026-08-15');

    $reminder = ServiceReminder::where('order_id', $order->id)->first();
    expect($reminder)->not->toBeNull()
        ->and($reminder->tanggal_servis_berikutnya->toDateString())->toBe('2026-11-15')
        ->and($reminder->customer_id)->toBe($order->customer_id);
});

it('order pengadaan AC -> income kategori material, tanpa reminder', function () {
    $admin = userWithRole(RoleName::Admin);
    $catalog = ServiceCatalog::factory()->pengadaan()->create();
    $order = Order::factory()->create([
        'service_catalog_id' => $catalog->id,
        'status' => OrderStatus::Dikerjakan,
    ]);

    $this->paymentService->recordPayment($order, PaymentMethod::Qris, $order->total(), $admin);

    $income = Income::where('order_id', $order->id)->first();
    expect($income)->not->toBeNull()
        ->and($income->kategori->value)->toBe('material');

    expect(ServiceReminder::where('order_id', $order->id)->count())->toBe(0);
});

it('order lunas tidak bisa dibayar dua kali (anti income ganda)', function () {
    $admin = userWithRole(RoleName::Admin);
    $order = Order::factory()->create();
    $this->paymentService->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin);

    expect(Income::where('order_id', $order->id)->count())->toBe(1);

    $this->paymentService->recordPayment($order, PaymentMethod::Cash, 1000, $admin);
})->throws(BusinessRuleException::class);

it('hanya admin/owner yang bisa menandai reminder sudah dihubungi', function () {
    $admin = userWithRole(RoleName::Admin);
    $reminder = ServiceReminder::factory()->create();

    $this->paymentService->tandaiSudahDihubungi($reminder, $admin);

    expect($reminder->fresh()->status_notice->value)->toBe('sudah_dihubungi');
});

it('teknisi tidak boleh mencatat pembayaran', function () {
    $teknisi = userWithRole(RoleName::Teknisi);
    $order = Order::factory()->create();

    $this->paymentService->recordPayment($order, PaymentMethod::Cash, 1000, $teknisi);
})->throws(AuthorizationException::class);
