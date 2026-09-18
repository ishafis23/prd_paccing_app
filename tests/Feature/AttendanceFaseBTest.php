<?php

use App\Enums\DailyAttendanceStatus;
use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveTipe;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\DailyAttendanceResource;
use App\Filament\Resources\DailyAttendanceResource\Pages\ListDailyAttendances;
use App\Filament\Resources\TechnicianIncentiveResource;
use App\Filament\Resources\TechnicianIncentiveResource\Pages\ListTechnicianIncentives;
use App\Livewire\Teknisi\AbsensiScan;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Services\AttendanceCodeService;
use App\Services\AttendanceService;
use App\Services\AttendanceSettingService;
use App\Services\StorageQuotaService;
use App\Services\TechnicianIncentiveService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    AttendanceSettingService::lupakanCache();

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->admin = ($this->mkUser)(RoleName::Admin->value);
    $this->teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $this->kode = app(AttendanceCodeService::class)->buatBaru($this->admin)->kode;
});

afterEach(function () {
    Carbon::setTestNow();
});

// --- TechnicianIncentiveService -------------------------------------------

it('catat idempoten: dipanggil ulang di kategori/tanggal sama memperbarui, bukan menggandakan', function () {
    $service = app(TechnicianIncentiveService::class);
    $tanggal = Carbon::today();

    $service->catat($this->teknisi, $tanggal, IncentiveKategori::Games1Hadir, IncentiveTipe::Bonus, 7500);
    $service->catat($this->teknisi, $tanggal, IncentiveKategori::Games1Hadir, IncentiveTipe::Bonus, 8000);

    expect(TechnicianIncentive::query()->count())->toBe(1)
        ->and((float) TechnicianIncentive::first()->nominal)->toBe(8000.0);
});

it('catat tanpa foto langsung disetujui; dengan foto menunggu verifikasi (B55)', function () {
    $service = app(TechnicianIncentiveService::class);
    $tanggal = Carbon::today();

    $tanpaFoto = $service->catat($this->teknisi, $tanggal, IncentiveKategori::DendaTelat, IncentiveTipe::Denda, 7500);
    $denganFoto = $service->catat($this->teknisi, $tanggal, IncentiveKategori::Games1Hadir, IncentiveTipe::Bonus, 7500, 'absensi/foto.jpg');

    expect($tanpaFoto->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Disetujui)
        ->and($denganFoto->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Menunggu);
});

it('catatManual hanya boleh role pengelola', function () {
    app(TechnicianIncentiveService::class)->catatManual(
        $this->teknisi,
        Carbon::today(),
        IncentiveKategori::Games5OmsetTim,
        IncentiveTipe::Bonus,
        40000,
        $this->teknisi,
    );
})->throws(AuthorizationException::class);

it('setujui & tolak mengubah status_verifikasi dan mencatat verifikator', function () {
    $service = app(TechnicianIncentiveService::class);
    $entry = $service->catat($this->teknisi, Carbon::today(), IncentiveKategori::Games1Hadir, IncentiveTipe::Bonus, 7500, 'absensi/foto.jpg');

    $disetujui = $service->setujui($entry, $this->admin);
    expect($disetujui->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Disetujui)
        ->and($disetujui->diverifikasi_oleh)->toBe($this->admin->id);

    $ditolak = $service->tolak($entry, $this->admin, 'Foto tidak jelas');
    expect($ditolak->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Ditolak)
        ->and($ditolak->catatan)->toBe('Foto tidak jelas');
});

it('hitungTitikHarian menghitung order ditutup hari itu (PIC & anggota tim)', function () {
    $rekan = ($this->mkUser)(RoleName::Teknisi->value);
    Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));

    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now()]);
    $order2 = Order::factory()->create(['teknisi_id' => $rekan->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now()]);
    OrderTechnician::create(['order_id' => $order2->id, 'teknisi_id' => $this->teknisi->id]);
    // Order ditutup di hari lain -> tidak dihitung.
    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now()->subDay()]);

    $titik = app(TechnicianIncentiveService::class)->hitungTitikHarian($this->teknisi, Carbon::today());

    expect($titik)->toBe(2);
});

it('tentukanModeJalan: sendiri (1 teknisi), berdua (2 teknisi), null bila campur/tidak ada titik', function () {
    $rekan = ($this->mkUser)(RoleName::Teknisi->value);
    Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));
    $service = app(TechnicianIncentiveService::class);

    expect($service->tentukanModeJalan($this->teknisi, Carbon::today()))->toBeNull();

    $orderSolo = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now()]);
    expect($service->tentukanModeJalan($this->teknisi, Carbon::today()))->toBe('sendiri');

    $orderBerdua = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now()]);
    OrderTechnician::create(['order_id' => $orderBerdua->id, 'teknisi_id' => $rekan->id]);

    expect($service->tentukanModeJalan($this->teknisi, Carbon::today()))->toBeNull(); // campur solo & berdua
});

// --- AttendanceService::catatDatang ----------------------------------------

it('catatDatang menolak kode tidak valid', function () {
    app(AttendanceService::class)->catatDatang($this->teknisi, 'kode-ngawur', UploadedFile::fake()->image('a.jpg'));
})->throws(BusinessRuleException::class, 'tidak berlaku');

it('catatDatang sebelum jam_games1_batas -> status Bonus & ledger games1_hadir', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 07:20:00'));

    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    expect($absen->status_datang)->toBe(DailyAttendanceStatus::Bonus)
        ->and(Storage::disk('public')->exists($absen->foto_datang))->toBeTrue();

    $entry = TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games1Hadir->value)->first();
    expect($entry)->not->toBeNull()
        ->and((float) $entry->nominal)->toBe(7500.0)
        ->and($entry->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Menunggu);
});

it('catatDatang di jendela normal -> tidak ada bonus atau denda', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 07:50:00'));

    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    expect($absen->status_datang)->toBe(DailyAttendanceStatus::Normal)
        ->and(TechnicianIncentive::query()->count())->toBe(0);
});

it('catatDatang setelah jam_normal_selesai tanpa toleransi -> status Telat & ledger denda_telat', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 08:30:00'));

    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    expect($absen->status_datang)->toBe(DailyAttendanceStatus::Telat);

    $denda = TechnicianIncentive::query()->where('kategori', IncentiveKategori::DendaTelat->value)->first();
    expect($denda)->not->toBeNull()
        ->and($denda->tipe)->toBe(IncentiveTipe::Denda)
        ->and((float) $denda->nominal)->toBe(7500.0)
        ->and($denda->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Disetujui); // tanpa foto -> auto-disetujui
});

it('catatDatang telat TAPI kemarin lembur (jam_pulang >= 20:00) -> toleransi, tidak didenda', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 20:15:00'));
    DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-16',
        'jam_datang' => Carbon::parse('2026-09-16 07:30:00'),
        'jam_pulang' => Carbon::parse('2026-09-16 20:15:00'),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-17 09:00:00'));
    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    expect($absen->status_datang)->toBe(DailyAttendanceStatus::TelatToleransi)
        ->and(TechnicianIncentive::query()->where('kategori', IncentiveKategori::DendaTelat->value)->exists())->toBeFalse();
});

it('catatDatang lewat batas toleransi (>10:00) meski kemarin lembur -> tetap Telat & didenda', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 21:00:00'));
    DailyAttendance::create([
        'user_id' => $this->teknisi->id,
        'tanggal' => '2026-09-16',
        'jam_datang' => Carbon::parse('2026-09-16 07:30:00'),
        'jam_pulang' => Carbon::parse('2026-09-16 21:00:00'),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-09-17 10:30:00'));
    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    expect($absen->status_datang)->toBe(DailyAttendanceStatus::Telat)
        ->and(TechnicianIncentive::query()->where('kategori', IncentiveKategori::DendaTelat->value)->exists())->toBeTrue();
});

it('catatDatang menolak absen kedua di hari yang sama', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 07:30:00'));
    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('b.jpg'));
})->throws(BusinessRuleException::class, 'sudah absen datang');

it('catatDatang ditolak saat penyimpanan penuh (guard kuota B25)', function () {
    config(['penyimpanan.kuota_mb' => 1]);
    StorageQuotaService::lupakanCache();
    Storage::disk('public')->put('work-reports/padat.bin', str_repeat('x', 900 * 1024));

    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('besar.jpg')->size(300));
})->throws(BusinessRuleException::class, 'Penyimpanan');

// --- AttendanceService::catatPulang ----------------------------------------

it('catatPulang menolak sebelum absen datang', function () {
    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('a.jpg'));
})->throws(BusinessRuleException::class, 'Absen datang dulu');

it('catatPulang menolak absen pulang kedua', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 07:30:00'));
    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));
    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('b.jpg'));

    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('c.jpg'));
})->throws(BusinessRuleException::class, 'sudah absen pulang');

it('catatPulang menulis ledger games4_kepulangan saat titik & jam memenuhi ambang (sendiri)', function () {
    app(AttendanceSettingService::class)->perbarui(['minimal_titik_sendiri' => 1], $this->admin);

    Carbon::setTestNow(Carbon::parse('2026-09-17 07:30:00'));
    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Selesai, 'ditutup_pada' => now()]);

    Carbon::setTestNow(Carbon::parse('2026-09-17 17:00:00'));
    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('b.jpg'));

    $games4 = TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games4Kepulangan->value)->first();
    expect($games4)->not->toBeNull()
        ->and((float) $games4->nominal)->toBe(25000.0); // nominal_games4_sendiri default
});

it('catatPulang TIDAK menulis games4 saat titik kurang dari ambang', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 07:30:00'));
    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    // Default minimal_titik_sendiri = 5, tidak ada order ditutup sama sekali.
    Carbon::setTestNow(Carbon::parse('2026-09-17 17:00:00'));
    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('b.jpg'));

    expect(TechnicianIncentive::query()->where('kategori', IncentiveKategori::Games4Kepulangan->value)->exists())->toBeFalse();
});

// --- AttendanceService::kecualikanDenda (B45) ------------------------------

it('kecualikanDenda menolak entri denda_telat & tercatat di baris absensi', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 08:30:00'));
    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    $diperbarui = app(AttendanceService::class)->kecualikanDenda($absen, true, $this->admin, 'Macet parah');

    expect($diperbarui->dikecualikan_denda)->toBeTrue()
        ->and($diperbarui->catatan_admin)->toBe('Macet parah');

    $denda = TechnicianIncentive::query()->where('kategori', IncentiveKategori::DendaTelat->value)->first();
    expect($denda->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Ditolak);
});

it('kecualikanDenda hanya boleh role pengelola', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 08:30:00'));
    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    app(AttendanceService::class)->kecualikanDenda($absen, true, $this->teknisi);
})->throws(AuthorizationException::class);

// --- Livewire: AbsensiScan --------------------------------------------------

it('AbsensiScan: kode tidak valid & belum absen -> state kode_invalid', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class, ['kode' => 'ngawur'])
        ->assertSet('state', 'kode_invalid');
});

it('AbsensiScan: tanpa kode & belum absen -> state perlu_kode', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->assertSet('state', 'perlu_kode');
});

it('AbsensiScan: state perlu_kode menampilkan tombol Scan Kode QR (kamera in-app)', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->assertSee('Scan Kode QR')
        ->assertSeeHtml('x-data="qrScanner(');
});

it('AbsensiScan: input foto datang/pulang/cuci motor dipaksa buka kamera, bukan galeri', function () {
    // perlu_kode: cuma form Cuci Motor yg tampil (foto motor -> kamera belakang).
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->assertSeeHtml('capture="environment"');

    // siap_datang: foto bukti kehadiran -> kamera depan (selfie).
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class, ['kode' => $this->kode])
        ->assertSeeHtml('capture="user"');

    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    // siap_pulang: sama, foto pulang -> kamera depan.
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->assertSeeHtml('capture="user"');
});

it('AbsensiScan: kode valid & belum absen -> catatDatang lewat form berhasil', function () {
    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class, ['kode' => $this->kode])
        ->assertSet('state', 'siap_datang')
        ->set('foto', UploadedFile::fake()->image('datang.jpg'))
        ->call('catatDatang')
        ->assertHasNoErrors();

    expect(DailyAttendance::query()->where('user_id', $this->teknisi->id)->whereNotNull('jam_datang')->exists())->toBeTrue();
});

it('AbsensiScan: sudah datang -> state siap_pulang, catatPulang berhasil', function () {
    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->assertSet('state', 'siap_pulang')
        ->set('foto', UploadedFile::fake()->image('pulang.jpg'))
        ->call('catatPulang')
        ->assertHasNoErrors();

    expect(DailyAttendance::query()->where('user_id', $this->teknisi->id)->whereNotNull('jam_pulang')->exists())->toBeTrue();
});

it('AbsensiScan: sudah lengkap -> state selesai', function () {
    app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));
    app(AttendanceService::class)->catatPulang($this->teknisi, UploadedFile::fake()->image('b.jpg'));

    Livewire::actingAs($this->teknisi)
        ->test(AbsensiScan::class)
        ->assertSet('state', 'selesai');
});

// --- Filament resources -----------------------------------------------------

it('Rekap Absensi & Rekap Insentif hanya utk Owner/Admin/HR/Finance, bukan Teknisi', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);

    $this->actingAs($user)->get(DailyAttendanceResource::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
    $this->actingAs($user)->get(TechnicianIncentiveResource::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'hr' => [RoleName::Hr->value, true],
    'finance' => [RoleName::Finance->value, true],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('Rekap Insentif: aksi Tambah Entri Manual, Setujui, dan Tolak bekerja lewat Livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(ListTechnicianIncentives::class)
        ->callTableAction('tambahManual', data: [
            'user_id' => $this->teknisi->id,
            'tanggal' => Carbon::today()->toDateString(),
            'kategori' => IncentiveKategori::Games5OmsetTim->value,
            'tipe' => IncentiveTipe::Bonus->value,
            'nominal' => 40000,
        ])
        ->assertHasNoTableActionErrors();

    $entry = TechnicianIncentive::first();
    expect($entry->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Disetujui);

    Livewire::actingAs($this->admin)
        ->test(ListTechnicianIncentives::class)
        ->callTableAction('tolak', $entry, data: ['alasan' => 'Salah hitung'])
        ->assertHasNoTableActionErrors();

    expect($entry->fresh()->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Ditolak);
});

it('Rekap Absensi: EditAction menyimpan dikecualikan_denda & menolak ledger terkait', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-17 08:30:00'));
    $absen = app(AttendanceService::class)->catatDatang($this->teknisi, $this->kode, UploadedFile::fake()->image('a.jpg'));

    Livewire::actingAs($this->admin)
        ->test(ListDailyAttendances::class)
        ->callTableAction('edit', $absen, data: ['dikecualikan_denda' => true, 'catatan_admin' => 'Force majeure'])
        ->assertHasNoTableActionErrors();

    expect($absen->fresh()->dikecualikan_denda)->toBeTrue();

    $denda = TechnicianIncentive::query()->where('kategori', IncentiveKategori::DendaTelat->value)->first();
    expect($denda->status_verifikasi)->toBe(IncentiveStatusVerifikasi::Ditolak);
});
