<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import massal akun pengguna dari Excel/CSV (keputusan B36).
 *
 * Aturan:
 * - Importir: Admin/Owner (baris ber-role Owner hanya bisa oleh Owner — B20).
 * - Kolom: nama* | email* | no_hp | role* | password | status.
 * - Password per baris opsional; kosong -> password default `paccing123`
 *   (bisa diganti lewat argumen/kolom). Minimal 8 karakter.
 * - Idempoten: email duplikat (DB/antar baris) & baris tidak valid DILEWATI,
 *   tidak ada akun lama yang diubah. Laporan: dibuat/dilewati/gagal + rincian.
 * - Batas: 200 baris data per file; ekstensi .xlsx/.csv.
 */
class UserImportService
{
    use RestrictsByRole;

    public const HEADER = ['nama', 'email', 'no_hp', 'role', 'password', 'status'];

    public const MAX_BARIS = 200;

    public const DEFAULT_PASSWORD = 'paccing123';

    private const EKSTENSI_DIDUKUNG = ['xlsx', 'csv'];

    /**
     * Spreadsheet template: sheet "pengguna" (header + contoh) dan
     * sheet "petunjuk" (daftar role & aturan).
     */
    public function template(): Spreadsheet
    {
        $ss = new Spreadsheet;

        $ws = $ss->getActiveSheet();
        $ws->setTitle('pengguna');
        $ws->fromArray([self::HEADER], null, 'A1');

        $contoh = [
            ['Budi Santoso', 'budi@example.com', '081234567890', 'teknisi', '', 'aktif'],
            ['Sari Wulandari', 'sari@example.com', '081298765432', 'finance', 'sari12345', 'aktif'],
        ];
        $ws->fromArray($contoh, null, 'A2');

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $kolom) {
            $ws->getColumnDimension($kolom)->setAutoSize(true);
        }
        $ws->getStyle('A1:F1')->getFont()->setBold(true);
        $ws->freezePane('A2');

        $petunjuk = $ss->createSheet();
        $petunjuk->setTitle('petunjuk');
        $petunjuk->fromArray([
            ['PETUNJUK IMPORT PENGGUNA'],
            [],
            ['1. Kolom wajib: nama, email, role. Kolom lain opsional.'],
            ['2. Role yang valid (huruf kecil): ' . implode(', ', array_map(fn ($r) => $r->value, RoleName::cases())) . '.'],
            ['3. Password opsional (min. 8 karakter). Bila dikosongkan, akun memakai password default: ' . self::DEFAULT_PASSWORD . '.'],
            ['   Segera ganti password akun via menu Pengguna -> Reset Password setelah import.'],
            ['4. Status opsional: aktif (default) atau nonaktif.'],
            ['5. Email duplikat (sudah ada / dua baris sama) -> baris dilewati, akun lama tidak diubah.'],
            ['6. Baris ber-role owner HANYA bisa diimport oleh Owner (bukan Admin).'],
            ['7. Maksimal ' . self::MAX_BARIS . ' baris data per file; format .xlsx atau .csv (UTF-8).'],
            ['8. Baris contoh di sheet "pengguna" bisa dihapus sebelum diisi.'],
        ], null, 'A1');
        $petunjuk->getColumnDimension('A')->setWidth(110);

        return $ss;
    }

    /**
     * Download template .xlsx.
     */
    public function unduhTemplate(): StreamedResponse
    {
        $ss = $this->template();

        return response()->streamDownload(function () use ($ss): void {
            (new Xlsx($ss))->save('php://output');
        }, 'template-import-pengguna.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Proses file import.
     *
     * @return array{berhasil: int, dilewati: int, gagal: int, rincian: array<int, string>}
     */
    public function import(string $pathFile, User $by, ?string $passwordDefault = null): array
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $ekstensi = strtolower(pathinfo($pathFile, PATHINFO_EXTENSION));
        if (! in_array($ekstensi, self::EKSTENSI_DIDUKUNG, true)) {
            throw new BusinessRuleException('Format file harus .xlsx atau .csv.');
        }

        try {
            $reader = IOFactory::createReaderForFile($pathFile);
            $reader->setReadDataOnly(true);
            $ss = $reader->load($pathFile);
        } catch (\Throwable) {
            throw new BusinessRuleException('File tidak dapat dibaca. Pastikan file .xlsx/.csv tidak rusak.');
        }

        $baris = $this->ekstrakBaris($ss->getActiveSheet()->toArray(null, false, false, false));
        $indeks = $this->indeksKolom($baris->shift() ?? []);

        if (! isset($indeks['nama'], $indeks['email'], $indeks['role'])) {
            throw new BusinessRuleException('Format file tidak dikenali — gunakan template (kolom nama, email, role wajib ada).');
        }

        if (count($baris) > self::MAX_BARIS) {
            throw new BusinessRuleException('Maksimal '.self::MAX_BARIS.' baris data per file (file Anda '.count($baris).' baris).');
        }

        $userService = app(UserService::class);
        $passwordDefault = trim((string) $passwordDefault) !== '' ? (string) $passwordDefault : self::DEFAULT_PASSWORD;

        $berhasil = 0;
        $dilewati = 0;
        $gagal = 0;
        $rincian = [];
        $emailDilihat = [];

        foreach ($baris as $nomor => $nilai) {
            $nomorExcel = $nomor + 2; // baris 1 = header
            $ambil = fn (string $kolom): string => trim((string) ($nilai[$indeks[$kolom]] ?? ''));

            $nama = $ambil('nama');
            $email = strtolower($ambil('email'));
            $roleStr = $ambil('role');
            $password = $ambil('password');
            $statusStr = $ambil('status');
            $noHp = $ambil('no_hp');

            if ($nama === '' && $email === '' && $roleStr === '' && $password === '' && $statusStr === '' && $noHp === '') {
                continue; // baris kosong
            }

            if ($nama === '') {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Nama wajib diisi.";
                continue;
            }

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Email tidak valid (".($email === '' ? 'kosong' : $email).').';
                continue;
            }

            $role = $this->resolusiRole($roleStr);
            if ($role === null) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Role tidak valid ('{$roleStr}').";
                continue;
            }

            $status = $this->resolusiStatus($statusStr);
            if ($status === null) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Status tidak valid ('{$statusStr}').";
                continue;
            }

            if ($password !== '' && mb_strlen($password) < 8) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Password minimal 8 karakter.";
                continue;
            }
            $password = $password !== '' ? $password : $passwordDefault;

            if (User::query()->where('email', $email)->exists() || in_array($email, $emailDilihat, true)) {
                $dilewati++;
                $rincian[] = "Baris {$nomorExcel}: Email {$email} sudah ada — dilewati (akun lama tidak diubah).";
                continue;
            }
            $emailDilihat[] = $email;

            try {
                $userService->createUser([
                    'name' => $nama,
                    'email' => $email,
                    'phone' => $noHp !== '' ? $noHp : null,
                    'password' => $password,
                    'role' => $role->value,
                    'status' => $status->value,
                ], $by);
                $berhasil++;
            } catch (BusinessRuleException|AuthorizationException $e) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: ".$e->getMessage();
            }
        }

        return compact('berhasil', 'dilewati', 'gagal', 'rincian');
    }

    /**
     * @param  array<int, array<int, mixed>>  $baris
     */
    private function ekstrakBaris(array $baris): Collection
    {
        return collect($baris)
            ->map(fn ($b) => is_array($b) ? array_values($b) : [$b])
            ->values();
    }

    /**
     * @param  array<int, mixed>  $header
     * @return array<string, int>
     */
    private function indeksKolom(array $header): array
    {
        $peta = [];

        foreach ($header as $i => $nama) {
            $kunci = strtolower(trim((string) $nama));
            if ($kunci === 'no_hp' || $kunci === 'no hp' || $kunci === 'no.hp' || $kunci === 'no_hp/wa') {
                $kunci = 'no_hp';
            }
            $peta[$kunci] = $i;
        }

        return $peta;
    }

    private function resolusiRole(string $nilai): ?RoleName
    {
        $kecil = strtolower(trim($nilai));

        if ($kecil === '') {
            return null;
        }

        foreach (RoleName::cases() as $case) {
            if ($case->value === $kecil || strtolower($case->name) === $kecil) {
                return $case;
            }
        }

        return null;
    }

    private function resolusiStatus(string $nilai): ?UserStatus
    {
        $kecil = strtolower(trim($nilai));

        return match ($kecil) {
            '', 'aktif', 'active' => UserStatus::Aktif,
            'nonaktif', 'non_aktif', 'inactive' => UserStatus::Nonaktif,
            default => null,
        };
    }
}
