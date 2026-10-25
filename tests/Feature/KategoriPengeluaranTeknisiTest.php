<?php

use App\Livewire\Teknisi\LaporanPengeluaran;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\LaporanRekapService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Kategori pengeluaran teknisi (dev-plan/29 A3): transport → perawatan,
 * tambah gaji_honor & bonus.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole('teknisi');
});

it('KATEGORI memuat perawatan, gaji_honor, bonus dan tidak lagi transport', function () {
    expect(TeknisiExpense::KATEGORI)
        ->toContain('perawatan', 'gaji_honor', 'bonus')
        ->not->toContain('transport');
});

it('label kategori baru sesuai permintaan client', function () {
    expect(TeknisiExpense::kategoriLabel('perawatan'))->toBe('⚙️ Perawatan')
        ->and(TeknisiExpense::kategoriLabel('gaji_honor'))->toBe('💵 Gaji/Honor')
        ->and(TeknisiExpense::kategoriLabel('bonus'))->toBe('🎁 Bonus')
        ->and(array_keys(TeknisiExpense::kategoriOpsi()))->toBe(TeknisiExpense::KATEGORI);
});

it('migrasi rename memindahkan baris transport ke perawatan', function () {
    DB::table('teknisi_expenses')->insert([
        'teknisi_id' => $this->teknisi->id, 'tanggal_input' => '2026-10-03', 'kategori' => 'transport',
        'nominal' => 15000, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $migrasi = require database_path('migrations/2026_10_25_000002_rename_kategori_transport_ke_perawatan.php');
    $migrasi->up();

    expect(DB::table('teknisi_expenses')->where('kategori', 'transport')->count())->toBe(0)
        ->and(DB::table('teknisi_expenses')->where('kategori', 'perawatan')->count())->toBe(1);
});

it('form teknisi menerima kategori perawatan dan menolak transport', function () {
    Livewire::actingAs($this->teknisi)
        ->test(LaporanPengeluaran::class)
        ->set('kategori', 'perawatan')
        ->set('qty', 1)
        ->set('harga', 20000)
        ->call('submitPengeluaran')
        ->assertHasNoErrors();

    expect(TeknisiExpense::where('kategori', 'perawatan')->count())->toBe(1);

    Livewire::actingAs($this->teknisi)
        ->test(LaporanPengeluaran::class)
        ->set('kategori', 'transport')
        ->set('qty', 1)
        ->set('harga', 20000)
        ->call('submitPengeluaran')
        ->assertHasErrors(['kategori']);
});

it('rekap: gaji_honor/bonus tidak menambah operasional, perawatan masuk keranjang perawatan, honor masuk kolom gaji', function () {
    $buat = fn (string $kategori, int $nominal) => TeknisiExpense::create([
        'teknisi_id' => $this->teknisi->id, 'tanggal_input' => '2026-10-03',
        'kategori' => $kategori, 'nominal' => $nominal, 'status' => 'approved',
    ]);
    $buat('bensin', 25000);
    $buat('perawatan', 40000);
    $buat('gaji_honor', 700000);
    $buat('bonus', 300000);

    $rekap = app(LaporanRekapService::class)->susun(CarbonImmutable::parse('2026-10-01'))['rekap'];
    $total = $rekap['total'];

    expect($total['operasional'])->toBe(25000.0)
        ->and($total['perawatan'])->toBe(40000.0)
        // Honor & bonus terhitung SEKALI, di kolom Gaji/Honor — total pengeluaran
        // tetap sama dengan halaman Akuntan (25rb + 40rb + 1jt).
        ->and($total['gaji'])->toBe(1000000.0)
        ->and($total['pengeluaran'])->toBe(1065000.0);
});
