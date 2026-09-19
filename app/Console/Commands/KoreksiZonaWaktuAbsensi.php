<?php

namespace App\Console\Commands;

use App\Enums\DailyAttendanceStatus;
use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\RoleName;
use App\Models\DailyAttendance;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\AttendanceSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Koreksi data absensi yang tercatat SEBELUM config/app.php timezone
 * diperbaiki dari UTC ke Asia/Makassar (insiden 19 Sep 2026, lihat
 * 02-keputusan-eksekusi.md): jam_datang/jam_pulang tersimpan sbg jam UTC
 * (selisih 8 jam dari WITA asli), status_datang & sebagian ledger
 * insentif (Games 1 / denda telat) ikut salah karena perbandingan ambang
 * jam dilakukan thd jam yang salah itu.
 *
 * Games 2 (titik pertama) & Games 4 (kepulangan) SENGAJA TIDAK disentuh
 * di sini — jam kejadian aslinya tidak tersimpan di daily_attendances
 * (butuh tinjau manual terpisah kalau dianggap perlu).
 *
 * Default: DRY RUN (cuma laporan, tidak ubah apa pun). --apply utk
 * benar-benar menjalankan koreksi.
 */
class KoreksiZonaWaktuAbsensi extends Command
{
    protected $signature = 'absensi:koreksi-zona-waktu {--apply : Benar-benar jalankan koreksi (default cuma laporan/dry-run)}';

    protected $description = 'Koreksi jam_datang/jam_pulang/status_datang yang tersimpan sbg UTC (harusnya Asia/Makassar) — insiden 19 Sep 2026';

    public function handle(AttendanceService $attendanceService, AttendanceSettingService $settingService): int
    {
        $apply = (bool) $this->option('apply');
        $settings = $settingService->data();

        $rows = DailyAttendance::query()->whereNotNull('jam_datang')->with('user')->orderBy('jam_datang')->get();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada data daily_attendances — tidak ada yang perlu dikoreksi.');

            return self::SUCCESS;
        }

        $koreksiOleh = User::role([RoleName::Owner->value, RoleName::Admin->value])->first();

        if ($apply && $koreksiOleh === null) {
            $this->error('Tidak ada user Owner/Admin di database — tidak bisa jalankan --apply (perlu utk audit trail pembatalan insentif).');

            return self::FAILURE;
        }

        $this->info(($apply ? 'MENJALANKAN KOREKSI' : 'DRY RUN — tambahkan --apply utk benar-benar jalankan').' — '.$rows->count().' baris absensi ditemukan.');
        $this->newLine();

        $tabel = [];
        $jumlahStatusBerubah = 0;
        $jumlahInsentifDibatalkan = 0;
        $jumlahPerluTinjauManual = 0;

        DB::transaction(function () use (
            $rows, $apply, $attendanceService, $settings, $koreksiOleh,
            &$tabel, &$jumlahStatusBerubah, &$jumlahInsentifDibatalkan, &$jumlahPerluTinjauManual
        ) {
            foreach ($rows as $absen) {
                $jamDatangLama = $absen->jam_datang->copy();
                $jamDatangBaru = $jamDatangLama->copy()->addHours(8);
                $tanggalLama = $absen->tanggal->toDateString();
                $tanggalBaru = $jamDatangBaru->toDateString();
                $jamPulangBaru = $absen->jam_pulang?->copy()->addHours(8);

                $statusLama = $absen->status_datang;
                $statusBaru = $attendanceService->tentukanStatusDatang(
                    $absen->user,
                    Carbon::parse($tanggalBaru),
                    $jamDatangBaru,
                    $settings,
                );

                $aksiInsentif = '-';

                if ($statusLama !== $statusBaru) {
                    $jumlahStatusBerubah++;

                    $telatLama = in_array($statusLama, [DailyAttendanceStatus::Telat, DailyAttendanceStatus::TelatToleransi], true);
                    $telatBaru = in_array($statusBaru, [DailyAttendanceStatus::Telat, DailyAttendanceStatus::TelatToleransi], true);

                    if ($telatLama && ! $telatBaru) {
                        $entry = $this->cariEntriAktif($absen->user_id, $tanggalLama, IncentiveKategori::DendaTelat);
                        if ($entry) {
                            $aksiInsentif = 'Denda telat dibatalkan';
                            $jumlahInsentifDibatalkan++;
                            if ($apply) {
                                $this->batalkanEntri($entry, $koreksiOleh);
                            }
                        }
                    } elseif ($statusLama === DailyAttendanceStatus::Bonus && $statusBaru !== DailyAttendanceStatus::Bonus) {
                        $entry = $this->cariEntriAktif($absen->user_id, $tanggalLama, IncentiveKategori::Games1Hadir);
                        if ($entry) {
                            $aksiInsentif = 'Bonus Games 1 dibatalkan';
                            $jumlahInsentifDibatalkan++;
                            if ($apply) {
                                $this->batalkanEntri($entry, $koreksiOleh);
                            }
                        }
                    } elseif ($statusBaru === DailyAttendanceStatus::Bonus && $statusLama !== DailyAttendanceStatus::Bonus) {
                        $aksiInsentif = 'PERLU DITAMBAH MANUAL (Bonus Games 1) — tidak auto-tambah uang';
                        $jumlahPerluTinjauManual++;
                    } elseif ($telatBaru && ! $telatLama) {
                        $aksiInsentif = 'PERLU DITAMBAH MANUAL (Denda Telat) — tidak auto-tambah denda';
                        $jumlahPerluTinjauManual++;
                    }
                }

                $tabel[] = [
                    $absen->user->name ?? "user#{$absen->user_id}",
                    $tanggalLama,
                    $jamDatangLama->format('H:i').' -> '.$jamDatangBaru->format('H:i'),
                    $statusLama->value.' -> '.$statusBaru->value,
                    $aksiInsentif,
                ];

                if ($apply) {
                    $absen->jam_datang = $jamDatangBaru;
                    $absen->jam_pulang = $jamPulangBaru;
                    $absen->tanggal = $tanggalBaru;
                    $absen->status_datang = $statusBaru->value;
                    $absen->save();
                }
            }
        });

        $this->table(['Teknisi', 'Tanggal Lama', 'Jam Datang Lama -> Baru', 'Status Lama -> Baru', 'Aksi Insentif'], $tabel);
        $this->newLine();
        $this->info("Ringkasan: {$jumlahStatusBerubah} baris status_datang berubah, {$jumlahInsentifDibatalkan} entri insentif dibatalkan otomatis, {$jumlahPerluTinjauManual} butuh tinjau manual (mungkin ada uang yang harus DITAMBAH — Rekap Insentif > Tambah Entri Manual).");
        $this->warn('Games 2 (titik pertama) & Games 4 (kepulangan) TIDAK disentuh perintah ini — jam kejadian aslinya tidak tersimpan di daily_attendances, tinjau manual terpisah kalau perlu.');

        if (! $apply) {
            $this->newLine();
            $this->warn('Ini DRY RUN — tidak ada yang benar-benar diubah. Jalankan ulang dengan --apply untuk menerapkan.');
        }

        return self::SUCCESS;
    }

    private function cariEntriAktif(int $userId, string $tanggal, IncentiveKategori $kategori): ?TechnicianIncentive
    {
        return TechnicianIncentive::query()
            ->where('user_id', $userId)
            ->where('tanggal', $tanggal)
            ->where('kategori', $kategori->value)
            ->where('status_verifikasi', '!=', IncentiveStatusVerifikasi::Ditolak->value)
            ->first();
    }

    private function batalkanEntri(TechnicianIncentive $entry, User $by): void
    {
        $entry->status_verifikasi = IncentiveStatusVerifikasi::Ditolak;
        $entry->diverifikasi_pada = now();
        $entry->diverifikasi_oleh = $by->id;
        $entry->catatan = 'Dibatalkan otomatis: koreksi insiden timezone UTC (php artisan absensi:koreksi-zona-waktu, 19 Sep 2026).';
        $entry->save();
    }
}
