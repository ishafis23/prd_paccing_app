<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * Extend action "Kirim Resi (WA)" (dev-plan/revisiteknisi Poin 5) — bila
 * customer punya `wa_group_id`, admin bisa sekalian kirim resi ke grup WA
 * customer lewat toggle di modal yang sama.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'wa.aktif' => true,
        'wa.rate_limit' => 0,
    ]);

    $this->provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
    ]);
    $this->device = WaDevice::factory()->for($this->provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
    $this->admin->wa_device_id = $this->device->id;
    $this->admin->save();

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
    ]);

    $this->buatOrderResi = function (array $customerAttrs = []): Order {
        $customer = Customer::factory()->create($customerAttrs);

        return Order::factory()->for($customer)->create([
            'resi_token' => 'resitokenabc123',
            'status' => OrderStatus::Selesai,
        ]);
    };
});

it('kirimResi tanpa wa_group_id hanya kirim ke customer (perilaku lama)', function () {
    $order = ($this->buatOrderResi)(['wa_group_id' => null, 'no_hp' => '0812-0000-0001']);

    $this->actingAs($this->admin);

    Livewire::test(ListOrders::class)
        ->callTableAction('kirimResi', $order, data: [
            'target' => '0812-0000-0001',
            'pesan' => 'Resi pengerjaan',
        ])
        ->assertHasNoTableActionErrors();

    expect(WaLog::query()->count())->toBe(1);
    $log = WaLog::query()->first();
    expect($log->action)->toBe('send-teks')->and($log->jenis_kirim)->toBe('resi');
});

it('kirimResi dengan wa_group_id + toggle aktif mengirim ke customer & grup', function () {
    $jid = '120363040944048443@g.us';
    $order = ($this->buatOrderResi)(['wa_group_id' => $jid, 'no_hp' => '0812-0000-0002']);

    $this->actingAs($this->admin);

    Livewire::test(ListOrders::class)
        ->callTableAction('kirimResi', $order, data: [
            'target' => '0812-0000-0002',
            'pesan' => 'Resi pengerjaan',
            'kirimKeGrup' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect(WaLog::query()->count())->toBe(2);

    $logTeks = WaLog::query()->where('action', 'send-teks')->first();
    $logGrup = WaLog::query()->where('action', 'send-grup')->first();

    expect($logTeks)->not->toBeNull()
        ->and($logTeks->jenis_kirim)->toBe('resi')
        ->and($logGrup)->not->toBeNull()
        ->and($logGrup->jenis_kirim)->toBe('resi-grup')
        ->and($logGrup->target)->toBe($jid);
});

it('admin uncheck toggle meski wa_group_id ada tetap hanya kirim personal', function () {
    $order = ($this->buatOrderResi)(['wa_group_id' => '120363040944048443@g.us', 'no_hp' => '0812-0000-0003']);

    $this->actingAs($this->admin);

    Livewire::test(ListOrders::class)
        ->callTableAction('kirimResi', $order, data: [
            'target' => '0812-0000-0003',
            'pesan' => 'Resi pengerjaan',
            'kirimKeGrup' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect(WaLog::query()->count())->toBe(1);
    expect(WaLog::query()->first()->action)->toBe('send-teks');
});

it('kirim ke grup gagal tidak menggagalkan kirim personal — tercatat terpisah', function () {
    // Base URL grup berbeda dari base URL send supaya bisa dibedakan responsnya.
    $this->provider->update(['base_url_group' => 'https://grup.test']);
    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
        'https://grup.test/api/send/*' => Http::response(['status' => 'error'], 500),
    ]);

    $jid = '120363040944048443@g.us';
    $order = ($this->buatOrderResi)(['wa_group_id' => $jid, 'no_hp' => '0812-0000-0004']);

    $this->actingAs($this->admin);

    Livewire::test(ListOrders::class)
        ->callTableAction('kirimResi', $order, data: [
            'target' => '0812-0000-0004',
            'pesan' => 'Resi pengerjaan',
            'kirimKeGrup' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect(WaLog::query()->count())->toBe(2);

    $logTeks = WaLog::query()->where('action', 'send-teks')->first();
    $logGrup = WaLog::query()->where('action', 'send-grup')->first();

    expect($logTeks->sukses())->toBeTrue()
        ->and($logGrup->sukses())->toBeFalse();
});

it('kirimResi tersembunyi bila belum ada device WhatsApp', function () {
    $this->admin->wa_device_id = null;
    $this->admin->save();
    $order = ($this->buatOrderResi)();

    Livewire::actingAs($this->admin)
        ->test(ListOrders::class)
        ->assertTableActionHidden('kirimResi', $order);
});
