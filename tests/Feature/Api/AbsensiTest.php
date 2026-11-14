<?php

use App\Enums\AttendanceMode;
use App\Enums\RoleName;
use App\Models\AttendanceLocation;
use App\Models\DailyAttendance;
use App\Models\MotorCleaning;
use App\Models\TechnicianEarlyLeave;
use App\Models\User;
use App\Services\AttendanceSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);
    AttendanceSettingService::lupakanCache();

    $this->mk = fn (string $role = 'teknisi'): User => tap(User::factory()->create(), fn ($u) => $u->assignRole($role));
    $this->admin = ($this->mk)(RoleName::Admin->value);
    $this->teknisi = ($this->mk)();
    $this->foto = fn () => UploadedFile::fake()->image('f.jpg', 200, 200);
    $this->json = ['Accept' => 'application/json'];

    AttendanceLocation::create(['nama' => 'Kantor Pusat', 'latitude' => -6.2, 'longitude' => 106.8166, 'radius_meter' => 50, 'aktif' => true, 'dibuat_oleh' => $this->admin->id]);
    app(AttendanceSettingService::class)->perbarui(['mode_absensi' => AttendanceMode::Lokasi->value], $this->admin);
});

it('hari-ini: belum absen -> siap pulang -> selesai, memuat mode & lokasi kantor', function () {
    Sanctum::actingAs($this->teknisi);

    $this->getJson('/api/v1/absensi/hari-ini')->assertOk()
        ->assertJsonPath('data.state', 'belum_absen')->assertJsonPath('data.lacak_aktif', false)->assertJsonPath('data.mode_absensi', 'lokasi')
        ->assertJsonPath('data.lokasi_kantor.0.nama', 'Kantor Pusat');

    $this->post('/api/v1/absensi/datang', ['foto' => ($this->foto)(), 'lat' => -6.2, 'lng' => 106.8166], $this->json)
        ->assertCreated()->assertJsonStructure(['data' => ['jam_datang', 'status_datang']]);
    $this->getJson('/api/v1/absensi/hari-ini')->assertJsonPath('data.state', 'siap_pulang')->assertJsonPath('data.lacak_aktif', true);

    $this->post('/api/v1/absensi/pulang', ['foto' => ($this->foto)()], $this->json)->assertOk();
    $this->getJson('/api/v1/absensi/hari-ini')->assertJsonPath('data.state', 'selesai')->assertJsonPath('data.lacak_aktif', false);
});

it('datang ditolak di luar radius, tanpa lokasi, atau dua kali', function () {
    Sanctum::actingAs($this->teknisi);

    $this->post('/api/v1/absensi/datang', ['foto' => ($this->foto)(), 'lat' => -6.3, 'lng' => 106.9], $this->json)
        ->assertStatus(422)->assertJsonPath('code', 'aturan_bisnis');
    $this->post('/api/v1/absensi/datang', ['foto' => ($this->foto)()], $this->json)->assertStatus(422);

    $this->post('/api/v1/absensi/datang', ['foto' => ($this->foto)(), 'lat' => -6.2, 'lng' => 106.8166], $this->json)->assertCreated();
    $this->post('/api/v1/absensi/datang', ['foto' => ($this->foto)(), 'lat' => -6.2, 'lng' => 106.8166], $this->json)->assertStatus(422);
});

it('pulang tanpa datang ditolak; foto wajib', function () {
    Sanctum::actingAs($this->teknisi);
    $this->post('/api/v1/absensi/pulang', ['foto' => ($this->foto)()], $this->json)->assertStatus(422);
    $this->post('/api/v1/absensi/pulang', [], $this->json)->assertStatus(422)->assertJsonPath('code', 'validasi');
});

it('mode QR: kode salah ditolak', function () {
    app(AttendanceSettingService::class)->perbarui(['mode_absensi' => AttendanceMode::Qr->value], $this->admin);
    Sanctum::actingAs($this->teknisi);

    $this->post('/api/v1/absensi/datang', ['foto' => ($this->foto)(), 'kode' => 'salah'], $this->json)
        ->assertStatus(422)->assertJsonPath('code', 'aturan_bisnis');
});

it('riwayat hanya milik sendiri, bisa filter bulan', function () {
    $lain = ($this->mk)();
    DailyAttendance::create(['user_id' => $this->teknisi->id, 'tanggal' => '2026-10-05', 'jam_datang' => '2026-10-05 08:00:00']);
    DailyAttendance::create(['user_id' => $this->teknisi->id, 'tanggal' => '2026-09-05', 'jam_datang' => '2026-09-05 08:00:00']);
    DailyAttendance::create(['user_id' => $lain->id, 'tanggal' => '2026-10-06', 'jam_datang' => '2026-10-06 08:00:00']);
    Sanctum::actingAs($this->teknisi);

    $this->getJson('/api/v1/absensi/riwayat')->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/absensi/riwayat?bulan=2026-10')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.tanggal', '2026-10-05');
});

it('cuci motor: rekan terdaftar, tercatat untuk dua orang', function () {
    $rekan = ($this->mk)();
    Sanctum::actingAs($this->teknisi);

    expect(collect($this->getJson('/api/v1/cuci-motor/rekan')->json('data'))->pluck('id')->all())->toBe([$rekan->id]);

    $this->post('/api/v1/cuci-motor', ['foto' => ($this->foto)(), 'rekan_id' => $rekan->id], $this->json)
        ->assertCreated()->assertJsonCount(2, 'data.peserta');
    expect(MotorCleaning::count())->toBe(1);
});

it('pulang cepat: ajukan, tanggal masa depan ditolak, riwayat memuat nominal potongan', function () {
    Sanctum::actingAs($this->teknisi);

    $this->postJson('/api/v1/pulang-cepat', ['tanggal' => today()->toDateString(), 'jam_pulang' => '13:00', 'alasan' => 'Sakit'])
        ->assertCreated()->assertJsonPath('data.status', 'menunggu')->assertJsonPath('data.jam_pulang', '13:00');
    $this->postJson('/api/v1/pulang-cepat', ['tanggal' => today()->addDay()->toDateString(), 'jam_pulang' => '13:00'])
        ->assertStatus(422)->assertJsonPath('code', 'aturan_bisnis');

    TechnicianEarlyLeave::query()->update(['nominal_potongan' => 35000]);
    $this->getJson('/api/v1/pulang-cepat')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.nominal_potongan', 35000);
});
