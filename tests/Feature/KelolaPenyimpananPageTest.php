<?php

use App\Enums\PaymentChannelType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Pages\KelolaPenyimpanan;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\User;
use App\Models\WorkReport;
use App\Services\FilePenyimpananService;
use App\Services\StorageQuotaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    config(['penyimpanan.kuota_mb' => 1]);
    StorageQuotaService::lupakanCache();
});

function kpLaporanBerfoto(string $fotoSebelum, ?string $fotoSesudah = null): WorkReport
{
    $customer = Customer::factory()->create(['nama' => 'Budi Makmur']);
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    return WorkReport::factory()->create([
        'order_id' => $order->id,
        'foto_sebelum' => $fotoSebelum,
        'foto_sesudah' => $fotoSesudah,
    ]);
}

it('daftarFile memetakan status & label tiap file dari referensi DB (B28/B29)', function () {
    Storage::disk('public')->put('work-reports/sebelum.jpg', 'x');
    Storage::disk('public')->put('work-reports/sesudah.jpg', 'x');
    Storage::disk('public')->put('work-reports/yatim.jpg', 'x');
    Storage::disk('public')->put('payment-channels/qris.png', 'x');

    kpLaporanBerfoto('work-reports/sebelum.jpg', 'work-reports/sesudah.jpg');
    PaymentChannel::create([
        'nama' => 'QRIS Utama',
        'jenis' => PaymentChannelType::Qris,
        'gambar' => 'payment-channels/qris.png',
        'aktif' => true,
    ]);

    $daftar = app(FilePenyimpananService::class)->daftarFile();

    expect($daftar)->toHaveCount(4)
        ->and($daftar->firstWhere('path', 'work-reports/sebelum.jpg')['status'])->toBe('foto_sebelum')
        ->and($daftar->firstWhere('path', 'work-reports/sebelum.jpg')['label'])->toContain('Foto sebelum')
        ->and($daftar->firstWhere('path', 'work-reports/sebelum.jpg')['label'])->toContain('Budi Makmur')
        ->and($daftar->firstWhere('path', 'work-reports/sesudah.jpg')['status'])->toBe('foto_sesudah')
        ->and($daftar->firstWhere('path', 'work-reports/yatim.jpg')['status'])->toBe('yatim')
        ->and($daftar->firstWhere('path', 'work-reports/yatim.jpg')['label'])->toBe('Tanpa referensi')
        ->and($daftar->firstWhere('path', 'payment-channels/qris.png')['status'])->toBe('qris')
        ->and($daftar->firstWhere('path', 'payment-channels/qris.png')['label'])->toBe('QRIS — QRIS Utama');
});

it('hapusFile membuang file fisik & mengosongkan kolom DB, riwayat tetap (B30)', function () {
    Storage::disk('public')->put('work-reports/sebelum.jpg', 'x');
    Storage::disk('public')->put('work-reports/sesudah.jpg', 'x');
    Storage::disk('public')->put('work-reports/yatim.jpg', 'x');
    Storage::disk('public')->put('payment-channels/qris.png', 'x');

    $report = kpLaporanBerfoto('work-reports/sebelum.jpg', 'work-reports/sesudah.jpg');
    PaymentChannel::create([
        'nama' => 'QRIS Utama',
        'jenis' => PaymentChannelType::Qris,
        'gambar' => 'payment-channels/qris.png',
        'aktif' => true,
    ]);

    $service = app(FilePenyimpananService::class);
    $service->hapusFile('work-reports/sebelum.jpg');
    $service->hapusFile('payment-channels/qris.png');
    $service->hapusFile('work-reports/yatim.jpg');

    $disk = Storage::disk('public');

    expect($disk->exists('work-reports/sebelum.jpg'))->toBeFalse()
        ->and($disk->exists('payment-channels/qris.png'))->toBeFalse()
        ->and($disk->exists('work-reports/yatim.jpg'))->toBeFalse()
        ->and($report->fresh()->foto_sebelum)->toBeNull()
        ->and($report->fresh()->foto_sesudah)->toBe('work-reports/sesudah.jpg')
        ->and($disk->exists('work-reports/sesudah.jpg'))->toBeTrue()
        ->and(WorkReport::query()->count())->toBe(1)
        ->and(PaymentChannel::first()->gambar)->toBeNull();
});

it('hapusFile menolak path di luar folder terkelola', function () {
    Storage::disk('public')->put('logo.png', 'x');

    expect(fn () => app(FilePenyimpananService::class)->hapusFile('logo.png'))
        ->toThrow(BusinessRuleException::class, 'di luar folder penyimpanan');
});

it('halaman penyimpanan dapat diakses owner/admin/finance, 403 utk hr/teknisi (B31)', function (string $role, bool $boleh) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get(KelolaPenyimpanan::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'admin' => [RoleName::Admin->value, true],
    'finance' => [RoleName::Finance->value, true],
    'hr' => [RoleName::Hr->value, false],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('halaman menampilkan ringkasan kuota & daftar file utk admin (B27)', function () {
    Storage::disk('public')->put('work-reports/a.jpg', 'x');
    Storage::disk('public')->put('payment-channels/qris.png', 'x');
    kpLaporanBerfoto('work-reports/a.jpg', null);
    PaymentChannel::create([
        'nama' => 'QRIS Utama',
        'jenis' => PaymentChannelType::Qris,
        'gambar' => 'payment-channels/qris.png',
        'aktif' => true,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(KelolaPenyimpanan::class)
        ->assertOk()
        ->assertSee('Penyimpanan Foto')
        ->assertSee('a.jpg')
        ->assertSee('qris.png')
        ->assertSee('Bersihkan foto lama sekarang');
});

it('finance melihat halaman tanpa aksi hapus (B31)', function () {
    Storage::disk('public')->put('work-reports/a.jpg', 'x');

    $finance = User::factory()->create();
    $finance->assignRole(RoleName::Finance->value);

    Livewire::actingAs($finance)
        ->test(KelolaPenyimpanan::class)
        ->assertOk()
        ->assertSee('a.jpg')
        ->assertDontSee('Bersihkan foto lama sekarang')
        ->assertDontSee('Hapus file ini');
});

it('admin bisa menghapus file lewat aksi halaman; finance ditolak (B30/B31)', function () {
    Storage::disk('public')->put('work-reports/sebelum.jpg', 'x');
    $report = kpLaporanBerfoto('work-reports/sebelum.jpg', null);

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(KelolaPenyimpanan::class)
        ->call('hapusFile', 'work-reports/sebelum.jpg');

    expect(Storage::disk('public')->exists('work-reports/sebelum.jpg'))->toBeFalse()
        ->and($report->fresh()->foto_sebelum)->toBeNull();

    $finance = User::factory()->create();
    $finance->assignRole(RoleName::Finance->value);

    Livewire::actingAs($finance)
        ->test(KelolaPenyimpanan::class)
        ->call('hapusFile', 'work-reports/sebelum.jpg')
        ->assertForbidden();
});

it('hapus massal menghapus beberapa file sekaligus', function () {
    Storage::disk('public')->put('work-reports/a.jpg', 'x');
    Storage::disk('public')->put('work-reports/b.jpg', 'x');
    Storage::disk('public')->put('work-reports/yatim.jpg', 'x');
    $report = kpLaporanBerfoto('work-reports/a.jpg', 'work-reports/b.jpg');

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(KelolaPenyimpanan::class)
        ->set('terpilih', ['work-reports/a.jpg', 'work-reports/b.jpg', 'work-reports/yatim.jpg'])
        ->call('hapusMassal');

    $disk = Storage::disk('public');

    expect($disk->exists('work-reports/a.jpg'))->toBeFalse()
        ->and($disk->exists('work-reports/b.jpg'))->toBeFalse()
        ->and($disk->exists('work-reports/yatim.jpg'))->toBeFalse()
        ->and($report->fresh()->foto_sebelum)->toBeNull()
        ->and($report->fresh()->foto_sesudah)->toBeNull();
});

it('tombol bersihkan sekarang menjalankan pembersihan foto tua dari UI', function () {
    Storage::disk('public')->put('work-reports/lama.jpg', 'lama');
    $report = kpLaporanBerfoto('work-reports/lama.jpg', null);
    $report->created_at = now()->subDays(70);
    $report->save();

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin->value);

    Livewire::actingAs($admin)
        ->test(KelolaPenyimpanan::class)
        ->call('bersihkanSekarang');

    expect(Storage::disk('public')->exists('work-reports/lama.jpg'))->toBeFalse()
        ->and($report->fresh()->foto_sebelum)->toBeNull();

    $finance = User::factory()->create();
    $finance->assignRole(RoleName::Finance->value);

    Livewire::actingAs($finance)
        ->test(KelolaPenyimpanan::class)
        ->call('bersihkanSekarang')
        ->assertForbidden();
});
