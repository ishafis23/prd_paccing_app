<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\AcUnitsRelationManager;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };

    $this->mkTeknisi = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Teknisi->value);

        return $user;
    };
});

it('CustomerAcUnit::orderItems mengembalikan baris order_items yg tertaut ke unit itu', function () {
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id]);
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $item = OrderItem::factory()->create(['order_id' => $order->id, 'customer_ac_unit_id' => $unit->id]);
    OrderItem::factory()->create(['order_id' => $order->id, 'customer_ac_unit_id' => null]);

    expect($unit->orderItems)->toHaveCount(1);
    expect($unit->orderItems->first()->id)->toBe($item->id);
});

it('aksi Histori menampilkan riwayat pengerjaan unit (tanggal, layanan, teknisi, harga)', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-HIS']);
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Selesai,
        'tanggal_jadwal' => '2026-07-10',
    ]);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'customer_ac_unit_id' => $unit->id,
        'nama_layanan' => 'Cuci AC Ruang Server',
        'harga' => 125000,
    ]);

    Livewire::actingAs($admin)
        ->test(AcUnitsRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])
        ->mountTableAction('histori', $unit)
        ->assertSee('Cuci AC Ruang Server')
        ->assertSee($teknisi->name)
        ->assertSee('125.000');
});

it('aksi Histori menampilkan pesan kosong kalau unit belum pernah dikerjakan', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create();
    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id]);

    Livewire::actingAs($admin)
        ->test(AcUnitsRelationManager::class, [
            'ownerRecord' => $customer,
            'pageClass' => EditCustomer::class,
        ])
        ->mountTableAction('histori', $unit)
        ->assertSee('Belum ada riwayat pengerjaan');
});
