<?php

namespace App\Services;

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import massal data Customer dari Excel/CSV (mis. hasil rapikan dari
 * kontak Gmail) — dipakai utk migrasi data ribuan customer sekaligus.
 *
 * Aturan:
 * - Importir: Admin (mengikuti CustomerPolicy::create).
 * - Kolom: nama* | jenis | no_hp* | email | alamat | area | sumber_lead | status | catatan.
 * - Idempoten: no_hp duplikat (DB/antar baris, dibandingkan setelah
 *   dinormalisasi) & baris tidak valid DILEWATI, tidak ada customer lama
 *   yang diubah. Laporan: dibuat/dilewati/gagal + rincian.
 * - Batas: 5000 baris data per file; ekstensi .xlsx/.csv.
 * - Insert dikelompokkan per 500 baris (bukan satu-satu) supaya import
 *   ribuan baris tidak timeout di hosting terbatas.
 */
class CustomerImportService
{
    use RestrictsByRole;

    public const HEADER = ['nama', 'jenis', 'no_hp', 'email', 'alamat', 'area', 'sumber_lead', 'status', 'catatan'];

    public const MAX_BARIS = 5000;

    private const UKURAN_CHUNK_INSERT = 500;

    /** Dropdown di template cuma perlu utk baris awal — staff tinggal drag/copy ke bawah kalau data lebih banyak. */
    private const BARIS_DROPDOWN_TEMPLATE = 1000;

    private const EKSTENSI_DIDUKUNG = ['xlsx', 'csv'];

    /**
     * Spreadsheet template: sheet "customer" (header + contoh) dan
     * sheet "petunjuk" (daftar pilihan kolom & aturan).
     */
    public function template(): Spreadsheet
    {
        $ss = new Spreadsheet;

        $ws = $ss->getActiveSheet();
        $ws->setTitle('customer');
        $ws->fromArray([self::HEADER], null, 'A1');

        $contoh = [
            ['Budi Santoso', 'perorangan', '081234567890', 'budi@example.com', 'Jl Mawar 4, Makassar', 'makassar', 'whatsapp', 'lead', ''],
            ['PT Kalla Toyota', 'company', '081298765432', '', 'Jl Melati 10, Gowa', 'gowa', 'referral', 'aktif', 'Pelanggan lama'],
        ];
        $ws->fromArray($contoh, null, 'A2');

        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'] as $kolom) {
            $ws->getColumnDimension($kolom)->setAutoSize(true);
        }
        $ws->getStyle('A1:I1')->getFont()->setBold(true);
        $ws->getStyle('A1:I1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DCE6F1');
        $ws->freezePane('A2');

        // Dropdown supaya staff pilih dari daftar, bukan ketik manual —
        // hindari typo yg bikin baris dilewati/gagal saat ribuan baris diisi.
        $this->pasangDropdown($ws, 'B', array_map(fn ($j) => $j->value, CustomerJenis::cases()));
        $this->pasangDropdown($ws, 'F', array_map(fn ($a) => $a->value, CustomerArea::cases()));
        $this->pasangDropdown($ws, 'G', array_map(fn ($s) => $s->value, LeadSource::cases()));
        $this->pasangDropdown($ws, 'H', array_map(fn ($s) => $s->value, CustomerStatus::cases()));

        $petunjuk = $ss->createSheet();
        $petunjuk->setTitle('petunjuk');
        $petunjuk->fromArray([
            ['PETUNJUK IMPORT CUSTOMER'],
            [],
            ['1. Kolom wajib: nama, no_hp. Kolom lain opsional.'],
            ['2. Jenis yang valid: '.implode(', ', array_map(fn ($j) => $j->value, CustomerJenis::cases())).'. Kosong -> default '.CustomerJenis::Perorangan->value.'.'],
            ['3. Area yang valid (huruf kecil): '.implode(', ', array_map(fn ($a) => $a->value, CustomerArea::cases())).'. Kosong -> default '.CustomerArea::Makassar->value.'.'],
            ['4. Sumber Lead yang valid: '.implode(', ', array_map(fn ($s) => $s->value, LeadSource::cases())).'. Kosong -> default '.LeadSource::Whatsapp->value.'.'],
            ['5. Status yang valid: '.implode(', ', array_map(fn ($s) => $s->value, CustomerStatus::cases())).'. Kosong -> default '.CustomerStatus::Lead->value.'.'],
            ['6. No. HP duplikat (sudah ada di sistem / dua baris sama, dibandingkan tanpa memandang format 0812.../62812...) -> baris dilewati, data lama tidak diubah.'],
            ['7. Lokasi (koordinat peta) TIDAK diisi lewat import ini — isi manual per customer di menu Data Customer setelah import (fitur "Link Google Maps" / geser pin).'],
            ['8. Maksimal '.self::MAX_BARIS.' baris data per file; format .xlsx atau .csv (UTF-8).'],
            ['9. Baris contoh di sheet "customer" bisa dihapus sebelum diisi.'],
            ['10. Kolom jenis/area/sumber_lead/status sudah dropdown (klik sel -> muncul panah pilihan) utk baris 2-'.self::BARIS_DROPDOWN_TEMPLATE.'. Kalau data lebih banyak dari itu: blok salah satu sel di baris ber-dropdown, lalu drag kotak kecil di pojok kanan-bawah sel ke bawah sejumlah baris yg dibutuhkan.'],
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
        }, 'template-import-customer.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Proses file import.
     *
     * @return array{berhasil: int, dilewati: int, gagal: int, rincian: array<int, string>}
     */
    public function import(string $pathFile, User $by): array
    {
        $this->assertRole($by, [RoleName::Admin]);

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

        if (! isset($indeks['nama'], $indeks['no_hp'])) {
            throw new BusinessRuleException('Format file tidak dikenali — gunakan template (kolom nama, no_hp wajib ada).');
        }

        if (count($baris) > self::MAX_BARIS) {
            throw new BusinessRuleException('Maksimal '.self::MAX_BARIS.' baris data per file (file Anda '.count($baris).' baris).');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        // Normalisasi no_hp existing sekali di awal (bukan query per baris)
        // supaya import ribuan baris tetap cepat.
        $noHpTerdaftar = Customer::query()->pluck('no_hp')
            ->map(fn ($n) => $this->normalisasiNoHp((string) $n))
            ->filter()
            ->flip();

        $berhasil = 0;
        $dilewati = 0;
        $gagal = 0;
        $rincian = [];
        $noHpDilihat = [];
        $antrianInsert = [];
        $sekarang = now();

        foreach ($baris as $nomor => $nilai) {
            $nomorExcel = $nomor + 2; // baris 1 = header
            $ambil = fn (string $kolom): string => trim((string) ($nilai[$indeks[$kolom]] ?? ''));

            $nama = $ambil('nama');
            $jenisStr = $ambil('jenis');
            $noHp = $ambil('no_hp');
            $email = $ambil('email');
            $alamat = $ambil('alamat');
            $areaStr = $ambil('area');
            $sumberStr = $ambil('sumber_lead');
            $statusStr = $ambil('status');
            $catatan = $ambil('catatan');

            if ($nama === '' && $noHp === '' && $email === '' && $alamat === '') {
                continue; // baris kosong
            }

            if ($nama === '') {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Nama wajib diisi.";
                continue;
            }

            if ($noHp === '') {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: No. HP wajib diisi.";
                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Email tidak valid ({$email}).";
                continue;
            }

            $jenis = $this->resolusi($jenisStr, CustomerJenis::class, CustomerJenis::Perorangan);
            if ($jenis === null) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Jenis tidak valid ('{$jenisStr}').";
                continue;
            }

            $area = $this->resolusi($areaStr, CustomerArea::class, CustomerArea::Makassar);
            if ($area === null) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Area tidak valid ('{$areaStr}').";
                continue;
            }

            $sumber = $this->resolusi($sumberStr, LeadSource::class, LeadSource::Whatsapp);
            if ($sumber === null) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Sumber Lead tidak valid ('{$sumberStr}').";
                continue;
            }

            $status = $this->resolusi($statusStr, CustomerStatus::class, CustomerStatus::Lead);
            if ($status === null) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: Status tidak valid ('{$statusStr}').";
                continue;
            }

            $kunciNoHp = $this->normalisasiNoHp($noHp);

            if ($kunciNoHp !== '' && (isset($noHpTerdaftar[$kunciNoHp]) || isset($noHpDilihat[$kunciNoHp]))) {
                $dilewati++;
                $rincian[] = "Baris {$nomorExcel}: No. HP {$noHp} sudah terdaftar — dilewati (data lama tidak diubah).";
                continue;
            }
            $noHpDilihat[$kunciNoHp] = true;

            $antrianInsert[] = [
                'nama' => $nama,
                'jenis' => $jenis->value,
                'no_hp' => $noHp,
                'email' => $email !== '' ? $email : null,
                'alamat' => $alamat !== '' ? $alamat : null,
                'area' => $area->value,
                'sumber_lead' => $sumber->value,
                'status' => $status->value,
                'catatan' => $catatan !== '' ? $catatan : null,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
            $berhasil++;
        }

        foreach (array_chunk($antrianInsert, self::UKURAN_CHUNK_INSERT) as $chunk) {
            Customer::query()->insert($chunk);
        }

        return compact('berhasil', 'dilewati', 'gagal', 'rincian');
    }

    /**
     * Pasang dropdown pilihan (data validation) pada satu kolom, baris 2
     * s.d. BARIS_DROPDOWN_TEMPLATE — cukup drag/copy ke bawah di Excel bila
     * baris data lebih banyak dari itu.
     *
     * @param  array<int, string>  $pilihan
     */
    private function pasangDropdown(Worksheet $ws, string $kolom, array $pilihan): void
    {
        $validasi = new DataValidation;
        $validasi->setType(DataValidation::TYPE_LIST);
        $validasi->setErrorStyle(DataValidation::STYLE_STOP);
        $validasi->setAllowBlank(true);
        $validasi->setShowDropDown(true);
        $validasi->setShowErrorMessage(true);
        $validasi->setErrorTitle('Pilihan tidak valid');
        $validasi->setError('Pilih salah satu dari daftar dropdown.');
        $validasi->setFormula1('"'.implode(',', $pilihan).'"');

        for ($baris = 2; $baris <= self::BARIS_DROPDOWN_TEMPLATE; $baris++) {
            $ws->getCell("{$kolom}{$baris}")->setDataValidation(clone $validasi);
        }
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
            if (in_array($kunci, ['no_hp', 'no hp', 'no.hp', 'no_hp/wa', 'no_telp', 'telepon'], true)) {
                $kunci = 'no_hp';
            }
            if (in_array($kunci, ['sumber lead', 'lead_source', 'sumber'], true)) {
                $kunci = 'sumber_lead';
            }
            if (in_array($kunci, ['tipe', 'type', 'jenis customer', 'jenis usaha'], true)) {
                $kunci = 'jenis';
            }
            $peta[$kunci] = $i;
        }

        return $peta;
    }

    /**
     * @template T of \App\Enums\CustomerJenis|\App\Enums\CustomerArea|\App\Enums\LeadSource|\App\Enums\CustomerStatus
     *
     * @param  class-string<T>  $enumClass
     * @param  T  $default
     * @return T|null
     */
    private function resolusi(string $nilai, string $enumClass, $default)
    {
        $kecil = strtolower(trim($nilai));

        if ($kecil === '') {
            return $default;
        }

        foreach ($enumClass::cases() as $case) {
            if ($case->value === $kecil) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Kunci pembanding no_hp yg menyamakan format 0812xxx / +62812xxx /
     * 62812xxx / dengan spasi-strip, supaya deteksi duplikat tetap akurat
     * meski format penulisan berbeda antar baris (umum pada data Gmail).
     */
    private function normalisasiNoHp(string $noHp): string
    {
        $digit = preg_replace('/\D/', '', $noHp) ?? '';

        if ($digit === '') {
            return '';
        }

        if (str_starts_with($digit, '0')) {
            return '62'.substr($digit, 1);
        }

        return $digit;
    }
}
