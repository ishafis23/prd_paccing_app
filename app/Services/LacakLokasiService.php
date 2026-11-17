<?php

namespace App\Services;

use App\Models\TeknisiLokasiLog;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Pelacakan posisi teknisi terus-menerus selama teknisi login di app (juga di luar jam kerja,
 * agar admin bisa menugaskan teknisi terdekat).
 * App merekam titik di perangkat (juga saat offline) lalu mengirimnya berkelompok;
 * `client_uuid` membuat pengiriman ulang aman (tidak menggandakan).
 */
class LacakLokasiService
{
    public const MAKS_TITIK = 100;

    /** Titik yang lebih lama dari ini (hari) dianggap basi & dibuang — mencegah antrean offline lawas menyumbat. */
    public const MAKS_UMUR_HARI = 7;

    /**
     * Waktu dari app. Tanpa penanda zona (Z / +08:00) dianggap UTC — app mengirim
     * jam UTC; membacanya sebagai zona server (Asia/Makassar) menggeser titik
     * 8 jam ke belakang sehingga peta salah menandai teknisi "basi".
     */
    public static function parseWaktu(string $nilai): Carbon
    {
        $adaZona = (bool) preg_match('/(Z|[+-]\d{2}(:?\d{2})?)$/i', trim($nilai));

        return ($adaZona ? Carbon::parse($nilai) : Carbon::parse($nilai, 'UTC'))->setTimezone(config('app.timezone'));
    }

    /**
     * `uuid_selesai` = titik yang boleh dihapus dari antrean app
     * (diterima, duplikat, atau ditolak permanen: waktu di masa depan / terlalu lama).
     *
     * @param  array<int, array{client_uuid: string, lat: float|int|string, lng: float|int|string, akurasi?: int|null, direkam_pada: string}>  $titik
     * @return array{diterima: int, duplikat: int, ditolak: int, uuid_selesai: list<string>}
     */
    public function simpanBatch(User $teknisi, array $titik): array
    {
        $diterima = $duplikat = $ditolak = 0;
        $selesai = [];
        $terbaru = null;

        foreach ($titik as $t) {
            $waktu = self::parseWaktu($t['direkam_pada']);

            if ($waktu->gt(now()->addMinutes(5)) || $waktu->lt(now()->subDays(self::MAKS_UMUR_HARI))) {
                $ditolak++;
                $selesai[] = $t['client_uuid'];

                continue;
            }

            $log = TeknisiLokasiLog::query()->firstOrCreate(
                ['user_id' => $teknisi->id, 'client_uuid' => $t['client_uuid']],
                [
                    'latitude' => $t['lat'],
                    'longitude' => $t['lng'],
                    'akurasi_meter' => $t['akurasi'] ?? null,
                    'direkam_pada' => $waktu,
                ],
            );

            $log->wasRecentlyCreated ? $diterima++ : $duplikat++;
            $selesai[] = $t['client_uuid'];

            if ($log->wasRecentlyCreated && ($terbaru === null || $waktu->gt($terbaru['waktu']))) {
                $terbaru = ['waktu' => $waktu, 'lat' => $t['lat'], 'lng' => $t['lng']];
            }
        }

        // Peta admin memakai users.last_*: hanya maju, jangan ditimpa titik lama hasil sync offline.
        if ($terbaru !== null && ($teknisi->last_location_at === null || $terbaru['waktu']->gt($teknisi->last_location_at))) {
            $teknisi->update([
                'last_latitude' => $terbaru['lat'],
                'last_longitude' => $terbaru['lng'],
                'last_location_at' => $terbaru['waktu'],
            ]);
        }

        return ['diterima' => $diterima, 'duplikat' => $duplikat, 'ditolak' => $ditolak, 'uuid_selesai' => $selesai];
    }
}
