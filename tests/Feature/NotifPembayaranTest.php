<?php

use App\Enums\InvoiceStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Services\InvoicePdfService;
use App\Services\NotifikasiWaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    config([
        'wa.aktif' => true,
        'wa.document_token' => 'tok123',
        'wa.pdf_secret' => 'rahasia',
        'wa.rate_limit' => 0,
        'wa.rate_limit_sistem' => 0,
        'wa.base_url.qr' => null,
        'wa.base_url.send' => null,
    ]);

    $this->provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
        'base_url_document' => 'https://gw.test/apiwablasted/api_document.php',
    ]);
    $this->device = WaDevice::factory()->for($this->provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
        'notif_default' => true,
    ]);

    Http::fake([
        'https://gw.test/api/qr/*' => Http::response(['status' => 'ok'], 200),
        'https://gw.test/apiwablasted/api_document.php' => Http::response(['status' => 'terkirim'], 200),
    ]);

    // Hindari render dompdf asli; cukup verifikasi orkestrasi kirim.
    $this->mock(InvoicePdfService::class, function ($mock): void {
        $mock->shouldReceive('render')->andReturn('%PDF-1.4 dummy');
    });

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);
});

function invoiceLunas(): Invoice
{
    $customer = Customer::factory()->create(['no_hp' => '081234567890']);

    return Invoice::create([
        'nomor' => 'INV-202610-0001',
        'customer_id' => $customer->id,
        'tanggal' => now()->toDateString(),
        'status' => InvoiceStatus::Lunas,
        'subtotal' => 150000,
        'total' => 150000,
    ]);
}

it('kirim notif pembayaran mengirim invoice + tercatat jenis_kirim pembayaran', function () {
    $invoice = invoiceLunas();

    $log = app(NotifikasiWaService::class)->kirimPembayaranLunas($invoice, $this->admin);

    expect($log->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($log->action)->toBe('send-document')
        ->and($log->jenis_kirim)->toBe('pembayaran')
        ->and($log->related_type)->toBe(Invoice::class)
        ->and($log->related_id)->toBe($invoice->id)
        ->and($log->dokumen_path)->not->toBeNull();

    Storage::disk('local')->assertExists($log->dokumen_path);
});

it('customer tanpa nomor WA -> kirim notif pembayaran ditolak', function () {
    $invoice = invoiceLunas();
    $invoice->customer->update(['no_hp' => '']);

    expect(fn () => app(NotifikasiWaService::class)->kirimPembayaranLunas($invoice->fresh('customer'), $this->admin))
        ->toThrow(BusinessRuleException::class, 'nomor WhatsApp');
});
