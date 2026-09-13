<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UnitType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\User;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import massal data Unit AC milik SATU customer (khususnya company dgn
 * banyak unit, mis. sekolah/kantor puluhan-ratusan ruangan) — satu baris
 * = satu unit AC fisik. Beda dari CustomerImportService: customer sudah
 * ditentukan (bukan per baris), assign teknisi/order TIDAK termasuk di
 * sini (menyusul saat input orderan — lihat dev-plan/12 §3.4/§3.10).
 *
 * Aturan:
 * - Importir: Admin (mengikuti CustomerAcUnitPolicy::create).
 * - Kolom: kode_unit* | kode_ruangan* | jenis_unit | pk | catatan.
 * - Idempoten: kode_unit duplikat (DB utk customer ybs / antar baris)
 *   DILEWATI, tidak ada unit lama yang diubah.
 * - Batas: 2000 baris data per file; ekstensi .xlsx/.csv.
 */
class CustomerAcUnitImportService
{
    use RestrictsByRole;

    public const HEADER = ['kode_unit', 'kode_ruangan', 'jenis_unit', 'pk', 'catatan'];

    public const MAX_BARIS = 2000;

    private const EKSTENSI_DIDUKUNG = ['xlsx', 'csv'];

    /**
     * Spreadsheet template: sheet "unit_ac" (header + contoh) dan
     * sheet "petunjuk".
     */
    public function template(): Spreadsheet
    {
        $ss = new Spreadsheet;

        $ws = $ss->getActiveSheet();
        $ws->setTitle('unit_ac');
        $ws->fromArray([self::HEADER], null, 'A1');

        $contoh = [
            ['AC-001', 'Kelas 3A', 'split', '1 PK', ''],
            ['AC-002', 'Ruang Guru', 'cassette', '2 PK', 'Instalasi 2024'],
        ];
        $ws->fromArray($contoh, null, 'A2');

        foreach (['A', 'B', 'C', 'D', 'E'] as $kolom) {
            $ws->getColumnDimension($kolom)->setAutoSize(true);
        }
        $ws->getStyle('A1:E1')->getFont()->setBold(true);
        $ws->getStyle('A1:E1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DCE6F1');
        $ws->freezePane('A2');

        $this->pasangDropdown($ws, 'C', array_map(fn ($j) => $j->value, UnitType::cases()));

        $petunjuk = $ss->createSheet();
        $petunjuk->setTitle('petunjuk');
        $petunjuk->fromArray([
            ['PETUNJUK IMPORT UNIT AC'],
            [],
            ['1. File ini utk SATU customer (company) sekaligus — pilih customer-nya dulu di halaman Unit AC sebelum upload.'],
            ['2. Kolom wajib: kode_unit, kode_ruangan. Kolom lain opsional.'],
            ['3. kode_unit harus unik utk customer ini (mis. AC-001, AC-002, ...) — dipakai sbg identitas unit fisik utk riwayat pencucian nantinya.'],
            ['4. jenis_unit yang valid: '.implode(', ', array_map(fn ($j) => $j->value, UnitType::cases())).'.'],
            ['5. pk diisi bebas (mis. "1/2 PK", "1 PK", "1.5 PK", "2 PK").'],
            ['6. kode_unit yang sudah ada utk customer ini -> baris dilewati, data lama tidak diubah.'],
            ['7. Maksimal '.self::MAX_BARIS.' baris data per file; format .xlsx atau .csv (UTF-8).'],
        ], null, 'A1');
        $petunjuk->getColumnDimension('A')->setWidth(110);

        return $ss;
    }

    public function unduhTemplate(): StreamedResponse
    {
        $ss = $this->template();

        return response()->streamDownload(function () use ($ss): void {
            (new Xlsx($ss))->save('php://output');
        }, 'template-import-unit-ac.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Proses file import utk satu customer.
     *
     * @return array{berhasil: int, dilewati: int, gagal: int, rincian: array<int, string>}
     */
    public function import(string $pathFile, Customer $customer, User $by): array
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

        if (! isset($indeks['kode_unit'], $indeks['kode_ruangan'])) {
            throw new BusinessRuleException('Format file tidak dikenali — gunakan template (kolom kode_unit, kode_ruangan wajib ada).');
        }

        if (count($baris) > self::MAX_BARIS) {
            throw new BusinessRuleException('Maksimal '.self::MAX_BARIS.' baris data per file (file Anda '.count($baris).' baris).');
        }

        $kodeTerdaftar = $customer->acUnits()->pluck('kode_unit')
            ->map(fn ($k) => strtolower($k))
            ->flip();

        $berhasil = 0;
        $dilewati = 0;
        $gagal = 0;
        $rincian = [];
        $kodeDilihat = [];
        $antrianInsert = [];
        $sekarang = now();

        foreach ($baris as $nomor => $nilai) {
            $nomorExcel = $nomor + 2; // baris 1 = header
            $ambil = fn (string $kolom): string => trim((string) ($nilai[$indeks[$kolom]] ?? ''));

            $kodeUnit = $ambil('kode_unit');
            $kodeRuangan = $ambil('kode_ruangan');
            $jenisUnitStr = $ambil('jenis_unit');
            $pk = $ambil('pk');
            $catatan = $ambil('catatan');

            if ($kodeUnit === '' && $kodeRuangan === '') {
                continue; // baris kosong
            }

            if ($kodeUnit === '') {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: kode_unit wajib diisi.";
                continue;
            }

            if ($kodeRuangan === '') {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: kode_ruangan wajib diisi.";
                continue;
            }

            $jenisUnit = null;
            if ($jenisUnitStr !== '') {
                $jenisUnit = UnitType::tryFrom(strtolower($jenisUnitStr));
                if ($jenisUnit === null) {
                    $gagal++;
                    $rincian[] = "Baris {$nomorExcel}: jenis_unit tidak valid ('{$jenisUnitStr}').";
                    continue;
                }
            }

            $kunciKode = strtolower($kodeUnit);

            if (isset($kodeTerdaftar[$kunciKode]) || isset($kodeDilihat[$kunciKode])) {
                $dilewati++;
                $rincian[] = "Baris {$nomorExcel}: kode_unit {$kodeUnit} sudah terdaftar utk customer ini — dilewati.";
                continue;
            }
            $kodeDilihat[$kunciKode] = true;

            $antrianInsert[] = [
                'customer_id' => $customer->id,
                'kode_unit' => $kodeUnit,
                'kode_ruangan' => $kodeRuangan,
                'jenis_unit' => $jenisUnit?->value,
                'pk' => $pk !== '' ? $pk : null,
                'catatan' => $catatan !== '' ? $catatan : null,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
            $berhasil++;
        }

        if ($antrianInsert !== []) {
            CustomerAcUnit::query()->insert($antrianInsert);
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
            if (in_array($kunci, ['kode unit', 'kode_ac', 'no_unit'], true)) {
                $kunci = 'kode_unit';
            }
            if (in_array($kunci, ['ruangan', 'kode ruangan', 'lokasi', 'kamar'], true)) {
                $kunci = 'kode_ruangan';
            }
            if (in_array($kunci, ['jenis unit', 'tipe', 'tipe_ac', 'tipe unit'], true)) {
                $kunci = 'jenis_unit';
            }
            $peta[$kunci] = $i;
        }

        return $peta;
    }

    /**
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

        for ($baris = 2; $baris <= 500; $baris++) {
            $ws->getCell("{$kolom}{$baris}")->setDataValidation(clone $validasi);
        }
    }
}
