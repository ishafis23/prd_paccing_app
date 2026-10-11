<?php

use App\Enums\DailyAttendanceStatus;
use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveTipe;
use App\Enums\PayrollStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\DailyAttendance;
use App\Models\TechnicianIncentive;
use App\Models\TechnicianOffDay;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use App\Services\PayrollService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Penggajian teknisi (dev-plan/hrd/02).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (RoleName $role): User {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    };

    $this->siapkanProfil = function (User $teknisi): TechnicianPayrollProfile {
        return TechnicianPayrollProfile::create([
            'user_id' => $teknisi->id,
            'tanggal_masuk' => '2024-10-15',
            'gaji_pokok' => 3050000,
            'potongan_bpjs_tk' => 129424,
            'potongan_off_per_hari' => 100000,
            'denda_telat_per_hari' => 7500,
            'aktif' => true,
        ]);
    };
});

it('hitung: gaji bersih = pokok + bonus bulanan + bonus umum + bonus mingguan − pembayaran mingguan − telat − off − bpjs', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $profil = ($this->siapkanProfil)($teknisi);

    // Bonus bulanan (Games 1) + bonus mingguan (Games 5) + entri ditolak (diabaikan).
    TechnicianIncentive::create([
        'user_id' => $teknisi->id, 'tanggal' => '2026-06-20', 'kategori' => IncentiveKategori::Games1Hadir,
        'tipe' => IncentiveTipe::Bonus, 'nominal' => 20000, 'sumber' => 'manual', 'status_verifikasi' => IncentiveStatusVerifikasi::Disetujui,
    ]);
    TechnicianIncentive::create([
        'user_id' => $teknisi->id, 'tanggal' => '2026-06-25', 'kategori' => IncentiveKategori::Games5OmsetTim,
        'tipe' => IncentiveTipe::Bonus, 'nominal' => 50000, 'sumber' => 'manual', 'status_verifikasi' => IncentiveStatusVerifikasi::Disetujui,
    ]);
    TechnicianIncentive::create([
        'user_id' => $teknisi->id, 'tanggal' => '2026-06-26', 'kategori' => IncentiveKategori::Games1Hadir,
        'tipe' => IncentiveTipe::Bonus, 'nominal' => 999000, 'sumber' => 'manual', 'status_verifikasi' => IncentiveStatusVerifikasi::Ditolak,
    ]);

    // Telat 2x (denda), 1x dikecualikan.
    foreach ([['2026-06-16', false], ['2026-06-17', false], ['2026-06-18', true]] as [$tgl, $kecuali]) {
        DailyAttendance::create([
            'user_id' => $teknisi->id, 'tanggal' => $tgl, 'jam_datang' => $tgl.' 08:20:00',
            'status_datang' => DailyAttendanceStatus::Telat, 'dikecualikan_denda' => $kecuali,
        ]);
    }

    // OFF 6 hari (dalam periode) -> kelebihan 1 dari kuota 5.
    foreach (range(16, 21) as $i) {
        TechnicianOffDay::create(['user_id' => $teknisi->id, 'tanggal' => '2026-06-'.$i]);
    }

    $run = app(PayrollService::class)->hitung($profil, Carbon::parse('2026-06-15'), $admin);

    expect($run->periode_selesai->format('Y-m-d'))->toBe('2026-07-14');
    expect((float) $run->bonus_bulanan)->toBe(20000.0);
    expect((float) $run->bonus_mingguan)->toBe(50000.0);
    expect($run->jumlah_hari_telat)->toBe(2);
    expect((float) $run->total_denda_telat)->toBe(15000.0);
    expect($run->jumlah_hari_off)->toBe(6);
    expect((float) $run->total_potongan_off)->toBe(100000.0);
    expect((float) $run->potongan_bpjs_tk)->toBe(129424.0);
    expect((float) $run->gaji_bersih)->toBe(2825576.0);
    expect($run->status)->toBe(PayrollStatus::Draft);

    // Bonus mingguan ditampilkan sekaligus jadi pengurang.
    expect($run->items->where('kategori', 'bonus_mingguan')->count())->toBe(1);
    expect($run->items->where('kategori', 'dibayar_mingguan')->count())->toBe(1);
});

it('bonus umum diubah admin & dihitung ulang', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $profil = ($this->siapkanProfil)($teknisi);

    $run = app(PayrollService::class)->hitung($profil, Carbon::parse('2026-06-15'), $admin);
    $run = app(PayrollService::class)->setBonusUmum($run, 400000, $admin);

    expect((float) $run->bonus_umum)->toBe(400000.0);
    expect((float) $run->gaji_bersih)->toBe(3050000.0 + 400000.0 - 129424.0);
});

it('ACC lalu bayar; tidak bisa hitung ulang setelah disetujui', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $profil = ($this->siapkanProfil)($teknisi);

    $svc = app(PayrollService::class);
    $run = $svc->hitung($profil, Carbon::parse('2026-06-15'), $admin);

    $run = $svc->acc($run, $admin);
    expect($run->status)->toBe(PayrollStatus::Disetujui);

    expect(fn () => $svc->hitung($profil, Carbon::parse('2026-06-15'), $admin))
        ->toThrow(BusinessRuleException::class, 'sudah disetujui');

    $run = $svc->bayar($run, $admin);
    expect($run->status)->toBe(PayrollStatus::Dibayar);
});

it('bayar menolak bila belum di-ACC', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $profil = ($this->siapkanProfil)($teknisi);

    $svc = app(PayrollService::class);
    $run = $svc->hitung($profil, Carbon::parse('2026-06-15'), $admin);

    expect(fn () => $svc->bayar($run, $admin))->toThrow(BusinessRuleException::class, 'ACC gaji dulu');
});

it('hanya Owner/HR/Admin yang boleh menghitung gaji', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $profil = ($this->siapkanProfil)($teknisi);
    $finance = ($this->mkUser)(RoleName::Finance);

    expect(fn () => app(PayrollService::class)->hitung($profil, Carbon::parse('2026-06-15'), $finance))
        ->toThrow(AuthorizationException::class);
});

it('slip hanya tampil setelah ACC & PDF ter-generate', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $profil = ($this->siapkanProfil)($teknisi);

    $svc = app(PayrollService::class);
    $run = $svc->hitung($profil, Carbon::parse('2026-06-15'), $admin);

    $this->actingAs($admin)->get(route('payroll.slip', $run))->assertNotFound();

    $svc->acc($run, $admin);

    $this->actingAs($admin)->get(route('payroll.slip', $run->fresh()))
        ->assertOk()
        ->assertSee($teknisi->name);

    $this->actingAs($admin)->get(route('payroll.slip.pdf', $run->fresh()))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
