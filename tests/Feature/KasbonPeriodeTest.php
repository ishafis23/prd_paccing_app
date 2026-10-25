<?php

use App\Enums\AdvanceStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\TechnicianAdvance;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * Kasbon mengikuti periode gaji teknisi (dev-plan/29 A2). Kasus Bayu: cut-off 23.
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
        'cutoff_hari' => 23,
        'gaji_pokok' => 3000000,
        'potongan_bpjs_tk' => 0,
        'potongan_off_per_hari' => 100000,
        'denda_telat_per_hari' => 7500,
        'aktif' => true,
    ]);

    $this->kasbon = fn (array $o = []) => TechnicianAdvance::create($o + [
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-19',
        'nominal' => 500000,
        'dicatat_oleh' => $this->admin->id,
    ]);
});

it('kasbon 19 Sep dipotong penuh di periode 24 Agu – 23 Sep', function () {
    ($this->kasbon)();

    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-09-01'), $this->admin);

    expect($run->periode_mulai->toDateString())->toBe('2026-08-24')
        ->and((float) $run->total_potongan_kasbon)->toBe(500000.0);
});

it('kasbon 19 Sep tidak ikut terpotong di periode 24 Sep – 23 Okt', function () {
    ($this->kasbon)();

    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-10-01'), $this->admin);

    expect((float) $run->total_potongan_kasbon)->toBe(0.0)
        ->and($run->items->where('kategori', 'kasbon')->count())->toBe(0);
});

it('kasbon periode mendatang tidak dipotong di periode yang sedang dihitung', function () {
    ($this->kasbon)(['tanggal' => '2026-10-10']); // periode 24 Sep – 23 Okt

    $run = app(PayrollService::class)->hitung($this->profil, Carbon::parse('2026-09-01'), $this->admin);

    expect((float) $run->total_potongan_kasbon)->toBe(0.0);
});

it('kasbon yang terlewat periodenya tidak dipotong lagi dan tampil sebagai belum terpotong', function () {
    $svc = app(PayrollService::class);

    // Run 24 Agu – 23 Sep di-ACC lebih dulu, baru kasbon 19 Sep dicatat.
    $lama = $svc->hitung($this->profil, Carbon::parse('2026-09-01'), $this->admin);
    $svc->acc($lama, $this->admin);
    $kasbon = ($this->kasbon)();

    $run = $svc->hitung($this->profil, Carbon::parse('2026-10-01'), $this->admin);

    expect((float) $run->total_potongan_kasbon)->toBe(0.0)
        ->and($run->items->where('kategori', 'kasbon_belum_terpotong')->count())->toBe(1)
        ->and((float) $run->gaji_bersih)->toBe(3000000.0);

    Carbon::setTestNow('2026-10-25');
    $terlewat = $svc->kasbonBelumTerpotongLewatPeriode($this->teknisi->id);
    Carbon::setTestNow();

    expect($terlewat->pluck('id')->all())->toBe([$kasbon->id]);
});

it('lunasiManual sebagian mengurangi sisa tanpa payroll_run_id, penuh → Lunas', function () {
    $kasbon = ($this->kasbon)();
    $svc = app(PayrollService::class);

    $svc->lunasiManual($kasbon, 200000, 'Sudah dipotong di gaji Sep (di luar sistem)', $this->admin);

    $kasbon = $kasbon->fresh();
    $pelunasan = $kasbon->pelunasan()->first();

    expect($kasbon->sisa())->toBe(300000.0)
        ->and($kasbon->status)->toBe(AdvanceStatus::Aktif)
        ->and($pelunasan->payroll_run_id)->toBeNull()
        ->and($pelunasan->dicatat_oleh)->toBe($this->admin->id);

    $svc->lunasiManual($kasbon, 300000, 'Pelunasan sisa', $this->admin);

    expect($kasbon->fresh()->status)->toBe(AdvanceStatus::Lunas);
});

it('lunasiManual menolak nominal melebihi sisa, nol, dan catatan kosong', function () {
    $kasbon = ($this->kasbon)();
    $svc = app(PayrollService::class);

    expect(fn () => $svc->lunasiManual($kasbon, 600000, 'x', $this->admin))->toThrow(BusinessRuleException::class);
    expect(fn () => $svc->lunasiManual($kasbon, 0, 'x', $this->admin))->toThrow(BusinessRuleException::class);
    expect(fn () => $svc->lunasiManual($kasbon, 100000, '  ', $this->admin))->toThrow(BusinessRuleException::class);
});

it('kasbon multi-cicilan yang sudah pernah dipotong tetap dicicil di periode berikutnya', function () {
    $kasbon = ($this->kasbon)(['nominal' => 600000, 'cicilan_per_periode' => 200000]);
    $svc = app(PayrollService::class);

    $run1 = $svc->hitung($this->profil, Carbon::parse('2026-09-01'), $this->admin); // 24 Agu – 23 Sep
    expect((float) $run1->total_potongan_kasbon)->toBe(200000.0);
    $svc->acc($run1, $this->admin);

    $run2 = $svc->hitung($this->profil, Carbon::parse('2026-10-01'), $this->admin); // 24 Sep – 23 Okt
    expect((float) $run2->total_potongan_kasbon)->toBe(200000.0);
    $svc->acc($run2, $this->admin);

    $run3 = $svc->hitung($this->profil, Carbon::parse('2026-11-01'), $this->admin);
    expect((float) $run3->total_potongan_kasbon)->toBe(200000.0);
    $svc->acc($run3, $this->admin);

    expect($kasbon->fresh()->status)->toBe(AdvanceStatus::Lunas);
});
