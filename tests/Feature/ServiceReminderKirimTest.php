<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReminderStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\ServiceCatalog;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Services\PaymentService;
use App\Services\ServiceReminderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'wa.aktif' => true,
        'wa.rate_limit' => 0,
        'wa.rate_limit_sistem' => 0,
        'wa.base_url.qr' => null,
        'wa.base_url.send' => null,
        'wa.test_target' => null,
    ]);

    $provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
    ]);
    WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
        'notif_default' => true,
    ]);

    $this->kirimOk = function (): void {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
            'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
        ]);
    };

    // Reminder jatuh tempo kemarin milik customer ber-nomor.
    $this->buatReminder = function (array $attrs = []): ServiceReminder {
        $order = Order::factory()->create([
            'customer_id' => Customer::factory()->create(['no_hp' => '081234567890'])->id,
            'status' => OrderStatus::Selesai,
        ]);

        return ServiceReminder::factory()->create(array_merge([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'tanggal_servis_berikutnya' => now()->subDay()->toDateString(),
        ], $attrs));
    };

    $this->lunasi = function (Order $order, string $tgl = '2026-08-15'): void {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        app(PaymentService::class)->recordPayment($order, PaymentMethod::Cash, $order->total(), $admin, $tgl);
    };
});

it('interval pilihan teknisi dipakai saat order lunas', function () {
    $catalog = ServiceCatalog::factory()->create(['interval_bulan' => 3]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'status' => OrderStatus::Dikerjakan, 'interval_servis_bulan' => 6]);

    ($this->lunasi)($order);

    $r = ServiceReminder::where('order_id', $order->id)->first();
    expect($r->interval_bulan)->toBe(6)
        ->and($r->sumber_interval)->toBe('teknisi')
        ->and($r->tanggal_servis_berikutnya->toDateString())->toBe('2027-02-15');
});

it('tanpa pilihan teknisi memakai default dan sumber default', function () {
    $catalog = ServiceCatalog::factory()->create(['interval_bulan' => 3]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'status' => OrderStatus::Dikerjakan]);

    ($this->lunasi)($order);

    $r = ServiceReminder::where('order_id', $order->id)->first();
    expect($r->sumber_interval)->toBe('default');
});

it('teknisi mengisi interval setelah lunas -> reminder dihitung ulang', function () {
    $catalog = ServiceCatalog::factory()->create(['interval_bulan' => 3]);
    $order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'status' => OrderStatus::Dikerjakan]);
    ($this->lunasi)($order);

    app(ServiceReminderService::class)->setIntervalServis($order, 2);

    $r = ServiceReminder::where('order_id', $order->id)->first();
    expect($r->interval_bulan)->toBe(2)
        ->and($r->sumber_interval)->toBe('teknisi')
        ->and($r->tanggal_servis_berikutnya->toDateString())->toBe('2026-10-15');
});

it('API interval-servis menyimpan pilihan dan menolak di luar 1-12', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Selesai, 'tanggal_jadwal' => today()]);
    Sanctum::actingAs($teknisi);

    $this->postJson("/api/v1/order/{$order->id}/interval-servis", ['interval_bulan' => 13])->assertStatus(422);
    $this->postJson("/api/v1/order/{$order->id}/interval-servis", ['interval_bulan' => 4])->assertOk();

    expect($order->fresh()->interval_servis_bulan)->toBe(4);
});

it('cron mengirim reminder jatuh tempo: status_kirim 1 dan jadwal bulan depan', function () {
    ($this->kirimOk)();
    $r = ($this->buatReminder)();

    $this->artisan('reminder:kirim')->assertSuccessful();

    $r->refresh();
    expect($r->status_kirim)->toBe(ServiceReminder::KIRIM_TERKIRIM)
        ->and($r->jumlah_terkirim)->toBe(1)
        ->and($r->tahap)->toBe('bulanan')
        ->and($r->tanggal_servis_berikutnya->toDateString())->toBe(now()->addMonthNoOverflow()->toDateString())
        ->and(WaLog::where('jenis_kirim', 'reminder_servis')->where('status', WaLog::STATUS_SUKSES)->count())->toBe(1);
});

it('reminder yang belum jatuh tempo tidak dikirim', function () {
    ($this->kirimOk)();
    $r = ($this->buatReminder)(['tanggal_servis_berikutnya' => now()->addDays(3)->toDateString()]);

    $this->artisan('reminder:kirim')->assertSuccessful();

    expect($r->fresh()->status_kirim)->toBe(ServiceReminder::KIRIM_MENUNGGU)
        ->and(WaLog::count())->toBe(0);
});

it('gateway gagal -> status_kirim 2, percobaan naik, dicoba lagi sampai batas 3', function () {
    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'error'], 500),
    ]);
    $r = ($this->buatReminder)();

    $this->artisan('reminder:kirim')->assertSuccessful();
    $r->refresh();
    expect($r->status_kirim)->toBe(ServiceReminder::KIRIM_GAGAL)
        ->and($r->percobaan)->toBe(1)
        ->and($r->tanggal_servis_berikutnya->toDateString())->toBe(now()->subDay()->toDateString());

    $this->artisan('reminder:kirim');
    $this->artisan('reminder:kirim');
    $this->artisan('reminder:kirim'); // sudah habis -> tidak diproses lagi

    expect($r->fresh()->percobaan)->toBe(ServiceReminder::MAKS_PERCOBAAN);
});

it('setelah gagal lalu gateway pulih, kirim ulang berhasil dan percobaan direset', function () {
    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'error'], 500),
    ]);
    $r = ($this->buatReminder)();
    $this->artisan('reminder:kirim');
    expect($r->fresh()->status_kirim)->toBe(ServiceReminder::KIRIM_GAGAL);

    ($this->kirimOk)();
    $this->artisan('reminder:kirim');

    $r->refresh();
    expect($r->status_kirim)->toBe(ServiceReminder::KIRIM_TERKIRIM)->and($r->percobaan)->toBe(0);
});

it('customer tanpa nomor WA dilewati tanpa menghitung percobaan', function () {
    ($this->kirimOk)();
    $r = ($this->buatReminder)();
    $r->customer->update(['no_hp' => '']);

    $this->artisan('reminder:kirim');

    $r->refresh();
    expect($r->percobaan)->toBe(0)
        ->and($r->status_kirim)->toBe(ServiceReminder::KIRIM_MENUNGGU)
        ->and($r->error_terakhir)->toContain('nomor');
});

it('reminder lama ditutup bila customer punya reminder lebih baru', function () {
    ($this->kirimOk)();
    $lama = ($this->buatReminder)();
    ServiceReminder::factory()->create([
        'customer_id' => $lama->customer_id,
        'tanggal_servis_berikutnya' => now()->addMonths(3)->toDateString(),
    ]);

    $this->artisan('reminder:kirim');

    expect($lama->fresh()->status_notice)->toBe(ReminderStatus::Selesai)
        ->and(WaLog::count())->toBe(0);
});

it('reminder yang sudah ditandai dihubungi manual tidak dikirim otomatis', function () {
    ($this->kirimOk)();
    $r = ($this->buatReminder)(['status_notice' => ReminderStatus::SudahDihubungi]);

    $this->artisan('reminder:kirim');

    expect($r->fresh()->status_kirim)->toBe(ServiceReminder::KIRIM_MENUNGGU)->and(WaLog::count())->toBe(0);
});
