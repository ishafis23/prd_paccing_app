<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Livewire\Portal\Login as PortalLogin;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\CustomerPortalService;
use App\Services\InvoiceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Portal Corporate Customer (dev-plan/22, Plan A) — akses perusahaan via
 * kolom username/password di tabel customers + halaman Pekerjaan, Unit,
 * Invoice, dan isolasi data antar customer.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $this->buatCompany = fn (array $attrs = []): Customer => Customer::factory()->create(array_merge([
        'nama' => 'PT Contoh',
        'jenis' => CustomerJenis::Company,
        'email' => 'pt@contoh.id',
    ], $attrs));

    $this->aktifkan = fn (Customer $c, ?string $username = 'ptcontoh', string $password = 'rahasia123'): Customer => app(CustomerPortalService::class)
        ->aturAkses($c, $username, $password, $this->admin);

    $this->buatOrder = function (Customer $c, string $tanggal = '2026-09-01', OrderStatus $status = OrderStatus::Selesai, ?CustomerAddress $cabang = null): Order {
        $order = Order::factory()->create([
            'customer_id' => $c->id,
            'customer_address_id' => $cabang?->id,
            'teknisi_id' => null,
            'status' => $status,
            'jenis_pelanggan' => CustomerJenis::Company,
            'tanggal_jadwal' => $tanggal,
        ]);
        $order->orderItems()->delete();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'kategori' => ServiceType::CuciAc,
            'nama_layanan' => 'Cuci AC Standar',
            'harga' => 75000,
            'jumlah' => 2,
        ]);

        return $order->fresh('orderItems');
    };
});

it('aturAkses menyimpan username & login portal pakai username', function () {
    $customer = ($this->buatCompany)();
    ($this->aktifkan)($customer, 'ptcontoh', 'rahasia123');

    expect($customer->fresh()->username)->toBe('ptcontoh');

    Livewire::test(PortalLogin::class)
        ->set('identitas', 'ptcontoh')
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertRedirect(route('portal.dashboard'));

    expect(Auth::guard('customer')->id())->toBe($customer->id);
});

it('login portal company tetap bisa pakai email', function () {
    $customer = ($this->buatCompany)(['email' => 'billing@contoh.id']);
    ($this->aktifkan)($customer, null, 'rahasia123');

    Livewire::test(PortalLogin::class)
        ->set('identitas', 'billing@contoh.id')
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertRedirect(route('portal.dashboard'));
});

it('customer perorangan tidak bisa login portal', function () {
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan, 'email' => 'orang@contoh.id']);
    ($this->aktifkan)($customer, null, 'rahasia123');

    Livewire::test(PortalLogin::class)
        ->set('identitas', 'orang@contoh.id')
        ->set('password', 'rahasia123')
        ->call('login')
        ->assertHasErrors('identitas');

    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('customer perorangan yang login langsung dipaksa keluar oleh middleware portal.aktif', function () {
    $customer = Customer::factory()->create(['jenis' => CustomerJenis::Perorangan, 'email' => 'orang@contoh.id']);
    Auth::guard('customer')->login($customer);

    $this->get('/portal')->assertRedirect(route('portal.login'));
    expect(Auth::guard('customer')->check())->toBeFalse();
});

it('daftar pekerjaan hanya menampilkan order milik customer login', function () {
    $customer = ($this->buatCompany)();
    ($this->aktifkan)($customer);
    $order = ($this->buatOrder)($customer, '2026-09-05');

    $lain = ($this->buatCompany)(['nama' => 'PT Lain', 'email' => 'lain@contoh.id']);
    $orderLain = ($this->buatOrder)($lain, '2026-09-06');

    Auth::guard('customer')->login($customer);

    $this->get('/portal/pekerjaan')
        ->assertOk()
        ->assertSee('Cuci AC Standar')
        ->assertSee(route('portal.pekerjaan.detail', $order), false)
        ->assertDontSee(route('portal.pekerjaan.detail', $orderLain), false);
});

it('detail order milik customer lain menghasilkan 404', function () {
    $customer = ($this->buatCompany)();
    ($this->aktifkan)($customer);

    $lain = ($this->buatCompany)(['nama' => 'PT Lain', 'email' => 'lain@contoh.id']);
    $orderLain = ($this->buatOrder)($lain);

    Auth::guard('customer')->login($customer);

    $this->get(route('portal.pekerjaan.detail', $orderLain))->assertNotFound();
});

it('unduh PDF laporan order sendiri 200, milik customer lain 404', function () {
    $customer = ($this->buatCompany)();
    ($this->aktifkan)($customer);
    $order = ($this->buatOrder)($customer);

    $lain = ($this->buatCompany)(['nama' => 'PT Lain', 'email' => 'lain@contoh.id']);
    $orderLain = ($this->buatOrder)($lain);

    Auth::guard('customer')->login($customer);

    $this->get(route('portal.laporan.pdf', $order))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get(route('portal.laporan.pdf', $orderLain))->assertNotFound();
});

it('invoice hanya menampilkan yang terkirim/lunas & mengisolasi data customer lain', function () {
    $customer = ($this->buatCompany)();
    ($this->aktifkan)($customer);

    $order = ($this->buatOrder)($customer, '2026-09-10');
    $svc = app(InvoiceService::class);

    $draft = $svc->buat([$order], $this->admin);

    $order2 = ($this->buatOrder)($customer, '2026-09-11');
    $terkirim = $svc->tandaiTerkirim($svc->buat([$order2], $this->admin), $this->admin);

    $lain = ($this->buatCompany)(['nama' => 'PT Lain', 'email' => 'lain@contoh.id']);
    $orderLain = ($this->buatOrder)($lain, '2026-09-12');
    $invoiceLain = $svc->tandaiTerkirim($svc->buat([$orderLain], $this->admin), $this->admin);

    Auth::guard('customer')->login($customer);

    $this->get('/portal/invoice')
        ->assertOk()
        ->assertSee($terkirim->nomor)
        ->assertDontSee($draft->nomor)
        ->assertDontSee($invoiceLain->nomor);

    $this->get(route('portal.invoice.detail', $terkirim))->assertOk();

    $this->get(route('portal.invoice.detail', $draft))->assertNotFound();
    $this->get(route('portal.invoice.detail', $invoiceLain))->assertNotFound();

    $this->get(route('portal.invoice.pdf', $terkirim))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->get(route('portal.invoice.pdf', $invoiceLain))->assertNotFound();
});

it('halaman unit menampilkan unit customer per cabang', function () {
    $customer = ($this->buatCompany)();
    ($this->aktifkan)($customer);

    $cabang = CustomerAddress::factory()->create(['customer_id' => $customer->id, 'nama_lokasi' => 'Cabang A']);
    CustomerAcUnit::factory()->create([
        'customer_id' => $customer->id,
        'customer_address_id' => $cabang->id,
        'kode_unit' => 'AC-01',
        'kode_ruangan' => 'Lobby',
    ]);

    Auth::guard('customer')->login($customer);

    $this->get('/portal/unit')
        ->assertOk()
        ->assertSee('AC-01')
        ->assertSee('Lobby')
        ->assertSee('Cabang A');
});

it('detail pekerjaan menampilkan laporan tanpa URL /storage/', function () {
    $customer = ($this->buatCompany)();
    ($this->aktifkan)($customer);
    $order = ($this->buatOrder)($customer, '2026-09-15');

    Auth::guard('customer')->login($customer);

    $this->get(route('portal.pekerjaan.detail', $order))
        ->assertOk()
        ->assertSee('Unduh Laporan PDF')
        ->assertDontSee('/storage/', false);
});
