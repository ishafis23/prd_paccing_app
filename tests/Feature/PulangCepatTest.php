<?php

use App\Enums\EarlyLeaveStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\TechnicianEarlyLeave;
use App\Models\TechnicianPayrollProfile;
use App\Models\User;
use App\Services\EarlyLeaveService;
use App\Services\PayrollService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

/**
 * Pulang cepat teknisi (dev-plan/hrd/02) — pengajuan teknisi, verifikasi HRD,
 * & integrasi setengah hari ke penggajian.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (RoleName $role): User {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    };
});

it('teknisi mengajukan pulang cepat; ajukan ulang tanggal sama menimpa (bukan ganda)', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $svc = app(EarlyLeaveService::class);

    $svc->ajukan($teknisi, '2026-06-20', '13:00', 'urusan keluarga');
    $ulang = $svc->ajukan($teknisi, '2026-06-20', '14:30', 'koreksi jam');

    expect(TechnicianEarlyLeave::where('user_id', $teknisi->id)->count())->toBe(1);
    expect($ulang->status)->toBe(EarlyLeaveStatus::Menunggu);
    expect($ulang->alasan)->toBe('koreksi jam');
});

it('menolak pengajuan tanggal di masa depan', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi);

    expect(fn () => app(EarlyLeaveService::class)->ajukan($teknisi, now()->addDay()->toDateString(), '13:00'))
        ->toThrow(BusinessRuleException::class, 'masa depan');
});

it('HRD dapat menyetujui / menolak; non-HRD tidak', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi);
    $hr = ($this->mkUser)(RoleName::Hr);
    $finance = ($this->mkUser)(RoleName::Finance);

    $entry = app(EarlyLeaveService::class)->ajukan($teknisi, '2026-06-20', '13:00');

    expect(fn () => app(EarlyLeaveService::class)->setujui($entry, $finance))
        ->toThrow(AuthorizationException::class);

    $entry = app(EarlyLeaveService::class)->setujui($entry, $hr, 'OK');
    expect($entry->status)->toBe(EarlyLeaveStatus::Disetujui);
    expect($entry->diverifikasi_oleh)->toBe($hr->id);

    $entry = app(EarlyLeaveService::class)->tolak($entry, $hr, 'bukan alasan kuat');
    expect($entry->status)->toBe(EarlyLeaveStatus::Ditolak);
});

it('penggajian menghitung pulang cepat disetujui sebagai setengah hari', function () {
    $admin = ($this->mkUser)(RoleName::Admin);
    $teknisi = ($this->mkUser)(RoleName::Teknisi);

    $profil = TechnicianPayrollProfile::create([
        'user_id' => $teknisi->id,
        'tanggal_masuk' => '2024-10-15',
        'gaji_pokok' => 3000000,
        'potongan_bpjs_tk' => 0,
        'potongan_off_per_hari' => 100000,
        'denda_telat_per_hari' => 7500,
        'aktif' => true,
    ]);

    // 1 disetujui, 1 masih menunggu (tidak dihitung).
    TechnicianEarlyLeave::create([
        'user_id' => $teknisi->id, 'tanggal' => '2026-06-18', 'jam_pulang' => '13:00:00',
        'status' => EarlyLeaveStatus::Disetujui,
    ]);
    TechnicianEarlyLeave::create([
        'user_id' => $teknisi->id, 'tanggal' => '2026-06-19', 'jam_pulang' => '12:00:00',
        'status' => EarlyLeaveStatus::Menunggu,
    ]);

    $run = app(PayrollService::class)->hitung($profil, Carbon::parse('2026-06-15'), $admin);

    expect($run->jumlah_setengah_hari)->toBe(1);
    expect((float) $run->total_potongan_setengah_hari)->toBe(50000.0);
    expect((float) $run->gaji_bersih)->toBe(2950000.0);
    expect($run->items->where('kategori', 'setengah_hari')->count())->toBe(1);
});

it('halaman teknisi pulang cepat bisa diakses teknisi', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi);

    $this->actingAs($teknisi)->get(route('teknisi.pulang-cepat'))
        ->assertOk()
        ->assertSee('Ajukan Pulang Cepat');
});
