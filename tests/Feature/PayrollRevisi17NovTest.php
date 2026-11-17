<?php

use App\Enums\PayrollItemJenis;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\PayrollRun;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * Revisi 17 Nov 2026 poin 28 (edit periode + hapus gaji draft) dan
 * poin 29 (bonus / potongan lain berulang dengan catatan).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $this->teknisi = $teknisi;

    $this->profil = TechnicianPayrollProfile::create([
        'user_id' => $teknisi->id,
        'tanggal_masuk' => '2024-10-15',
        'cutoff_hari' => null,
        'gaji_pokok' => 2600000,
        'potongan_bpjs_tk' => 0,
        'potongan_off_per_hari' => 100000,
        'denda_telat_per_hari' => 7500,
        'aktif' => true,
    ]);

    $this->svc = app(PayrollService::class);
});

it('edit periode memindahkan tanggal dan Hitung Ulang tetap memakai periode baru', function () {
    $run = $this->svc->hitung($this->profil, Carbon::parse('2026-10-15'), $this->admin);

    $baru = $this->svc->ubahPeriode($run, Carbon::parse('2026-10-16'), Carbon::parse('2026-11-15'), $this->admin);

    expect($baru->id)->toBe($run->id)
        ->and($baru->periode_mulai->toDateString())->toBe('2026-10-16')
        ->and($baru->periode_selesai->toDateString())->toBe('2026-11-15');

    $ulang = $this->svc->hitungUlang($baru, $this->admin);

    expect($ulang->id)->toBe($run->id)
        ->and($ulang->periode_selesai->toDateString())->toBe('2026-11-15')
        ->and(PayrollRun::count())->toBe(1);
});

it('edit periode ditolak bila tumpang tindih dengan gaji lain teknisi yang sama', function () {
    $a = $this->svc->hitung($this->profil, Carbon::parse('2026-10-15'), $this->admin);
    $b = $this->svc->hitung($this->profil, Carbon::parse('2026-11-15'), $this->admin);

    expect(fn () => $this->svc->ubahPeriode($b, Carbon::parse('2026-11-10'), Carbon::parse('2026-12-09'), $this->admin))
        ->toThrow(BusinessRuleException::class, 'tumpang tindih');
});

it('gaji draft salah input bisa dihapus, yang sudah ACC tidak', function () {
    $run = $this->svc->hitung($this->profil, Carbon::parse('2026-10-15'), $this->admin);
    $this->svc->tambahPenyesuaian($run, PayrollItemJenis::Bonus, 'Game 5', 50000, $this->admin);

    $this->svc->hapus($run, $this->admin);

    expect(PayrollRun::count())->toBe(0);

    $acc = $this->svc->hitung($this->profil, Carbon::parse('2026-10-15'), $this->admin);
    $this->svc->acc($acc, $this->admin);

    expect(fn () => $this->svc->hapus($acc->fresh(), $this->admin))->toThrow(BusinessRuleException::class);
    expect(fn () => $this->svc->ubahPeriode($acc->fresh(), Carbon::parse('2026-10-01'), Carbon::parse('2026-10-31'), $this->admin))
        ->toThrow(BusinessRuleException::class);
});

it('bonus dan potongan lain bisa diinput berkali-kali, masuk gaji bersih & rincian, dan bertahan saat hitung ulang', function () {
    $run = $this->svc->hitung($this->profil, Carbon::parse('2026-10-15'), $this->admin);
    $dasar = (float) $run->gaji_bersih;

    $this->svc->tambahPenyesuaian($run, PayrollItemJenis::Potongan, 'Ganti pipa', 240000, $this->admin, '2026-10-20', 'pipa bocor di Kost A');
    $this->svc->tambahPenyesuaian($run, PayrollItemJenis::Potongan, 'Galon', 10000, $this->admin);
    $run = $this->svc->tambahPenyesuaian($run, PayrollItemJenis::Bonus, 'Game 5', 80000, $this->admin, '2026-11-04')->payrollRun->fresh('items');

    expect((float) $run->total_potongan_lain)->toBe(250000.0)
        ->and((float) $run->total_bonus_lain)->toBe(80000.0)
        ->and((float) $run->gaji_bersih)->toBe($dasar - 250000 + 80000)
        ->and($run->items->where('kategori', 'potongan_lain'))->toHaveCount(2)
        ->and($run->items->where('kategori', 'bonus_lain'))->toHaveCount(1);

    $ulang = $this->svc->hitungUlang($run, $this->admin);

    expect((float) $ulang->gaji_bersih)->toBe($dasar - 250000 + 80000)
        ->and($ulang->adjustments)->toHaveCount(3);

    $this->svc->hapusPenyesuaian($ulang->adjustments->firstWhere('keterangan', 'Ganti pipa'), $this->admin);

    expect((float) $run->fresh()->gaji_bersih)->toBe($dasar - 10000 + 80000);
});

it('bonus/potongan lain butuh keterangan, nominal positif, dan status draft', function () {
    $run = $this->svc->hitung($this->profil, Carbon::parse('2026-10-15'), $this->admin);

    expect(fn () => $this->svc->tambahPenyesuaian($run, PayrollItemJenis::Bonus, '  ', 1000, $this->admin))
        ->toThrow(BusinessRuleException::class);
    expect(fn () => $this->svc->tambahPenyesuaian($run, PayrollItemJenis::Bonus, 'x', 0, $this->admin))
        ->toThrow(BusinessRuleException::class);

    $this->svc->acc($run, $this->admin);

    expect(fn () => $this->svc->tambahPenyesuaian($run->fresh(), PayrollItemJenis::Bonus, 'x', 1000, $this->admin))
        ->toThrow(BusinessRuleException::class);
});

it('rincian & slip menampilkan bonus/potongan lain', function () {
    $run = $this->svc->hitung($this->profil, Carbon::parse('2026-10-15'), $this->admin);
    $this->svc->tambahPenyesuaian($run, PayrollItemJenis::Potongan, 'Ganti pipa', 240000, $this->admin, '2026-10-20', 'pipa bocor');

    $html = view('payroll._rincian', ['run' => $run->fresh(['items', 'adjustments'])])->render();

    expect($html)->toContain('Ganti pipa')->toContain('240.000')->toContain('pipa bocor');
});
