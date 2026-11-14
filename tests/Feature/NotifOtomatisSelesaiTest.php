<?php

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Jobs\KirimLaporanOtomatisJob;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Services\InvoicePdfService;
use App\Services\LaporanPdfService;
use App\Services\LaporanPengerjaanService;
use App\Services\NotifikasiWaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    config([
        'wa.aktif' => true,
        'wa.notif_otomatis_selesai' => true,
        'wa.notif_laporan' => true,
        'wa.test_target' => null,
        'wa.document_token' => 'tok123',
        'wa.pdf_secret' => 'rahasia',
        'wa.rate_limit' => 0,
        'wa.rate_limit_sistem' => 0,
        'wa.base_url.qr' => null,
        'wa.base_url.send' => null,
    ]);

    $provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
        'base_url_document' => 'https://gw.test/apiwablasted/api_document.php',
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    WaDevice::factory()->for($provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
        'notif_default' => true,
        'assigned_user_id' => $this->admin->id,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/apiwablasted/api_document.php' => Http::response(['status' => 'terkirim'], 200),
    ]);

    // Hindari render dompdf asli; yang diuji orkestrasinya.
    $this->mock(InvoicePdfService::class, fn ($m) => $m->shouldReceive('render')->andReturn('%PDF-1.4 inv'));
    $this->mock(LaporanPdfService::class, fn ($m) => $m->shouldReceive('render')->andReturn('%PDF-1.4 lap'));
    $this->mock(LaporanPengerjaanService::class, fn ($m) => $m->shouldReceive('dokumenOrder')->andReturn([]));

    $this->orderSelesai = function (bool $denganInvoice = false, bool $lunas = false): Order {
        $customer = Customer::factory()->create(['no_hp' => '081234567890']);
        $order = Order::factory()->create(['customer_id' => $customer->id, 'status' => OrderStatus::Selesai]);

        if ($denganInvoice) {
            $invoice = Invoice::create([
                'nomor' => 'INV-202611-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'tanggal' => now()->toDateString(),
                'status' => $lunas ? InvoiceStatus::Lunas : InvoiceStatus::Terkirim,
                'subtotal' => 150000,
                'total' => 150000,
            ]);
            $invoice->orders()->attach($order->id);
        }

        if ($lunas) {
            Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Lunas, 'jumlah_dibayar' => 150000, 'tanggal_bayar' => now()->toDateString()]);
        }

        return $order->fresh();
    };
});

it('order selesai tanpa invoice: laporan terkirim, tagihan dilewati; laporan_terkirim_pada terisi', function () {
    $order = ($this->orderSelesai)();

    $hasil = app(NotifikasiWaService::class)->kirimOtomatisSelesai($order);

    expect($hasil['laporan'])->toBe('terkirim')
        ->and($hasil['tagihan'])->toContain('belum ada invoice')
        ->and($order->fresh()->laporan_terkirim_pada)->not->toBeNull()
        ->and(WaLog::where('jenis_kirim', 'laporan_pengerjaan')->where('status', WaLog::STATUS_SUKSES)->count())->toBe(1);
});

it('belum bayar: laporan + invoice sebagai tagihan', function () {
    $order = ($this->orderSelesai)(denganInvoice: true);

    $hasil = app(NotifikasiWaService::class)->kirimOtomatisSelesai($order);

    expect($hasil)->toBe(['laporan' => 'terkirim', 'tagihan' => 'terkirim'])
        ->and(WaLog::where('jenis_kirim', 'tagihan')->where('related_type', Invoice::class)->count())->toBe(1)
        ->and(WaLog::where('jenis_kirim', 'pembayaran')->count())->toBe(0);
});

it('sudah bayar: laporan + kuitansi (jenis pembayaran), bukan tagihan', function () {
    $order = ($this->orderSelesai)(denganInvoice: true, lunas: true);

    $hasil = app(NotifikasiWaService::class)->kirimOtomatisSelesai($order);

    expect($hasil)->toBe(['laporan' => 'terkirim', 'tagihan' => 'terkirim'])
        ->and(WaLog::where('jenis_kirim', 'pembayaran')->count())->toBe(1)
        ->and(WaLog::where('jenis_kirim', 'tagihan')->count())->toBe(0);
});

it('idempoten: dipanggil lagi tidak mengirim ulang; tagihan lalu lunas -> kuitansi menyusul', function () {
    $order = ($this->orderSelesai)(denganInvoice: true);
    $svc = app(NotifikasiWaService::class);

    $svc->kirimOtomatisSelesai($order);
    $ulang = $svc->kirimOtomatisSelesai($order->fresh());
    expect($ulang)->toBe(['laporan' => 'sudah', 'tagihan' => 'sudah'])
        ->and(WaLog::count())->toBe(2);

    Payment::factory()->create(['order_id' => $order->id, 'status' => PaymentStatus::Lunas, 'jumlah_dibayar' => 150000, 'tanggal_bayar' => now()->toDateString()]);
    $setelahLunas = $svc->kirimOtomatisSelesai($order->fresh());

    expect($setelahLunas)->toBe(['laporan' => 'sudah', 'tagihan' => 'terkirim'])
        ->and(WaLog::where('jenis_kirim', 'pembayaran')->count())->toBe(1);
});

it('fitur mati (default) tidak mengirim apa pun dan tidak mengantre job', function () {
    config(['wa.notif_otomatis_selesai' => false]);
    Bus::fake();
    $order = ($this->orderSelesai)(denganInvoice: true);

    $hasil = app(NotifikasiWaService::class)->kirimOtomatisSelesai($order);
    KirimLaporanOtomatisJob::antre($order);

    expect($hasil['laporan'])->toBe('dilewati:nonaktif')
        ->and(WaLog::count())->toBe(0);
    Bus::assertNotDispatched(KirimLaporanOtomatisJob::class);
});

it('customer tanpa nomor WA dilewati dengan alasan, tidak melempar', function () {
    $order = ($this->orderSelesai)();
    $order->customer->update(['no_hp' => '']);

    $hasil = app(NotifikasiWaService::class)->kirimOtomatisSelesai($order->fresh());

    expect($hasil['laporan'])->toContain('dilewati:')->toContain('nomor')
        ->and(WaLog::count())->toBe(0);
});

it('order belum selesai tidak dikirimi', function () {
    $order = ($this->orderSelesai)();
    $order->update(['status' => OrderStatus::Dikerjakan]);

    $hasil = app(NotifikasiWaService::class)->kirimOtomatisSelesai($order->fresh());

    expect($hasil['laporan'])->toContain('belum selesai')->and(WaLog::count())->toBe(0);
});

it('job antre bila fitur menyala dan menjalankan pengiriman', function () {
    $order = ($this->orderSelesai)();

    KirimLaporanOtomatisJob::antre($order); // queue=sync pada test -> langsung jalan

    expect(WaLog::where('jenis_kirim', 'laporan_pengerjaan')->count())->toBe(1);
});
