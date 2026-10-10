<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Models\WorkReportPhoto;
use App\Support\FotoLaporanSlot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * SATU-SATUNYA penyusun data Laporan Pengerjaan (format Circle K, dev-plan/21
 * §6). Preview HTML, PDF per order, PDF bulanan, dan nanti portal customer
 * (dev-plan/22) semuanya memakai service ini — Blade/controller hanya
 * merender struktur yang dikembalikan di sini.
 *
 * Tidak bergantung pada Filament/Livewire/request: parameternya model Order
 * atau koleksi Order. Tidak ada akses HTTP; foto dibaca dari disk lokal dan
 * diperkecil oleh FotoKompresService.
 */
class LaporanPengerjaanService
{
    private const ANGKA = [
        1 => 'satu', 2 => 'dua', 3 => 'tiga', 4 => 'empat', 5 => 'lima',
        6 => 'enam', 7 => 'tujuh', 8 => 'delapan', 9 => 'sembilan', 10 => 'sepuluh',
    ];

    public function __construct(
        private readonly UnitReportService $units,
        private readonly FotoKompresService $kompres,
        private readonly BusinessInfoService $usaha,
    ) {}

    /**
     * Dokumen siap-render untuk satu order (preview / PDF per order).
     *
     * @return array<string, mixed>
     */
    public function dokumenOrder(Order $order): array
    {
        return $this->dokumen(collect([$order]));
    }

    /**
     * Dokumen siap-render untuk sekumpulan order (urutan koleksi dipertahankan).
     *
     * @param  iterable<int, Order>  $orders
     * @return array{kop: array<string, mixed>, judul: string, subjudul: ?string, kunjungan: array<int, array<string, mixed>>}
     */
    public function dokumen(iterable $orders, ?string $customerNama = null, ?string $subjudul = null): array
    {
        $kunjungan = collect($orders)->map(fn (Order $o): array => $this->untukOrder($o))->values();
        $nama = $customerNama ?? $kunjungan->first()['customer'] ?? '';

        return [
            'kop' => $this->kop(),
            'judul' => trim('LAPORAN MAINTENANCE AC '.mb_strtoupper((string) $nama)),
            'subjudul' => $subjudul,
            'kunjungan' => $kunjungan->all(),
        ];
    }

    /**
     * Satu kunjungan (= satu order): blok info + daftar unit beserta fotonya.
     *
     * @return array{
     *     order_id: int,
     *     customer: string,
     *     cabang: ?string,
     *     tanggal: ?\Illuminate\Support\Carbon,
     *     tanggal_label: ?string,
     *     keterangan: string,
     *     unit: array<int, array<string, mixed>>,
     *     jumlah_foto: int
     * }
     */
    public function untukOrder(Order $order): array
    {
        $order->loadMissing(['customer', 'customerAddress', 'serviceCatalog', 'orderItems', 'workReports.photos']);

        $cabang = $this->teks($order->customerAddress?->nama_lokasi) ?? $this->teks($order->alamat_pengerjaan);
        $tanggal = $order->tanggal_jadwal;

        $unit = $this->susunUnit($order, $cabang);

        return [
            'order_id' => $order->id,
            'customer' => (string) ($order->customer?->nama ?? ''),
            'cabang' => $cabang,
            'tanggal' => $tanggal,
            'tanggal_label' => $tanggal?->copy()->locale('id')->translatedFormat('j F Y'),
            'keterangan' => $this->keterangan($order),
            'unit' => $unit,
            'jumlah_foto' => collect($unit)->sum(fn (array $u): int => collect($u['foto'])->where('ada', true)->count()),
        ];
    }

    /**
     * Identitas usaha untuk kop & footer.
     *
     * @return array{nama: string, alamat: ?string, kontak_wa: ?string, logo_file: ?string}
     */
    public function kop(): array
    {
        $logo = $this->usaha->data()->logo_path;
        $file = null;

        if ($logo && Storage::disk('public')->exists($logo)) {
            $path = Storage::disk('public')->path($logo);
            $file = filesize($path) <= 2 * 1024 * 1024 ? $path : null;
        }

        return [
            'nama' => $this->usaha->namaUsaha(),
            'alamat' => $this->usaha->alamatTampil(),
            'kontak_wa' => $this->usaha->kontakWaTampil(),
            'logo_file' => $file,
        ];
    }

    /**
     * File lokal → data URI. Dipakai preview & PDF supaya tidak ada URL
     * /storage/... maupun permintaan HTTP.
     */
    public function dataUri(?string $file): ?string
    {
        if ($file === null || ! is_file($file)) {
            return null;
        }

        $info = @getimagesize($file);
        $isi = @file_get_contents($file);

        if ($info === false || $isi === false) {
            return null;
        }

        return 'data:'.$info['mime'].';base64,'.base64_encode($isi);
    }

    /**
     * Caption unit mengikuti contoh klien:
     * `CK DADI · UNIT SATU · DI ATAS TOILET · CUCI STANDAR · RPM 7,3 · SUHU 17,5°C`.
     * Bagian tanpa data dilewati (tidak pernah menulis nol/kosong).
     */
    public function captionUnit(OrderUnitReport $unit, ?string $cabang = null): string
    {
        $bagian = [
            $this->teks($unit->lokasi_label) ?? $cabang,
            'UNIT '.$this->terbilang((int) $unit->unit_no),
            $this->teks($unit->posisi),
            $this->teks($unit->jenis_pekerjaan),
            $this->angka($unit->rpm) !== null ? 'RPM '.$this->angka($unit->rpm) : null,
            $this->angka($unit->suhu) !== null ? 'SUHU '.$this->angka($unit->suhu).'°C' : null,
        ];

        return collect($bagian)
            ->filter(fn ($b): bool => $b !== null && $b !== '')
            ->map(fn (string $b): string => mb_strtoupper($b))
            ->implode(' · ');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function susunUnit(Order $order, ?string $cabang): array
    {
        $fotoSemua = $order->workReports->flatMap->photos->sortBy('urutan')->values();
        $baris = $this->units->unitAktif($order);

        if ($baris->isEmpty()) {
            return $this->unitTanpaData($order, $cabang, $fotoSemua);
        }

        $unitPertama = $baris->groupBy('order_item_id')->map(fn (Collection $g) => $g->min('unit_no'));
        $hasil = [];

        foreach ($baris as $u) {
            /** @var OrderItem $item */
            $item = $u->orderItem;
            $milik = $fotoSemua->filter(function (WorkReportPhoto $f) use ($u, $unitPertama): bool {
                if ($f->order_unit_report_id !== null) {
                    return (int) $f->order_unit_report_id === (int) $u->id;
                }

                // Foto lama (sebelum foto menempel per unit) = milik unit pertama barisnya.
                return (int) $f->order_item_id === (int) $u->order_item_id
                    && (int) $u->unit_no === (int) $unitPertama->get($u->order_item_id);
            });

            $caption = $this->captionUnit($u, $cabang);
            $label = FotoLaporanSlot::untuk($item->kategori);

            $hasil[] = [
                'nomor' => (int) $u->unit_no,
                'caption' => $caption,
                'catatan' => $u->kondisi === OrderUnitReport::KONDISI_TIDAK_NORMAL ? $this->teks($u->catatan_kondisi) : null,
                'foto' => $this->susunFoto($milik, $label, $caption),
            ];
        }

        return $hasil;
    }

    /**
     * Order lama / belum ada baris unit: satu blok per baris layanan aktif.
     *
     * @param  Collection<int, WorkReportPhoto>  $fotoSemua
     * @return array<int, array<string, mixed>>
     */
    private function unitTanpaData(Order $order, ?string $cabang, Collection $fotoSemua): array
    {
        $items = $order->orderItems->reject(fn (OrderItem $i): bool => $i->dibatalkan() || $i->penyesuaian)->values();
        $hasil = [];

        foreach ($items as $idx => $item) {
            $caption = collect([$cabang, $this->namaLayanan($item)])
                ->filter()->map(fn ($b) => mb_strtoupper((string) $b))->implode(' · ');

            $hasil[] = [
                'nomor' => null,
                'caption' => $caption,
                'catatan' => null,
                'foto' => $this->susunFoto(
                    $fotoSemua->filter(fn (WorkReportPhoto $f): bool => (int) $f->order_item_id === (int) $item->id),
                    FotoLaporanSlot::untuk($item->kategori),
                    $caption,
                ),
            ];
        }

        if ($hasil === []) {
            $hasil[] = ['nomor' => null, 'caption' => mb_strtoupper((string) $cabang), 'catatan' => null, 'foto' => $this->susunFoto(collect(), [], (string) $cabang)];
        }

        // Laporan format lama menyimpan satu foto sebelum/sesudah langsung di work_reports.
        if ($fotoSemua->isEmpty()) {
            $lama = [];
            foreach ($order->workReports as $laporan) {
                foreach (['foto_sebelum' => 'Sebelum', 'foto_sesudah' => 'Sesudah'] as $kolom => $label) {
                    if (filled($laporan->{$kolom})) {
                        $lama[] = [$label, (string) $laporan->{$kolom}];
                    }
                }
            }

            if ($lama !== []) {
                $hasil[0]['foto'] = collect($lama)
                    ->map(fn (array $p): array => $this->fotoDari($p[1], $p[0], $hasil[0]['caption']))
                    ->all();
            }
        }

        return $hasil;
    }

    /**
     * @param  Collection<int, WorkReportPhoto>  $foto
     * @param  array<string, string>  $label  slot => label
     * @return array<int, array<string, mixed>>
     */
    private function susunFoto(Collection $foto, array $label, string $caption): array
    {
        if ($foto->isEmpty()) {
            return [$this->placeholder(null, $caption)];
        }

        return $foto->sortBy('urutan')->values()
            ->map(fn (WorkReportPhoto $f): array => $this->fotoDari((string) $f->path, $label[$f->slot] ?? str($f->slot)->headline()->toString(), $caption))
            ->all();
    }

    /**
     * @return array{label: ?string, caption: string, ada: bool, file: ?string, lebar: ?int, tinggi: ?int}
     */
    private function fotoDari(string $path, ?string $label, string $caption): array
    {
        $kecil = $this->kompres->kecilkan($path);

        if ($kecil === null) {
            return $this->placeholder($label, $caption);
        }

        return ['label' => $label, 'caption' => $caption, 'ada' => true, 'file' => $kecil['path'], 'lebar' => $kecil['lebar'], 'tinggi' => $kecil['tinggi']];
    }

    /**
     * @return array{label: ?string, caption: string, ada: bool, file: ?string, lebar: ?int, tinggi: ?int}
     */
    private function placeholder(?string $label, string $caption): array
    {
        return ['label' => $label, 'caption' => $caption, 'ada' => false, 'file' => null, 'lebar' => null, 'tinggi' => null];
    }

    /**
     * "Cuci AC 3 Unit, Ganti Kapasitor 1 Unit" — diturunkan dari baris layanan
     * aktif (tidak mengubah Order::ringkasanLayanan()).
     */
    private function keterangan(Order $order): string
    {
        $aktif = $order->orderItems->reject(fn (OrderItem $i): bool => $i->dibatalkan() || $i->penyesuaian);

        if ($aktif->isEmpty()) {
            return trim(($order->serviceCatalog !== null ? str($order->serviceCatalog->jenis_layanan->value)->headline()->toString() : 'Layanan')
                .' '.max(1, (int) $order->jumlah_unit).' Unit');
        }

        return $aktif
            ->groupBy(fn (OrderItem $i): string => $this->namaLayanan($i))
            ->map(fn (Collection $g, string $nama): string => $nama.' '.max(1, (int) $g->sum('jumlah')).' Unit')
            ->implode(', ');
    }

    private function namaLayanan(OrderItem $item): string
    {
        $nama = (string) $item->nama_layanan;

        return str_contains($nama, '_') ? str($nama)->headline()->toString() : ($nama !== '' ? $nama : 'Layanan');
    }

    private function terbilang(int $n): string
    {
        return mb_strtoupper(self::ANGKA[$n] ?? (string) $n);
    }

    /**
     * Angka untuk caption: koma desimal, tanpa nol di belakang; null bila kosong/nol.
     */
    private function angka(mixed $nilai): ?string
    {
        if ($nilai === null || $nilai === '' || (float) $nilai == 0.0) {
            return null;
        }

        return rtrim(rtrim(number_format((float) $nilai, 1, ',', ''), '0'), ',');
    }

    private function teks(?string $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }
}
