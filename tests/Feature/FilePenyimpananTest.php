<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkReport;
use App\Services\StockService;
use App\Services\StorageQuotaService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    config(['penyimpanan.kuota_mb' => 1]); // 1 MB default utk test
    StorageQuotaService::lupakanCache();
});

it('menghitung pemakaian, persen, dan format ukuran dengan benar', function () {
    Storage::disk('public')->put('work-reports/a.jpg', str_repeat('x', 512 * 1024)); // 0,5 MB

    $quota = new StorageQuotaService;

    expect($quota->kuotaBytes())->toBe(1024 * 1024)
        ->and($quota->pakaiBytes(segar: true))->toBe(512 * 1024)
        ->and($quota->persenTerpakai())->toBe(50.0)
        ->and($quota->sisaBytes())->toBe(512 * 1024)
        ->and(StorageQuotaService::formatBytes(1024 * 1024))->toBe('1 MB');
});

it('pastikanCukup menolak saat tambahan melampaui kuota', function () {
    Storage::disk('public')->put('work-reports/a.jpg', str_repeat('x', 900 * 1024));
    $quota = new StorageQuotaService;

    $quota->pastikanCukup(100 * 1024); // 900+100 = 1000 KB < 1024 KB -> lolos

    expect(fn () => $quota->pastikanCukup(200 * 1024))
        ->toThrow(BusinessRuleException::class, 'Penyimpanan foto hampir penuh');
});

it('perintah foto:bersihkan menghapus foto tua & file yatim, menyisakan foto muda dan non-foto', function () {
    Storage::disk('public')->put('work-reports/lama-a.jpg', 'lama');
    Storage::disk('public')->put('work-reports/lama-b.jpg', 'lama');
    Storage::disk('public')->put('work-reports/baru-a.jpg', 'baru');
    Storage::disk('public')->put('work-reports/baru-b.jpg', 'baru');
    Storage::disk('public')->put('work-reports/yatim.jpg', 'yatim');
    Storage::disk('public')->put('payment-channels/qris.png', 'qris');

    $lama = WorkReport::factory()->create([
        'foto_sebelum' => 'work-reports/lama-a.jpg',
        'foto_sesudah' => 'work-reports/lama-b.jpg',
    ]);
    $lama->created_at = now()->subDays(70);
    $lama->save();

    $baru = WorkReport::factory()->create([
        'foto_sebelum' => 'work-reports/baru-a.jpg',
        'foto_sesudah' => 'work-reports/baru-b.jpg',
    ]);
    $baru->created_at = now()->subDays(5);
    $baru->save();

    $this->artisan('foto:bersihkan')->assertSuccessful();

    $disk = Storage::disk('public');

    // Foto tua: file hilang, kolom null, riwayat laporan tetap ada.
    expect($disk->exists('work-reports/lama-a.jpg'))->toBeFalse()
        ->and($disk->exists('work-reports/lama-b.jpg'))->toBeFalse()
        ->and($lama->fresh()->foto_sebelum)->toBeNull()
        ->and($lama->fresh()->foto_sesudah)->toBeNull();

    // Foto muda: utuh.
    expect($disk->exists('work-reports/baru-a.jpg'))->toBeTrue()
        ->and($disk->exists('work-reports/baru-b.jpg'))->toBeTrue()
        ->and($baru->fresh()->foto_sebelum)->toBe('work-reports/baru-a.jpg');

    // File yatim hilang; QRIS di folder lain tidak tersentuh; baris tetap 2.
    expect($disk->exists('work-reports/yatim.jpg'))->toBeFalse()
        ->and($disk->exists('payment-channels/qris.png'))->toBeTrue()
        ->and(WorkReport::query()->count())->toBe(2);
});

it('submit laporan membawa foto ditolak saat penyimpanan sudah penuh (B25)', function () {
    // Isi disk melebihi kuota 1 MB.
    Storage::disk('public')->put('work-reports/padat.bin', str_repeat('x', 2 * 1024 * 1024));

    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    // dev-plan/17: kategori default Cuci AC sekarang wajib 6 foto — tes ini
    // soal kuota penyimpanan, jadi pindah ke kategori yg belum wajib.
    $order->orderItems->first()->update(['kategori' => ServiceType::ServiceAc]);
    $service = new TeknisiService(new StockService);

    expect(fn () => $service->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai.',
        'materials' => [],
        'foto_sebelum' => 'work-reports/padat.bin',
    ]))->toThrow(BusinessRuleException::class, 'Penyimpanan foto penuh');

    expect(WorkReport::query()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Dikerjakan);
});
