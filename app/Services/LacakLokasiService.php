<?php

namespace App\Services;

use App\Models\DailyAttendance;
use App\Models\TeknisiLokasiLog;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Pelacakan posisi teknisi terus-menerus selama jam kerja (absen datang → absen pulang).
 * App merekam titik di perangkat (juga saat offline) lalu mengirimnya berkelompok;
 * `client_uuid` membuat pengiriman ulang aman (tidak menggandakan).
 */
class LacakLokasiService
{
    public const MAKS_TITIK = 100;

    /**
     * `uuid_selesai` = titik yang boleh dihapus dari antrean app
     * (diterima, duplikat, atau ditolak permanen karena di luar jam kerja).
     *
     * @param  array<int, array{client_uuid: string, lat: float|int|string, lng: float|int|string, akurasi?: int|null, direkam_pada: string}>  $titik
     * @return array{diterima: int, duplikat: int, ditolak: int, uuid_selesai: list<string>}
     */
    public function simpanBatch(User $teknisi, array $titik): array
    {
        $diterima = $duplikat = $ditolak = 0;
        $selesai = [];
        $terbaru = null;
        $absenPerTanggal = [];

        foreach ($titik as $t) {
            $waktu = Carbon::parse($t['direkam_pada']);

            if ($waktu->gt(now()->addMinutes(5)) || ! $this->dalamJamKerja($teknisi, $waktu, $absenPerTanggal)) {
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

    /**
     * Titik sah bila direkam di antara jam datang dan jam pulang (atau sekarang)
     * pada tanggal absensinya.
     *
     * @param  array<string, DailyAttendance|null>  $cache
     */
    private function dalamJamKerja(User $teknisi, Carbon $waktu, array &$cache): bool
    {
        $tanggal = $waktu->toDateString();

        if (! array_key_exists($tanggal, $cache)) {
            $cache[$tanggal] = DailyAttendance::query()
                ->where('user_id', $teknisi->id)
                ->where('tanggal', $tanggal)
                ->whereNotNull('jam_datang')
                ->first();
        }

        $absen = $cache[$tanggal];
        if ($absen === null) {
            return false;
        }

        $mulai = Carbon::parse($absen->jam_datang)->subMinute();
        $akhir = $absen->jam_pulang !== null ? Carbon::parse($absen->jam_pulang)->addMinute() : now()->addMinutes(5);

        return $waktu->betweenIncluded($mulai, $akhir);
    }
}
