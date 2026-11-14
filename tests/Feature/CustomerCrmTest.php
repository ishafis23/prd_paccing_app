<?php

use App\Enums\CustomerTipe;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\CustomerResource\RelationManagers\RiwayatPelayananRelationManager;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Models\WaDevice;
use App\Services\ServiceReminderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Admin->value));
});

it('customer baru default bertipe baru; bisa ditandai lama lengkap dgn jejak', function () {
    $c = Customer::factory()->create();
    expect($c->fresh()->tipe_customer)->toBe(CustomerTipe::Baru)->and($c->isLama())->toBeFalse();

    $c->tandaiTipe(CustomerTipe::Lama, $this->admin);

    $c = $c->fresh();
    expect($c->tipe_customer)->toBe(CustomerTipe::Lama)
        ->and($c->tipe_diubah_oleh)->toBe($this->admin->id)
        ->and($c->tipe_diubah_at)->not->toBeNull();
});

it('daftar customer: aksi massal tandai lama + filter baru/lama bekerja', function () {
    $a = Customer::factory()->create();
    $b = Customer::factory()->create();

    Livewire::actingAs($this->admin)->test(ListCustomers::class)
        ->callTableBulkAction('tandaiLama', [$a])
        ->assertHasNoTableBulkActionErrors();

    expect($a->fresh()->isLama())->toBeTrue()->and($b->fresh()->isLama())->toBeFalse();

    Livewire::actingAs($this->admin)->test(ListCustomers::class)
        ->filterTable('tipe_customer', 'lama')
        ->assertCanSeeTableRecords([$a])
        ->assertCanNotSeeTableRecords([$b]);
});

it('keluhan tersimpan di order dan tampil pada riwayat pelayanan customer', function () {
    $c = Customer::factory()->create();
    $o = Order::factory()->create(['customer_id' => $c->id, 'status' => OrderStatus::Selesai, 'keluhan' => 'AC bocor air']);

    Livewire::actingAs($this->admin)->test(RiwayatPelayananRelationManager::class, [
        'ownerRecord' => $c, 'pageClass' => \App\Filament\Resources\CustomerResource\Pages\ViewCustomer::class,
    ])->assertCanSeeTableRecords([$o])->assertSee('AC bocor air');

    expect($c->orderTerakhir->id)->toBe($o->id);
});

it('kirim pengingat satu klik: tanpa order selesai ditolak', function () {
    $c = Customer::factory()->create(['no_hp' => '081234567890']);

    app(ServiceReminderService::class)->kirimSekarangUntukCustomer($c);
})->throws(BusinessRuleException::class);

it('kirim pengingat satu klik: membuat reminder dari order terakhir lalu terkirim', function () {
    config(['wa.aktif' => true, 'wa.rate_limit' => 0, 'wa.rate_limit_sistem' => 0,
        'wa.base_url.qr' => null, 'wa.base_url.send' => null, 'wa.test_target' => null]);
    $provider = ProviderAccount::factory()->create(['base_url_qr' => 'https://gw.test', 'base_url_send' => 'https://gw.test']);
    WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG, 'notif_default' => true,
    ]);
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
    ]);

    $c = Customer::factory()->create(['no_hp' => '081234567890']);
    $o = Order::factory()->create(['customer_id' => $c->id, 'status' => OrderStatus::Selesai, 'tanggal_jadwal' => now()->subMonths(4)]);

    $out = app(ServiceReminderService::class)->kirimSekarangUntukCustomer($c);

    expect($out['hasil'])->toBe('terkirim')
        ->and($out['reminder']->order_id)->toBe($o->id)
        ->and($out['reminder']->jumlah_terkirim)->toBe(1)
        ->and(ServiceReminder::where('customer_id', $c->id)->count())->toBe(1);
});
