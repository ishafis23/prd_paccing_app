<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Pages\BonusAdmin;
use App\Models\BonusAdminSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\BonusAdminService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/**
 * Revisi 17 Nov 2026 poin 24 — bonus Admin/HRD/Finance 1% omset jasa, minimal Rp 50 jt.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (string $role, string $nama): User {
        $u = User::factory()->create(['name' => $nama]);
        $u->assignRole($role);

        return $u;
    };

    $this->owner = ($this->mkUser)(RoleName::Owner->value, 'Owner');
    $this->admin = ($this->mkUser)(RoleName::Admin->value, 'Admin A');
    $this->hr = ($this->mkUser)(RoleName::Hr->value, 'HRD H');
    $this->finance = ($this->mkUser)(RoleName::Finance->value, 'Finance F');
    ($this->mkUser)(RoleName::Teknisi->value, 'Teknisi T');

    $this->omsetJasa = function (int $jasa, string $tanggal = '2026-11-10'): void {
        $order = Order::factory()->create([
            'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => $jasa])->id,
            'status' => OrderStatus::Selesai,
            'tanggal_jadwal' => $tanggal,
        ]);
        Payment::factory()->lunas()->create([
            'order_id' => $order->id,
            'total_tagihan' => $jasa,
            'tanggal_bayar' => $tanggal,
            'omset_jasa' => $jasa,
            'omset_material' => 0,
        ]);
    };

    $this->periode = [CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')->endOfDay()];
});

it('default: 1% omset jasa, minimal Rp 50 jt, penerima Admin/HRD/Finance', function () {
    $s = BonusAdminSetting::saatIni();

    expect((float) $s->persen)->toBe(1.0)
        ->and((float) $s->minimal_omset_jasa)->toBe(50000000.0)
        ->and($s->peranPenerima())->toBe(['admin', 'hr', 'finance']);
});

it('omset jasa di bawah minimal → tidak ada bonus', function () {
    ($this->omsetJasa)(49000000);

    $hasil = app(BonusAdminService::class)->hitung(...$this->periode);

    expect($hasil['memenuhi'])->toBeFalse()
        ->and($hasil['bonus_total'])->toBe(0.0)
        ->and($hasil['kurang'])->toBe(1000000.0)
        ->and(collect($hasil['penerima'])->sum('bonus'))->toBe(0.0);
});

it('omset jasa mencapai minimal → tiap penerima mendapat 1%; teknisi/owner tidak', function () {
    ($this->omsetJasa)(60000000);

    $hasil = app(BonusAdminService::class)->hitung(...$this->periode);

    expect($hasil['memenuhi'])->toBeTrue()
        ->and($hasil['bonus_total'])->toBe(600000.0)
        ->and($hasil['penerima'])->toHaveCount(3)
        ->and(collect($hasil['penerima'])->pluck('bonus')->unique()->all())->toBe([600000.0])
        ->and(collect($hasil['penerima'])->pluck('nama')->all())->toBe(['Admin A', 'Finance F', 'HRD H']);
});

it('tepat di batas minimal sudah memenuhi; mode dibagi rata membagi bonus', function () {
    ($this->omsetJasa)(50000000);
    BonusAdminSetting::saatIni()->update(['mode' => BonusAdminSetting::MODE_DIBAGI_RATA]);

    $hasil = app(BonusAdminService::class)->hitung(...$this->periode);

    expect($hasil['memenuhi'])->toBeTrue()
        ->and($hasil['bonus_total'])->toBe(500000.0)
        ->and($hasil['bonus_per_orang'])->toBe(round(500000 / 3, 2));
});

it('periode di luar omset tidak dihitung, dan nonaktif = tanpa bonus', function () {
    ($this->omsetJasa)(80000000, '2026-10-05');

    $hasil = app(BonusAdminService::class)->hitung(...$this->periode);
    expect($hasil['omset_jasa'])->toBe(0.0);

    ($this->omsetJasa)(80000000);
    BonusAdminSetting::saatIni()->update(['aktif' => false]);

    expect(app(BonusAdminService::class)->hitung(...$this->periode)['bonus_total'])->toBe(0.0);
});

it('halaman: Owner bisa mengubah pengaturan, Finance hanya melihat, teknisi ditolak', function () {
    ($this->omsetJasa)(60000000);

    Livewire::actingAs($this->owner)
        ->test(BonusAdmin::class)
        ->set('dari', '2026-11-01')
        ->set('sampai', '2026-11-30')
        ->assertSee('Memenuhi syarat')
        ->set('persen', '2')
        ->set('minimalOmsetJasa', '70000000')
        ->call('simpan')
        ->assertHasNoErrors();

    $s = BonusAdminSetting::saatIni();
    expect((float) $s->persen)->toBe(2.0)->and((float) $s->minimal_omset_jasa)->toBe(70000000.0);

    Livewire::actingAs($this->finance)
        ->test(BonusAdmin::class)
        ->call('simpan')
        ->assertForbidden();

    Livewire::actingAs(User::role(RoleName::Teknisi->value)->first())
        ->test(BonusAdmin::class)
        ->assertForbidden();
});
