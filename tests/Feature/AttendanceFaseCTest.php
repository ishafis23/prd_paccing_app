<?php

use App\Enums\IncentiveKategori;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Resources\OrderResource\Pages\CreateOrder;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\ServiceCatalog;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Services\AttendanceCodeService;
use App\Services\AttendanceService;
use App\Services\AttendanceSettingService;
use App\Services\TechnicianIncentiveService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    AttendanceSettingService::lupakanCache();

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->admin = ($this->mkUser)(RoleName::Admin->value);
    $this->teknisi = ($this->mkUser)(RoleName::Teknisi->value);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('Order is_klaim default false & bisa di-set true (boolean cast)', function () {
    $order = Order::factory()->create(['is_klaim' => true]);
    expect($order->is_klaim)->toBeTrue();

    $default = Order::factory()->create(['is_klaim' => false]);
    expect($default->fresh()->is_klaim)->toBeFalse();
});

it('Admin membuat order klaim lewat form Filament (B50)', function () {
    $customer = Customer::factory()->create();
    $catalog = ServiceCatalog::factory()->create(['aktif' => true]);

    Livewire::actingAs($this->admin)
        ->test(CreateOrder::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'service_catalog_id' => $catalog->id,
            'jumlah_unit' => 1,
            'is_klaim' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Order::first()->is_klaim)->toBeTrue();
});

it('Teknisi menandai klaim saat submit laporan (OrderDetail Livewire)', function () {
    $order = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('catatan', 'Ini kerjaan klaim garansi.')
        ->set('isKlaim', true)
        ->call('submitLaporan');

    expect($order->fresh()->is_klaim)->toBeTrue();
});

it('hitungTitikHarian mengecualikan order klaim (B50)', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-18 10:00:00'));

    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now(), 'is_klaim' => false]);
    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now(), 'is_klaim' => true]);

    $titik = app(TechnicianIncentiveService::class)->hitungTitikHarian($this->teknisi, Carbon::today());

    expect($titik)->toBe(1);
});

it('tentukanModeJalan mengabaikan order klaim saat menentukan mode', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-18 10:00:00'));
    $rekan = ($this->mkUser)(RoleName::Teknisi->value);

    // 1 order non-klaim sendiri -> harus "sendiri", meski ada order klaim
    // berdua di hari yang sama (yang seharusnya diabaikan sepenuhnya).
    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now(), 'is_klaim' => false]);
    $klaimBerdua = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now(), 'is_klaim' => true]);
    OrderTechnician::create(['order_id' => $klaimBerdua->id, 'teknisi_id' => $rekan->id]);

    $mode = app(TechnicianIncentiveService::class)->tentukanModeJalan($this->teknisi, Carbon::today());

    expect($mode)->toBe('sendiri');
});

it('catatPulang: Games 4 TIDAK terpicu kalau satu-satunya titik hari itu klaim', function () {
    app(AttendanceSettingService::class)->perbarui(['minimal_titik_sendiri' => 1], $this->admin);

    $kode = app(AttendanceCodeService::class)->buatBaru($this->admin)->kode;
    Carbon::setTestNow(Carbon::parse('2026-09-18 07:30:00'));
    app(AttendanceService::class)->catatDatang($this->teknisi, $kode, UploadedFile::fake()->image('a.jpg'));

    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now(), 'is_klaim' => true]);

    Carbon::setTestNow(Carbon::parse('2026-09-18 17:00:00'));
    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('b.jpg'));

    expect(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games4Kepulangan->value)->exists())->toBeFalse();
});

it('catatPulang: Games 4 tetap terpicu dari titik non-klaim meski ada titik klaim lain', function () {
    app(AttendanceSettingService::class)->perbarui(['minimal_titik_sendiri' => 1], $this->admin);

    $kode = app(AttendanceCodeService::class)->buatBaru($this->admin)->kode;
    Carbon::setTestNow(Carbon::parse('2026-09-18 07:30:00'));
    app(AttendanceService::class)->catatDatang($this->teknisi, $kode, UploadedFile::fake()->image('a.jpg'));

    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now(), 'is_klaim' => true]);
    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now(), 'is_klaim' => false]);

    Carbon::setTestNow(Carbon::parse('2026-09-18 17:00:00'));
    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('b.jpg'));

    expect(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games4Kepulangan->value)->exists())->toBeTrue();
});
