<?php

namespace App\Services;

use App\Support\Terbilang;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Merender keluaran LaporanRekapService menjadi workbook 6 sheet
 * (Operasional, Material, Perawatan, Omset Jasa, Omset Material, REKAP DANA).
 * Jumlah & rekap memakai formula SUM/SUMIF; terbilang berupa teks.
 */
class LaporanRekapExcelService
{
    private const FORMAT_RUPIAH = '#,##0';

    private const FORMAT_TANGGAL = 'dd mmm yyyy';

    private const HEADER_FILL = 'FFE7EEF7';

    private const AWAL = 10;

    /**
     * @param  array<string, mixed>  $data  keluaran LaporanRekapService::susun()
     */
    public function render(array $data, string $namaUsaha): string
    {
        $book = new Spreadsheet;
        $book->removeSheetByIndex(0);

        /** @var array<string, array{awal: int, akhir: int}> $jangkauan */
        $jangkauan = [];

        $sheetOp = $this->sheetRincian($book, 'Operasional', 'Pengeluaran Operasional Rutin', 'Operasional Rutin', 'Team / Ket', $data['operasional'], $data, $namaUsaha, $jangkauan);
        $this->tabelBonus($sheetOp, $data);
        $this->sheetRincian($book, 'Material', 'Pengeluaran Material', 'Pengeluaran Material', 'Ket/Uraian', $data['material'], $data, $namaUsaha, $jangkauan);
        $this->sheetRincian($book, 'Perawatan', 'Pengeluaran Perawatan', 'Pengeluaran Perawatan', 'Ket/Uraian', $data['perawatan'], $data, $namaUsaha, $jangkauan);
        $this->sheetRincian($book, 'Omset Jasa', 'Omset Jasa', 'Omset Jasa', 'Team / Customer', $data['omset_jasa'], $data, $namaUsaha, $jangkauan);
        $this->sheetRincian($book, 'Omset Material', 'Omset Material', 'Omset Material', 'Team / Customer', $data['omset_material'], $data, $namaUsaha, $jangkauan);
        $this->sheetRekap($book, $data, $namaUsaha, $jangkauan);

        $book->setActiveSheetIndex(0);

        $tmp = tempnam(sys_get_temp_dir(), 'rekap');
        (new Xlsx($book))->save($tmp);
        $isi = (string) file_get_contents($tmp);
        @unlink($tmp);
        $book->disconnectWorksheets();

        return $isi;
    }

    /**
     * @param  array<int, array{tanggal: string, ket: string, nominal: float}>  $rows
     * @param  array<string, mixed>  $data
     * @param  array<string, array{awal: int, akhir: int}>  $jangkauan
     */
    private function sheetRincian(Spreadsheet $book, string $nama, string $judul, string $kolomNominal, string $kolomKet, array $rows, array $data, string $namaUsaha, array &$jangkauan): Worksheet
    {
        $ws = new Worksheet($book, $nama);
        $book->addSheet($ws);
        $this->kop($ws, $namaUsaha, $judul, $data['bulan'], 'C');

        foreach (['B' => 'Tanggal', 'C' => $kolomKet, 'D' => $kolomNominal, 'E' => 'Jumlah', 'F' => 'Terbilang'] as $kol => $teks) {
            $ws->setCellValue($kol.'9', $teks);
        }
        $this->gayaHeader($ws, 'B9:F9');

        $perTanggal = collect($rows)->groupBy('tanggal');
        $r = self::AWAL;

        foreach ($data['tanggal'] as $t) {
            $grup = $perTanggal->get($t, collect());
            $awalGrup = $r;
            $baris = $grup->isEmpty() ? [null] : $grup->values()->all();

            foreach ($baris as $item) {
                $ws->setCellValue('B'.$r, Date::PHPToExcel(CarbonImmutable::parse($t)->toDateTime()));
                if ($item !== null) {
                    $ws->setCellValue('C'.$r, $item['ket']);
                    $ws->setCellValue('D'.$r, $item['nominal']);
                }
                $r++;
            }

            $akhirGrup = $r - 1;
            $jumlah = (float) $grup->sum('nominal');
            $ws->setCellValue('E'.$awalGrup, "=SUM(D{$awalGrup}:D{$akhirGrup})");
            $ws->setCellValue('F'.$awalGrup, Terbilang::rupiah($jumlah));
        }

        $akhir = $r - 1;
        $jangkauan[$nama] = ['awal' => self::AWAL, 'akhir' => $akhir];

        $total = array_sum(array_column($rows, 'nominal'));
        $ws->setCellValue('B'.$r, 'TOTAL');
        $ws->setCellValue('D'.$r, '=SUM(D'.self::AWAL.":D{$akhir})");
        $ws->setCellValue('E'.$r, '=SUM(E'.self::AWAL.":E{$akhir})");
        $ws->setCellValue('B'.($r + 1), 'TERBILANG');
        $ws->setCellValue('D'.($r + 1), Terbilang::rupiah($total));
        $ws->getStyle("B{$r}:F".($r + 1))->getFont()->setBold(true);

        $ws->getStyle('B'.self::AWAL.":B{$akhir}")->getNumberFormat()->setFormatCode(self::FORMAT_TANGGAL);
        $ws->getStyle('D'.self::AWAL.":E{$r}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
        $ws->getStyle('B9:F'.($r + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle('F'.self::AWAL.":F{$akhir}")->getAlignment()->setWrapText(true);

        foreach (['B' => 14, 'C' => 38, 'D' => 18, 'E' => 18, 'F' => 46] as $kol => $lebar) {
            $ws->getColumnDimension($kol)->setWidth($lebar);
        }
        $ws->freezePane('B10');

        return $ws;
    }

    /**
     * Tabel "Bonus Pekerja yang termasuk dalam Pengeluaran Operasional Rutin"
     * di kanan sheet Operasional (info saja, tidak masuk total).
     *
     * @param  array<string, mixed>  $data
     */
    private function tabelBonus(Worksheet $ws, array $data): void
    {
        $pekerja = $data['bonus']['pekerja'];
        $ws->setCellValue('H5', 'Bonus Pekerja yang termasuk dalam Pengeluaran Operasional Rutin');
        $ws->getStyle('H5')->getFont()->setBold(true);
        $ws->setCellValue('H9', 'Tanggal');

        foreach ($pekerja as $i => $nama) {
            $ws->setCellValue([9 + $i, 9], $nama);
        }
        $kolAkhir = $ws->getCell([8 + max(1, count($pekerja)), 9])->getColumn();
        $this->gayaHeader($ws, "H9:{$kolAkhir}9");

        $r = self::AWAL;
        foreach ($data['tanggal'] as $t) {
            $ws->setCellValue('H'.$r, Date::PHPToExcel(CarbonImmutable::parse($t)->toDateTime()));
            foreach ($pekerja as $i => $nama) {
                $nilai = $data['bonus']['baris'][$t][$nama] ?? null;
                if ($nilai !== null) {
                    $ws->setCellValue([9 + $i, $r], $nilai);
                }
            }
            $r++;
        }

        $akhir = $r - 1;
        $ws->setCellValue('H'.$r, 'TOTAL');
        foreach ($pekerja as $i => $nama) {
            $kol = $ws->getCell([9 + $i, 9])->getColumn();
            $ws->setCellValue($kol.$r, "=SUM({$kol}".self::AWAL.":{$kol}{$akhir})");
        }

        $ws->getStyle('H'.self::AWAL.":H{$akhir}")->getNumberFormat()->setFormatCode(self::FORMAT_TANGGAL);
        $ws->getStyle('I'.self::AWAL.":{$kolAkhir}{$r}")->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
        $ws->getStyle("H9:{$kolAkhir}{$r}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("H{$r}:{$kolAkhir}{$r}")->getFont()->setBold(true);
        $ws->getColumnDimension('G')->setWidth(4);
        $ws->getColumnDimension('H')->setWidth(14);
        foreach ($pekerja as $i => $nama) {
            $ws->getColumnDimension($ws->getCell([9 + $i, 9])->getColumn())->setWidth(14);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{awal: int, akhir: int}>  $jangkauan
     */
    private function sheetRekap(Spreadsheet $book, array $data, string $namaUsaha, array $jangkauan): void
    {
        $ws = new Worksheet($book, 'REKAP DANA');
        $book->addSheet($ws);
        $this->kop($ws, $namaUsaha, 'Total Pengeluaran & Pendapatan', $data['bulan'], 'C');

        $ws->setCellValue('B9', 'Tanggal');
        $ws->setCellValue('C9', 'PENGELUARAN');
        $ws->setCellValue('I9', 'PENDAPATAN');
        $ws->mergeCells('C9:H9');
        $ws->mergeCells('I9:K9');
        $this->gayaHeader($ws, 'B9:K9');

        $kepala = ['C' => 'Operasional Rutin', 'D' => 'Pengeluaran Material', 'E' => 'Pengeluaran Perawatan', 'F' => 'Gaji/Honor', 'G' => 'NAMA', 'H' => 'Jumlah', 'I' => 'Omset Jasa', 'J' => 'Omset Material', 'K' => 'Jumlah'];
        foreach ($kepala as $kol => $teks) {
            $ws->setCellValue($kol.'10', $teks);
        }
        $this->gayaHeader($ws, 'B10:K10');

        $sumif = function (string $sheet, string $sel) use ($jangkauan): string {
            $j = $jangkauan[$sheet];

            return "SUMIF('{$sheet}'!\$B\${$j['awal']}:\$B\${$j['akhir']},\$B{$sel},'{$sheet}'!\$D\${$j['awal']}:\$D\${$j['akhir']})";
        };

        $r = 11;
        foreach ($data['rekap']['baris'] as $b) {
            $ws->setCellValue('B'.$r, Date::PHPToExcel(CarbonImmutable::parse($b['tanggal'])->toDateTime()));
            $ws->setCellValue('C'.$r, '='.$sumif('Operasional', (string) $r));
            $ws->setCellValue('D'.$r, '='.$sumif('Material', (string) $r));
            $ws->setCellValue('E'.$r, '='.$sumif('Perawatan', (string) $r));
            if ($b['gaji'] > 0) {
                $ws->setCellValue('F'.$r, $b['gaji']);
            }
            $ws->setCellValue('G'.$r, $b['nama']);
            $ws->setCellValue('H'.$r, "=SUM(C{$r}:F{$r})");
            $ws->setCellValue('I'.$r, '='.$sumif('Omset Jasa', (string) $r));
            $ws->setCellValue('J'.$r, '='.$sumif('Omset Material', (string) $r));
            $ws->setCellValue('K'.$r, "=SUM(I{$r}:J{$r})");
            $r++;
        }

        $akhir = $r - 1;
        $t = $data['rekap']['total'];
        $ws->setCellValue('B'.$r, 'TOTAL');
        foreach (['C', 'D', 'E', 'F', 'H', 'I', 'J', 'K'] as $kol) {
            $ws->setCellValue($kol.$r, "=SUM({$kol}11:{$kol}{$akhir})");
        }

        $tb = $r + 1;
        $ws->setCellValue('B'.$tb, 'TERBILANG');
        foreach (['C' => 'operasional', 'D' => 'material', 'E' => 'perawatan', 'F' => 'gaji', 'H' => 'pengeluaran', 'I' => 'omset_jasa', 'J' => 'omset_material', 'K' => 'pendapatan'] as $kol => $kunci) {
            $ws->setCellValue($kol.$tb, Terbilang::rupiah($t[$kunci]));
        }

        $ws->setCellValue('B'.($r + 2), 'JUMLAH');
        $ws->setCellValue('C'.($r + 2), "=H{$r}");
        $ws->setCellValue('I'.($r + 2), "=K{$r}");
        $ws->setCellValue('B'.($r + 3), 'LABA');
        $ws->setCellValue('C'.($r + 3), '=I'.($r + 2).'-C'.($r + 2));
        $ws->setCellValue('D'.($r + 3), Terbilang::rupiah($data['rekap']['laba']));

        $ws->getStyle('B11:B'.$akhir)->getNumberFormat()->setFormatCode(self::FORMAT_TANGGAL);
        $ws->getStyle('C11:K'.($r + 3))->getNumberFormat()->setFormatCode(self::FORMAT_RUPIAH);
        $ws->getStyle('B9:K'.($r + 3))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("B{$r}:K".($r + 3))->getFont()->setBold(true);
        $ws->getStyle("C{$tb}:K{$tb}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $ws->getStyle("C{$tb}:K{$tb}")->getFont()->setBold(false)->setItalic(true)->setSize(9);

        $ws->getColumnDimension('B')->setWidth(14);
        foreach (['C', 'D', 'E', 'F', 'H', 'I', 'J', 'K'] as $kol) {
            $ws->getColumnDimension($kol)->setWidth(20);
        }
        $ws->getColumnDimension('G')->setWidth(22);
        $ws->freezePane('C11');
    }

    private function kop(Worksheet $ws, string $namaUsaha, string $judul, string $bulan, string $kol): void
    {
        $ws->setCellValue($kol.'3', 'Laporan Keuangan Bulanan '.$namaUsaha);
        $ws->setCellValue($kol.'4', $judul);
        $ws->setCellValue($kol.'5', 'Periode '.$bulan);
        $ws->setCellValue($kol.'6', 'Dalam Rupiah (Rp)');
        $ws->getStyle($kol.'3:'.$kol.'4')->getFont()->setBold(true)->setSize(13);
    }

    private function gayaHeader(Worksheet $ws, string $range): void
    {
        $ws->getStyle($range)->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
        ]);
    }
}
