<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\AttendanceLocation;
use App\Models\User;

/**
 * Lokasi kantor utk absensi mode GPS (dev-plan/19). CRUD + hitung jarak
 * (Haversine) — absen sah kalau teknisi dalam radius SALAH SATU lokasi
 * `aktif` (B70).
 */
class AttendanceLocationService
{
    use RestrictsByRole;

    private const PENGELOLA_ROLES = [RoleName::Admin, RoleName::Hr, RoleName::Owner];

    private const RADIUS_BUMI_METER = 6371000;

    public function tambah(array $data, User $by): AttendanceLocation
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        return AttendanceLocation::create([
            'nama' => $data['nama'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'radius_meter' => $data['radius_meter'] ?? 50,
            'aktif' => $data['aktif'] ?? true,
            'dibuat_oleh' => $by->id,
        ]);
    }

    public function perbarui(AttendanceLocation $lokasi, array $data, User $by): AttendanceLocation
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $lokasi->fill([
            'nama' => $data['nama'] ?? $lokasi->nama,
            'latitude' => $data['latitude'] ?? $lokasi->latitude,
            'longitude' => $data['longitude'] ?? $lokasi->longitude,
            'radius_meter' => $data['radius_meter'] ?? $lokasi->radius_meter,
        ]);
        $lokasi->save();

        return $lokasi->fresh();
    }

    public function toggleAktif(AttendanceLocation $lokasi, User $by): AttendanceLocation
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $lokasi->aktif = ! $lokasi->aktif;
        $lokasi->save();

        return $lokasi->fresh();
    }

    public function hapus(AttendanceLocation $lokasi, User $by): void
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $lokasi->delete();
    }

    /**
     * Jarak antar 2 koordinat dalam meter (rumus Haversine).
     */
    public function hitungJarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::RADIUS_BUMI_METER * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Cek posisi teknisi thd semua lokasi `aktif` yang sudah punya
     * koordinat. `masuk` true kalau ada minimal 1 lokasi aktif yang
     * jaraknya <= radius_meter-nya sendiri (B70) — kalau lolos lebih dari
     * 1, ambil yang paling dekat. Kalau tidak lolos sama sekali, `lokasi`
     * & `jarak_meter` tetap diisi (yang PALING DEKAT dari semua lokasi
     * aktif) supaya pesan error bisa sebut jarak persis (B74a).
     *
     * @return array{masuk: bool, lokasi: ?AttendanceLocation, jarak_meter: ?float}
     */
    public function evaluasiLokasi(float $lat, float $lng): array
    {
        $kandidat = $this->lokasiAktif()->map(function (AttendanceLocation $lokasi) use ($lat, $lng): array {
            $jarak = $this->hitungJarakMeter($lat, $lng, (float) $lokasi->latitude, (float) $lokasi->longitude);

            return ['lokasi' => $lokasi, 'jarak' => $jarak, 'lolos' => $jarak <= $lokasi->radius_meter];
        });

        // Prioritaskan yang lolos radius-nya sendiri, ambil yang paling dekat.
        $lolos = $kandidat->where('lolos', true)->sortBy('jarak')->first();
        if ($lolos !== null) {
            return ['masuk' => true, 'lokasi' => $lolos['lokasi'], 'jarak_meter' => $lolos['jarak']];
        }

        // Tidak ada yang lolos — tetap laporkan yang PALING DEKAT (B74a: pesan
        // error sebut jarak persis ke lokasi terdekat).
        $terdekat = $kandidat->sortBy('jarak')->first();

        return [
            'masuk' => false,
            'lokasi' => $terdekat['lokasi'] ?? null,
            'jarak_meter' => $terdekat['jarak'] ?? null,
        ];
    }

    private function lokasiAktif()
    {
        return AttendanceLocation::query()
            ->where('aktif', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();
    }
}
