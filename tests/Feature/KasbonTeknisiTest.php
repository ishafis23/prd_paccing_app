<?php

use App\Enums\AdvanceStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\PayrollRun;
use App\Models\TechnicianAdvance;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * Kasbon teknisi (dev-plan/28) — terhubung ke perhitungan gaji.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);

    $this->profil = TechnicianPayrollProfile::create([
        'user_id' => $this->teknisi->id,
        'tanggal_masuk' => '2024-10-15',
        'gaji_pokok' => 3000000,
        'potongan_bpjs_tk' => 0,
        'potongan_off_per_hari' => 100000,
        'denda_telat_per_hari' => 7500,
        'aktif' => true,
    ]);

    $this->kasbon = fn (array $o = []) => TechnicianAdvance::create($o + [
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-06-10',
        'nominal' => 500000,
        'dicatat_oleh' => $this->admin->id,
    ]);
});

it('kasbon tanpa cicilan dipotong lunas sekaligus dari gaji', function () {
    ($this->kasbon)();

    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);

    expect((float) $run->total_potongan_kasbon)->toBe(500000.0);
    expect((float) $run->gaji_bersih)->toBe(2500000.0);
    expect($run->items->where('kategori', 'kasbon')->count())->toBe(1);
});

it('cicilan membatasi potongan per periode; sisa terbawa ke periode berikutnya', function () {
    $kasbon = ($this->kasbon)(['cicilan_per_periode' => 200000]);
    $svc = app(PayrollService::class);

    $run1 = $svc->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);
    expect((float) $run1->total_potongan_kasbon)->toBe(200000.0);

    $svc->acc($run1, $this->admin);
    expect($kasbon->fresh()->sisa())->toBe(300000.0);

    $run2 = $svc->hitung($this->profil, Carbon::parse('2026-07-15'), $this->admin);
    expect((float) $run2->total_potongan_kasbon)->toBe(200000.0);
    $svc->acc($run2, $this->admin);

    $run3 = $svc->hitung($this->profil, Carbon::parse('2026-08-15'), $this->admin);
    expect((float) $run3->total_potongan_kasbon)->toBe(100000.0);
    $svc->acc($run3, $this->admin);

    expect($kasbon->fresh()->status)->toBe(AdvanceStatus::Lunas);
});

it('hitung ulang draft tidak menggandakan potongan kasbon', function () {
    ($this->kasbon)();
    $svc = app(PayrollService::class);

    $svc->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);
    $run = $svc->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);

    expect((float) $run->total_potongan_kasbon)->toBe(500000.0);
    expect($run->items->where('kategori', 'kasbon')->count())->toBe(1);
});

it('kasbon tidak membuat gaji negatif — sisanya terbawa', function () {
    $kasbon = ($this->kasbon)(['nominal' => 5000000]);

    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);
    expect((float) $run->total_potongan_kasbon)->toBe(3000000.0);
    expect((float) $run->gaji_bersih)->toBe(0.0);

    app(PayrollService::class)->acc($run, $this->admin);
    expect($kasbon->fresh()->sisa())->toBe(2000000.0)
        ->and($kasbon->fresh()->status)->toBe(AdvanceStatus::Aktif);
});

it('draft otomatis tersegarkan saat kasbon ditambah', function () {
    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);
    expect((float) $run->gaji_bersih)->toBe(3000000.0);

    ($this->kasbon)(['tanggal' => '2026-06-20', 'nominal' => 400000]);

    expect((float) $run->fresh()->total_potongan_kasbon)->toBe(400000.0);
    expect((float) $run->fresh()->gaji_bersih)->toBe(2600000.0);
});

it('kasbon setelah akhir periode tidak ikut dipotong di periode itu', function () {
    ($this->kasbon)(['tanggal' => '2026-07-20']);

    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);

    expect((float) $run->total_potongan_kasbon)->toBe(0.0);
});

it('ACC ditolak bila kasbon berubah sejak gaji dihitung', function () {
    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-06-15'), $this->admin);

    // Ubah langsung tanpa event (simulasi data berubah di luar jalur normal).
    TechnicianAdvance::withoutEvents(fn () => ($this->kasbon)(['tanggal' => '2026-06-20']));

    expect(fn () => app(PayrollService::class)->acc($run, $this->admin))
        ->toThrow(BusinessRuleException::class, 'hitung ulang');
    expect($run->fresh()->draft())->toBeTrue();
});

it('halaman admin Kasbon bisa diakses admin', function () {
    ($this->kasbon)();

    $this->actingAs($this->admin)->get('/admin/technician-advances')->assertOk();
});
