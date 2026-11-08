<?php

use App\Enums\RoleName;
use App\Models\DailyAttendance;
use App\Models\TeknisiLokasiLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->json = ['Accept' => 'application/json'];

    $this->titik = fn (string $waktu, float $lat = -6.2, ?string $uuid = null): array => [
        'client_uuid' => $uuid ?? (string) Str::uuid(),
        'lat' => $lat,
        'lng' => 106.8,
        'akurasi' => 12,
        'direkam_pada' => $waktu,
    ];
});

it('menolak tanpa token', function () {
    $this->postJson('/api/v1/lokasi/batch', ['titik' => []])->assertUnauthorized();
});

it('menyimpan titik selama jam kerja dan memperbarui posisi terakhir', function () {
    $this->travelTo('2026-11-09 10:00:00');
    DailyAttendance::create(['user_id' => $this->teknisi->id, 'tanggal' => '2026-11-09', 'jam_datang' => '2026-11-09 08:00:00']);
    Sanctum::actingAs($this->teknisi);

    $a = ($this->titik)('2026-11-09 09:00:00', -6.2);
    $b = ($this->titik)('2026-11-09 09:30:00', -6.3);

    $this->postJson('/api/v1/lokasi/batch', ['titik' => [$b, $a]], $this->json)
        ->assertOk()
        ->assertJsonPath('data.diterima', 2)
        ->assertJsonPath('data.ditolak', 0)
        ->assertJsonCount(2, 'data.uuid_selesai');

    expect(TeknisiLokasiLog::count())->toBe(2);
    $this->teknisi->refresh();
    expect((float) $this->teknisi->last_latitude)->toBe(-6.3)
        ->and($this->teknisi->last_location_at->format('H:i'))->toBe('09:30');
});

it('kirim ulang client_uuid yang sama tidak menggandakan', function () {
    $this->travelTo('2026-11-09 10:00:00');
    DailyAttendance::create(['user_id' => $this->teknisi->id, 'tanggal' => '2026-11-09', 'jam_datang' => '2026-11-09 08:00:00']);
    Sanctum::actingAs($this->teknisi);

    $payload = ['titik' => [($this->titik)('2026-11-09 09:00:00')]];

    $this->postJson('/api/v1/lokasi/batch', $payload, $this->json)->assertJsonPath('data.diterima', 1);
    $this->postJson('/api/v1/lokasi/batch', $payload, $this->json)
        ->assertJsonPath('data.diterima', 0)
        ->assertJsonPath('data.duplikat', 1);

    expect(TeknisiLokasiLog::count())->toBe(1);
});

it('menolak titik di luar jam kerja: belum absen, sebelum datang, sesudah pulang', function () {
    $this->travelTo('2026-11-09 18:00:00');
    Sanctum::actingAs($this->teknisi);

    // Belum absen sama sekali.
    $this->postJson('/api/v1/lokasi/batch', ['titik' => [($this->titik)('2026-11-09 09:00:00')]], $this->json)
        ->assertJsonPath('data.ditolak', 1)
        ->assertJsonPath('data.diterima', 0);

    DailyAttendance::create([
        'user_id' => $this->teknisi->id, 'tanggal' => '2026-11-09',
        'jam_datang' => '2026-11-09 08:00:00', 'jam_pulang' => '2026-11-09 16:00:00',
    ]);

    $respons = $this->postJson('/api/v1/lokasi/batch', ['titik' => [
        ($this->titik)('2026-11-09 07:00:00'), // sebelum datang
        ($this->titik)('2026-11-09 12:00:00'), // sah
        ($this->titik)('2026-11-09 17:00:00'), // sesudah pulang
    ]], $this->json)->assertOk();

    $respons->assertJsonPath('data.diterima', 1)->assertJsonPath('data.ditolak', 2);
    // Titik yang ditolak permanen ikut dilaporkan selesai agar app tidak mengulang selamanya.
    $respons->assertJsonCount(3, 'data.uuid_selesai');
});

it('titik lama hasil sync offline tidak menimpa posisi yang lebih baru', function () {
    $this->travelTo('2026-11-09 10:00:00');
    DailyAttendance::create(['user_id' => $this->teknisi->id, 'tanggal' => '2026-11-09', 'jam_datang' => '2026-11-09 08:00:00']);
    $this->teknisi->update(['last_latitude' => -7.0, 'last_longitude' => 110.0, 'last_location_at' => '2026-11-09 09:55:00']);
    Sanctum::actingAs($this->teknisi);

    $this->postJson('/api/v1/lokasi/batch', ['titik' => [($this->titik)('2026-11-09 09:00:00', -6.2)]], $this->json)
        ->assertJsonPath('data.diterima', 1);

    expect((float) $this->teknisi->fresh()->last_latitude)->toBe(-7.0);
});

it('validasi: koordinat, uuid, batas jumlah titik', function () {
    $this->travelTo('2026-11-09 10:00:00');
    Sanctum::actingAs($this->teknisi);

    $this->postJson('/api/v1/lokasi/batch', ['titik' => [['client_uuid' => 'bukan-uuid', 'lat' => 120, 'lng' => 106, 'direkam_pada' => 'x']]], $this->json)
        ->assertUnprocessable();

    $banyak = array_fill(0, 101, ($this->titik)('2026-11-09 09:00:00'));
    $this->postJson('/api/v1/lokasi/batch', ['titik' => $banyak], $this->json)->assertUnprocessable();
});
