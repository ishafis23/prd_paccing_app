<?php

namespace App\Services;

use App\Enums\AttendanceMode;
use App\Enums\DailyAttendanceStatus;
use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveTipe;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\AttendanceSetting;
use App\Models\DailyAttendance;
use App\Models\TechnicianIncentive;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Absensi kantor harian teknisi (dev-plan/15): catat datang/pulang lewat
 * scan kode (B39-B41), hitung Games 1 (hadir) + denda telat + toleransi
 * lembur malam sebelumnya (B47/§1b) saat datang, dan Games 4 (kepulangan:
 * titik + jam, B52) saat pulang.
 */
class AttendanceService
{
    use RestrictsByRole;

    public const FOLDER = 'absensi';

    private const PENGELOLA_ROLES = [RoleName::Admin, RoleName::Hr, RoleName::Owner];

    public function __construct(
        private readonly AttendanceCodeService $kodeService,
        private readonly AttendanceLocationService $lokasiService,
        private readonly AttendanceSettingService $settingService,
        private readonly TechnicianIncentiveService $incentiveService,
        private readonly StorageQuotaService $quotaService,
    ) {}

    /**
     * dev-plan/19: bukti kehadiran sesuai mode absensi aktif —
     * `$kode` dipakai kalau mode QR, `$lat`/`$lng` kalau mode lokasi.
     * Parameter yang tidak relevan dgn mode aktif boleh null, diabaikan.
     */
    public function catatDatang(User $teknisi, ?string $kode, UploadedFile $foto, ?float $lat = null, ?float $lng = null): DailyAttendance
    {
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        $settings = $this->settingService->data();
        $attendanceCodeId = null;
        $attendanceLocationId = null;

        if ($settings->mode_absensi === AttendanceMode::Lokasi) {
            if ($lat === null || $lng === null) {
                throw new BusinessRuleException('Lokasi tidak terdeteksi. Aktifkan izin lokasi di browser lalu coba lagi.');
            }

            $evaluasi = $this->lokasiService->evaluasiLokasi($lat, $lng);

            if ($evaluasi['lokasi'] === null) {
                throw new BusinessRuleException('Belum ada lokasi kantor terdaftar. Hubungi admin.');
            }

            if (! $evaluasi['masuk']) {
                $jarak = round($evaluasi['jarak_meter']);
                throw new BusinessRuleException(
                    "Anda {$jarak}m dari {$evaluasi['lokasi']->nama} (radius {$evaluasi['lokasi']->radius_meter}m). Mendekat ke kantor lalu coba lagi."
                );
            }

            $attendanceLocationId = $evaluasi['lokasi']->id;
        } else {
            $attendanceCodeId = $this->kodeService->validasiAtauGagal((string) $kode)->id;
        }

        $tanggal = Carbon::today();

        $sudahDatang = DailyAttendance::query()
            ->where('user_id', $teknisi->id)
            ->where('tanggal', $tanggal->toDateString())
            ->whereNotNull('jam_datang')
            ->exists();

        if ($sudahDatang) {
            throw new BusinessRuleException('Anda sudah absen datang hari ini.');
        }

        $this->quotaService->pastikanCukup($foto->getSize());
        $path = $foto->store(self::FOLDER, 'public');
        StorageQuotaService::lupakanCache();

        $now = Carbon::now();
        $status = $this->tentukanStatusDatang($teknisi, $tanggal, $now, $settings);

        $absen = DailyAttendance::updateOrCreate(
            ['user_id' => $teknisi->id, 'tanggal' => $tanggal->toDateString()],
            [
                'attendance_code_id' => $attendanceCodeId,
                'attendance_location_id' => $attendanceLocationId,
                'jam_datang' => $now,
                'foto_datang' => $path,
                'status_datang' => $status->value,
            ]
        );

        if ($status === DailyAttendanceStatus::Bonus) {
            $this->incentiveService->catat(
                $teknisi,
                $tanggal,
                IncentiveKategori::Games1Hadir,
                IncentiveTipe::Bonus,
                (float) $settings->nominal_games1,
                $path,
            );
        } elseif ($status === DailyAttendanceStatus::Telat) {
            $this->incentiveService->catat(
                $teknisi,
                $tanggal,
                IncentiveKategori::DendaTelat,
                IncentiveTipe::Denda,
                (float) $settings->nominal_denda_telat,
            );
        }

        return $absen->fresh();
    }

    public function catatPulang(User $teknisi, UploadedFile $foto): DailyAttendance
    {
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        $tanggal = Carbon::today();

        $absen = DailyAttendance::query()
            ->where('user_id', $teknisi->id)
            ->where('tanggal', $tanggal->toDateString())
            ->first();

        if (! $absen || $absen->jam_datang === null) {
            throw new BusinessRuleException('Absen datang dulu sebelum absen pulang.');
        }

        if ($absen->jam_pulang !== null) {
            throw new BusinessRuleException('Anda sudah absen pulang hari ini.');
        }

        $this->quotaService->pastikanCukup($foto->getSize());
        $path = $foto->store(self::FOLDER, 'public');
        StorageQuotaService::lupakanCache();

        $now = Carbon::now();

        $absen->jam_pulang = $now;
        $absen->foto_pulang = $path;
        $absen->save();

        $this->hitungGames4($teknisi, $tanggal, $now, $path);

        return $absen->fresh();
    }

    /**
     * Games 2 (dev-plan/15, B48): dipanggil dari slider "Check-in Sekarang"
     * (`OrderDetail`) saat ini check-in job-site PERTAMA teknisi hari itu.
     * Foto selalu disimpan sebagai bukti kehadiran di titik; bonus cuma
     * tercatat ke ledger bila masih dalam ambang jam.
     */
    public function catatTitikPertama(User $teknisi, UploadedFile $foto): void
    {
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        $this->quotaService->pastikanCukup($foto->getSize());
        $path = $foto->store(self::FOLDER, 'public');
        StorageQuotaService::lupakanCache();

        $settings = $this->settingService->data();
        $now = Carbon::now();

        if ($now->format('H:i:s') <= (string) $settings->jam_games2_batas) {
            $this->incentiveService->catat(
                $teknisi,
                Carbon::today(),
                IncentiveKategori::Games2TitikPertama,
                IncentiveTipe::Bonus,
                (float) $settings->nominal_games2,
                $path,
            );
        }
    }

    /**
     * Override manual (B45): tandai denda telat hari itu dikecualikan —
     * sekaligus tolak entri ledger `denda_telat` (efek nyata ke total gaji).
     */
    public function kecualikanDenda(DailyAttendance $absen, bool $dikecualikan, User $by, ?string $catatan = null): DailyAttendance
    {
        $this->assertRole($by, self::PENGELOLA_ROLES);

        $absen->dikecualikan_denda = $dikecualikan;
        $absen->catatan_admin = $catatan;
        $absen->save();

        if ($dikecualikan) {
            $denda = TechnicianIncentive::query()
                ->where('user_id', $absen->user_id)
                ->where('tanggal', $absen->tanggal->toDateString())
                ->where('kategori', IncentiveKategori::DendaTelat->value)
                ->first();

            if ($denda && $denda->status_verifikasi !== IncentiveStatusVerifikasi::Ditolak) {
                $this->incentiveService->tolak($denda, $by, $catatan ?? 'Dikecualikan Admin/HR.');
            }
        }

        return $absen->fresh();
    }

    /**
     * Publik (bukan cuma dipakai internal catatDatang()) — dipakai ulang
     * oleh `absensi:koreksi-zona-waktu` utk hitung ulang status_datang yg
     * benar setelah insiden timezone UTC (lihat 02-keputusan-eksekusi.md).
     */
    public function tentukanStatusDatang(User $teknisi, Carbon $tanggal, Carbon $now, AttendanceSetting $settings): DailyAttendanceStatus
    {
        $jamNow = $now->format('H:i:s');

        if ($jamNow <= (string) $settings->jam_games1_batas) {
            return DailyAttendanceStatus::Bonus;
        }

        if ($jamNow <= (string) $settings->jam_normal_selesai) {
            return DailyAttendanceStatus::Normal;
        }

        if ($this->toleransiLemburBerlaku($teknisi, $tanggal, $settings) && $jamNow <= (string) $settings->jam_toleransi_batas_denda) {
            return DailyAttendanceStatus::TelatToleransi;
        }

        return DailyAttendanceStatus::Telat;
    }

    /**
     * B47/§1b: toleransi berlaku bila jam pulang absen kantor **kemarin**
     * ≥ ambang "lembur" — sumber data dipilih sesuai rekomendasi §7 poin 1
     * (belum dikonfirmasi eksplisit, dicatat sebagai asumsi kerja).
     */
    private function toleransiLemburBerlaku(User $teknisi, Carbon $tanggal, AttendanceSetting $settings): bool
    {
        $kemarin = DailyAttendance::query()
            ->where('user_id', $teknisi->id)
            ->where('tanggal', $tanggal->copy()->subDay()->toDateString())
            ->first();

        if (! $kemarin || $kemarin->jam_pulang === null) {
            return false;
        }

        return $kemarin->jam_pulang->format('H:i:s') >= (string) $settings->jam_toleransi_lembur_mulai;
    }

    private function hitungGames4(User $teknisi, Carbon $tanggal, Carbon $now, string $fotoPulang): void
    {
        $settings = $this->settingService->data();
        $modeJalan = $this->incentiveService->tentukanModeJalan($teknisi, $tanggal);

        if ($modeJalan === null) {
            return;
        }

        $titik = $this->incentiveService->hitungTitikHarian($teknisi, $tanggal);
        $minimalTitik = $modeJalan === 'berdua' ? $settings->minimal_titik_berdua : $settings->minimal_titik_sendiri;
        $jamOk = $now->format('H:i:s') <= (string) $settings->jam_games4_batas;

        if ($titik < $minimalTitik || ! $jamOk) {
            return;
        }

        $nominal = $modeJalan === 'berdua' ? $settings->nominal_games4_berdua : $settings->nominal_games4_sendiri;

        $this->incentiveService->catat(
            $teknisi,
            $tanggal,
            IncentiveKategori::Games4Kepulangan,
            IncentiveTipe::Bonus,
            (float) $nominal,
            $fotoPulang,
            referensi: "titik={$titik};mode={$modeJalan}",
        );
    }
}
