<?php

use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Livewire\Portal\Login as PortalLogin;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Services\CustomerPortalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };
});

it('aturPassword mengaktifkan akses portal & hash password', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'sekolah@contoh.id']);

    $hasil = app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);

    expect($hasil->bisaLoginPortal())->toBeTrue();
    expect(Hash::check('rahasia123', $hasil->password))->toBeTrue();
});

it('aturPassword menolak customer tanpa email', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => null]);

    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);
})->throws(BusinessRuleException::class, 'harus punya email');

it('aturPassword menolak password kurang dari 8 karakter', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'a@b.com']);

    app(CustomerPortalService::class)->aturPassword($customer, 'pendek', $admin);
})->throws(BusinessRuleException::class, 'minimal 8 karakter');

it('aturPassword menolak email yg sudah dipakai customer lain yg jg py akses portal', function () {
    $admin = ($this->mkAdmin)();
    $sudahAda = Customer::factory()->create(['email' => 'sama@contoh.id']);
    app(CustomerPortalService::class)->aturPassword($sudahAda, 'rahasia123', $admin);

    $baru = Customer::factory()->create(['email' => 'sama@contoh.id']);

    app(CustomerPortalService::class)->aturPassword($baru, 'rahasia456', $admin);
})->throws(BusinessRuleException::class, 'sudah dipakai customer lain');

it('aturPassword menolak bukan admin/owner', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $customer = Customer::factory()->create(['email' => 'a@b.com']);

    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $teknisi);
})->throws(AuthorizationException::class);

it('cabutAkses mengosongkan password (portal nonaktif lagi)', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'a@b.com']);
    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);

    $hasil = app(CustomerPortalService::class)->cabutAkses($customer, $admin);

    expect($hasil->bisaLoginPortal())->toBeFalse();
    expect($hasil->password)->toBeNull();
});

it('login portal berhasil dgn kredensial benar & redirect ke dashboard', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'sekolah@contoh.id']);
    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);

    Livewire::test(PortalLogin::class)
        ->set('email', 'sekolah@contoh.id')
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertRedirect(route('portal.dashboard'));

    expect(Auth::guard('customer')->check())->toBeTrue();
    expect(Auth::guard('customer')->id())->toBe($customer->id);
});

it('login portal gagal dgn password salah', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'sekolah@contoh.id']);
    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);

    Livewire::test(PortalLogin::class)
        ->set('email', 'sekolah@contoh.id')
        ->set('password', 'salah-password')
        ->call('login')
        ->assertHasErrors('email');

    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('login portal ditolak kalau akses belum diaktifkan (password kosong)', function () {
    Customer::factory()->create(['email' => 'belum@contoh.id']);

    Livewire::test(PortalLogin::class)
        ->set('email', 'belum@contoh.id')
        ->set('password', 'apapun123')
        ->call('login')
        ->assertHasErrors('email');
});

it('login portal ditolak utk customer nonaktif walau password sudah diatur', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'nonaktif@contoh.id', 'status' => CustomerStatus::Nonaktif]);
    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);

    Livewire::test(PortalLogin::class)
        ->set('email', 'nonaktif@contoh.id')
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertHasErrors('email');
});

it('tamu yg akses dashboard portal diarahkan ke login portal, bukan login admin/teknisi', function () {
    $this->get('/portal')->assertRedirect(route('portal.login'));
});

it('dashboard portal menampilkan unit AC customer & histori terakhir dikerjakan', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $customer = Customer::factory()->create(['email' => 'sekolah@contoh.id', 'jenis' => CustomerJenis::Company]);
    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);

    $unit = CustomerAcUnit::factory()->create(['customer_id' => $customer->id, 'kode_unit' => 'AC-01', 'kode_ruangan' => 'Ruang Guru']);
    $order = Order::factory()->create(['customer_id' => $customer->id, 'teknisi_id' => $teknisi->id, 'tanggal_jadwal' => '2026-08-01']);
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'customer_ac_unit_id' => $unit->id,
        'nama_layanan' => 'Cuci AC Ruang Guru',
    ]);
    ServiceReminder::factory()->create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'interval_bulan' => 1,
        'tanggal_servis_berikutnya' => '2026-09-01',
    ]);

    Auth::guard('customer')->login($customer);

    $this->get('/portal')
        ->assertOk()
        ->assertSee('AC-01')
        ->assertSee('Ruang Guru')
        ->assertSee('Cuci AC Ruang Guru')
        ->assertSee($teknisi->name)
        ->assertSee('Jadwal Servis Berikutnya');
});

it('logout portal menghapus sesi customer', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'sekolah@contoh.id']);
    app(CustomerPortalService::class)->aturPassword($customer, 'rahasia123', $admin);

    Auth::guard('customer')->login($customer);
    expect(Auth::guard('customer')->check())->toBeTrue();

    $this->post('/portal/logout')->assertRedirect(route('portal.login'));
    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('aksi Atur Password Portal & Cabut Akses Portal tersedia di tabel admin', function () {
    $admin = ($this->mkAdmin)();
    $customer = Customer::factory()->create(['email' => 'a@b.com']);

    Livewire::actingAs($admin)
        ->test(ListCustomers::class)
        ->assertTableActionVisible('aturPasswordPortal', $customer)
        ->assertTableActionHidden('cabutAksesPortal', $customer)
        ->callTableAction('aturPasswordPortal', $customer, data: ['password' => 'rahasia123'])
        ->assertNotified();

    expect($customer->fresh()->bisaLoginPortal())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(ListCustomers::class)
        ->assertTableActionVisible('cabutAksesPortal', $customer->fresh());
});
