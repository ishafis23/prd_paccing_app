<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Models\WaTemplate;
use App\Services\NotifikasiWaService;
use App\Services\StockService;
use App\Services\TeknisiService;
use App\Services\WhatsappService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    config([
        'wa.aktif' => true,
        'wa.notif_berangkat' => true,
        'wa.rate_limit' => 0,
        'wa.rate_limit_sistem' => 0,
        'wa.base_url.qr' => null,
        'wa.base_url.send' => null,
    ]);

    $this->provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
    ]);
    $this->device = WaDevice::factory()->for($this->provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
        'notif_default' => true,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
    ]);

    $this->teknisi = User::factory()->create(['name' => 'Teknisi Andi']);
    $this->teknisi->assignRole(RoleName::Teknisi->value);
    $this->teknisiService = new TeknisiService(app(StockService::class));
});

function orderBerangkat(User $teknisi, mixed $noHp = null): Order
{
    $customer = Customer::factory()->create($noHp === null ? [] : ['no_hp' => $noHp]);

    return Order::factory()->create([
        'customer_id' => $customer->id,
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Terjadwal,
    ]);
}

it('berangkat mengirim notifikasi WA ke customer + tercatat di wa_logs', function () {
    $order = orderBerangkat($this->teknisi);

    $this->teknisiService->berangkat($order, $this->teknisi);

    $log = WaLog::query()->where('jenis_kirim', 'berangkat')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($log->related_id)->toBe($order->id)
        ->and($log->target)->toBe(app(WhatsappService::class)->normalizeNumber((string) $order->customer->no_hp))
        ->and($order->fresh()->status)->toBe(OrderStatus::MenujuLokasi);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send/')
        && $request['target'] === $log->target);
});

it('customer tanpa nomor WA tidak dikirimi, tapi berangkat tetap sukses', function () {
    $order = orderBerangkat($this->teknisi, '');

    $this->teknisiService->berangkat($order, $this->teknisi);

    expect(WaLog::query()->where('jenis_kirim', 'berangkat')->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::MenujuLokasi);
});

it('tidak kirim saat WA_AKTIF=false, tapi status tetap menuju_lokasi', function () {
    config(['wa.aktif' => false]);
    $order = orderBerangkat($this->teknisi);

    $this->teknisiService->berangkat($order, $this->teknisi);

    expect(WaLog::query()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::MenujuLokasi);
});

it('idempoten: kirim berangkat hanya sekali per order', function () {
    $order = orderBerangkat($this->teknisi);
    $notif = app(NotifikasiWaService::class);

    $notif->kirimBerangkat($order, $this->teknisi);
    $kedua = $notif->kirimBerangkat($order, $this->teknisi);

    expect($kedua)->toBeNull()
        ->and(WaLog::query()->where('jenis_kirim', 'berangkat')->count())->toBe(1);
});

it('template berangkat nonaktif -> notifikasi dilewati', function () {
    WaTemplate::create([
        'kode' => 'berangkat', 'nama' => 'x', 'isi' => 'Halo {nama}', 'aktif' => false,
    ]);

    $order = orderBerangkat($this->teknisi);
    $this->teknisiService->berangkat($order, $this->teknisi);

    expect(WaLog::query()->where('jenis_kirim', 'berangkat')->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::MenujuLokasi);
});
