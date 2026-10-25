<?php

use App\Enums\RoleName;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * Cut-off periode gaji per teknisi (dev-plan/29 A1).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $this->profilDengan = function (?int $cutoff): TechnicianPayrollProfile {
        $teknisi = User::factory()->create();
        $teknisi->assignRole(RoleName::Teknisi->value);

        return TechnicianPayrollProfile::create([
            'user_id' => $teknisi->id,
            'tanggal_masuk' => '2024-10-15',
            'cutoff_hari' => $cutoff,
            'gaji_pokok' => 3000000,
            'potongan_bpjs_tk' => 0,
            'potongan_off_per_hari' => 100000,
            'denda_telat_per_hari' => 7500,
            'aktif' => true,
        ]);
    };
});

it('cut-off 23 → periode 24 bulan lalu s.d. 23 bulan berjalan yang memuat acuan', function (string $acuan, string $awal, string $akhir) {
    $profil = ($this->profilDengan)(23);

    [$mulai, $selesai] = app(PayrollService::class)->rentangPeriodeProfil($profil, Carbon::parse($acuan));

    expect($mulai->toDateString())->toBe($awal)
        ->and($selesai->toDateString())->toBe($akhir);
})->with([
    'awal bulan' => ['2026-10-01', '2026-09-24', '2026-10-23'],
    'hari pertama periode' => ['2026-09-24', '2026-09-24', '2026-10-23'],
    'hari terakhir periode' => ['2026-09-23', '2026-08-24', '2026-09-23'],
    'lewat cut-off' => ['2026-10-24', '2026-10-24', '2026-11-23'],
]);

it('tanpa cut-off tetap memakai perilaku lama (1 bulan dari acuan)', function () {
    $profil = ($this->profilDengan)(null);

    [$mulai, $selesai] = app(PayrollService::class)->rentangPeriodeProfil($profil, Carbon::parse('2026-06-15'));

    expect($mulai->toDateString())->toBe('2026-06-15')
        ->and($selesai->toDateString())->toBe('2026-07-14');
});

it('cut-off 31 di bulan Februari tidak error dan memakai akhir bulan', function () {
    $profil = ($this->profilDengan)(31);

    [$mulai, $selesai] = app(PayrollService::class)->rentangPeriodeProfil($profil, Carbon::parse('2026-02-15'));

    expect($mulai->toDateString())->toBe('2026-02-01')
        ->and($selesai->toDateString())->toBe('2026-02-28');
});

it('hitung() dengan cut-off 23 membuat periode 24 Sep – 23 Okt', function () {
    $profil = ($this->profilDengan)(23);

    $run = app(PayrollService::class)->hitung($profil, Carbon::parse('2026-10-01'), $this->admin);

    expect($run->periode_mulai->toDateString())->toBe('2026-09-24')
        ->and($run->periode_selesai->toDateString())->toBe('2026-10-23');
});
