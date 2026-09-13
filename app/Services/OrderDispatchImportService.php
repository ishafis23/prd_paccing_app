<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Import Excel dispatch massal (dev-plan/12 §3.4) — bulk-create SATU
 * Order dgn banyak `order_items` sekaligus utk klien korporat banyak
 * unit/ruangan (mis. 100 ruangan dicuci di kunjungan yg sama). Beda dari
 * CustomerAcUnitImportService: itu bikin master data unit, ini bikin
 * ORDER dari unit yg SUDAH terdaftar (dev-plan/12 §3.10).
 *
 * Aturan:
 * - Importir: Admin/Owner.
 * - Kolom: kode_unit* | harga | catatan. kode_unit harus SUDAH terdaftar
 *   (`CustomerAcUnit`) utk customer terkait.
 * - Jenis layanan (katalog)/harga-default/jadwal/tim ditentukan SEKALI
 *   lewat form (bukan per baris) — harga per baris opsional utk override
 *   (mis. unit tertentu kena biaya ekstra).
 * - Batas: 500 baris per file (satu order/kunjungan, bukan ribuan unit).
 */
class OrderDispatchImportService
{
    use RestrictsByRole;

    public const HEADER = ['kode_unit', 'harga', 'catatan'];

    public const MAX_BARIS = 500;

    private const EKSTENSI_DIDUKUNG = ['xlsx', 'csv'];

    public function template(): Spreadsheet
    {
        $ss = new Spreadsheet;

        $ws = $ss->getActiveSheet();
        $ws->setTitle('dispatch');
        $ws->fromArray([self::HEADER], null, 'A1');
        $ws->fromArray([
            ['AC-001', '', ''],
            ['AC-002', '150000', 'Ekstra tinggi, perlu tangga'],
        ], null, 'A2');

        foreach (['A', 'B', 'C'] as $kolom) {
            $ws->getColumnDimension($kolom)->setAutoSize(true);
        }
        $ws->getStyle('A1:C1')->getFont()->setBold(true);
        $ws->getStyle('A1:C1')->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE6F1');
        $ws->freezePane('A2');

        $petunjuk = $ss->createSheet();
        $petunjuk->setTitle('petunjuk');
        $petunjuk->fromArray([
            ['PETUNJUK IMPORT ORDER MASSAL'],
            [],
            ['1. File ini menghasilkan SATU order (satu kunjungan/trip) mencakup banyak unit AC milik SATU customer.'],
            ['2. kode_unit WAJIB sudah terdaftar di tab "Unit AC" customer ini.'],
            ['3. Kolom harga opsional — kosongkan utk pakai harga default dari form, isi utk override per unit (mis. ekstra biaya).'],
            ['4. Jenis layanan (katalog), harga default, jadwal, & tim ditentukan sekali lewat form — bukan per baris.'],
            ['5. Maksimal '.self::MAX_BARIS.' baris per file; format .xlsx atau .csv (UTF-8).'],
        ], null, 'A1');
        $petunjuk->getColumnDimension('A')->setWidth(110);

        return $ss;
    }

    public function unduhTemplate(): StreamedResponse
    {
        $ss = $this->template();

        return response()->streamDownload(function () use ($ss): void {
            (new Xlsx($ss))->save('php://output');
        }, 'template-import-order-massal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array{service_catalog_id: int, harga?: float|int|string|null, tanggal_jadwal?: ?string, jam_jadwal?: ?string, team_id?: ?int, catatan_admin?: ?string}  $meta
     * @return array{order: ?Order, berhasil: int, gagal: int, rincian: array<int, string>}
     */
    public function import(string $pathFile, Customer $customer, array $meta, User $by): array
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $catalog = ServiceCatalog::find($meta['service_catalog_id'] ?? null);
        if ($catalog === null) {
            throw new BusinessRuleException('Jenis layanan wajib dipilih.');
        }

        if (! $catalog->aktif) {
            throw new BusinessRuleException('Jenis layanan sedang nonaktif.');
        }

        $hargaDefault = filled($meta['harga'] ?? null) ? (float) $meta['harga'] : (float) $catalog->harga;
        if ($hargaDefault < 0) {
            throw new BusinessRuleException('Harga default tidak valid.');
        }

        $namaLayanan = str($catalog->jenis_layanan->value)->headline()->toString();
        $kategori = $catalog->jenis_layanan;

        $ekstensi = strtolower(pathinfo($pathFile, PATHINFO_EXTENSION));
        if (! in_array($ekstensi, self::EKSTENSI_DIDUKUNG, true)) {
            throw new BusinessRuleException('Format file harus .xlsx atau .csv.');
        }

        try {
            $reader = IOFactory::createReaderForFile($pathFile);
            $reader->setReadDataOnly(true);
            $ss = $reader->load($pathFile);
        } catch (Throwable) {
            throw new BusinessRuleException('File tidak dapat dibaca. Pastikan file .xlsx/.csv tidak rusak.');
        }

        $baris = $this->ekstrakBaris($ss->getActiveSheet()->toArray(null, false, false, false));
        $indeks = $this->indeksKolom($baris->shift() ?? []);

        if (! isset($indeks['kode_unit'])) {
            throw new BusinessRuleException('Format file tidak dikenali — gunakan template (kolom kode_unit wajib ada).');
        }

        if (count($baris) > self::MAX_BARIS) {
            throw new BusinessRuleException('Maksimal '.self::MAX_BARIS.' baris data per file (file Anda '.count($baris).' baris).');
        }

        $unitTerdaftar = $customer->acUnits()->get()->keyBy(fn ($u) => strtolower($u->kode_unit));

        $gagal = 0;
        $rincian = [];
        $kodeDilihat = [];
        $antrian = [];

        foreach ($baris as $nomor => $nilai) {
            $nomorExcel = $nomor + 2; // baris 1 = header
            $ambil = fn (string $kolom): string => trim((string) ($nilai[$indeks[$kolom]] ?? ''));

            $kodeUnit = $ambil('kode_unit');
            $hargaStr = $ambil('harga');
            $catatan = $ambil('catatan');

            if ($kodeUnit === '') {
                continue; // baris kosong
            }

            $kunciKode = strtolower($kodeUnit);

            if (isset($kodeDilihat[$kunciKode])) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: kode_unit {$kodeUnit} duplikat dalam file — dilewati.";

                continue;
            }

            $unit = $unitTerdaftar->get($kunciKode);
            if ($unit === null) {
                $gagal++;
                $rincian[] = "Baris {$nomorExcel}: kode_unit {$kodeUnit} tidak ditemukan di data Unit AC customer ini.";

                continue;
            }

            $harga = $hargaDefault;
            if ($hargaStr !== '') {
                if (! is_numeric($hargaStr) || (float) $hargaStr < 0) {
                    $gagal++;
                    $rincian[] = "Baris {$nomorExcel}: harga tidak valid ('{$hargaStr}').";

                    continue;
                }
                $harga = (float) $hargaStr;
            }

            $kodeDilihat[$kunciKode] = true;

            $antrian[] = [
                'service_catalog_id' => $catalog->id,
                'customer_ac_unit_id' => $unit->id,
                'nama_layanan' => $namaLayanan,
                'kategori' => $kategori,
                'harga' => $harga,
                'jumlah' => 1,
                'catatan' => $catatan !== '' ? $catatan : null,
            ];
        }

        if ($antrian === []) {
            return ['order' => null, 'berhasil' => 0, 'gagal' => $gagal, 'rincian' => $rincian];
        }

        $order = DB::transaction(function () use ($customer, $catalog, $meta, $antrian, $by): Order {
            $order = Order::create([
                'customer_id' => $customer->id,
                'service_catalog_id' => $catalog->id,
                'jumlah_unit' => 1,
                'alamat_pengerjaan' => $customer->alamat,
                'jenis_pelanggan' => $customer->jenis?->value,
                'tanggal_jadwal' => $meta['tanggal_jadwal'] ?? null,
                'jam_jadwal' => $meta['jam_jadwal'] ?? null,
                'status' => OrderStatus::Baru,
                'catatan_admin' => filled($meta['catatan_admin'] ?? null) ? $meta['catatan_admin'] : null,
                'created_by' => $by->id,
            ]);

            // Order::booted() otomatis bikin 1 order_item stub dari
            // service_catalog_id (nama_layanan/kategori/harga katalog,
            // belum tertaut unit AC manapun) — baris Excel pertama dipakai
            // utk mengisi stub itu (termasuk override harga bila ada)
            // drpd bikin baris kosong nganggur.
            $stub = $order->orderItems()->first();
            $stub->update(array_shift($antrian));

            foreach ($antrian as $rincianItem) {
                $order->orderItems()->create($rincianItem);
            }

            if (filled($meta['team_id'] ?? null)) {
                app(OrderService::class)->assignTeam($order, Team::findOrFail($meta['team_id']), $by);
            }

            return $order->fresh();
        });

        return [
            'order' => $order,
            'berhasil' => $order->orderItems()->count(),
            'gagal' => $gagal,
            'rincian' => $rincian,
        ];
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
            if (in_array($kunci, ['kode unit', 'kode_ac', 'no_unit', 'unit'], true)) {
                $kunci = 'kode_unit';
            }
            $peta[$kunci] = $i;
        }

        return $peta;
    }
}
