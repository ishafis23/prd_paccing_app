<?php

namespace App\Services;

use App\Models\WaTemplate;

/**
 * Penyusun & pembaca template pesan WA yang dikelola admin (dev-plan/27 fase
 * 27-A). Sumber teks:
 *
 *  1. baris `wa_templates` yang AKTIF (hasil editan admin), atau
 *  2. default bawaan (const DEFAULTS) bila baris belum/pernah dihapus.
 *
 * Bila admin MENONAKTIFKAN baris (aktif=false), `teks()` mengembalikan null
 * sehingga pemakaian OTOMATIS dilewati (mis. notifikasi berangkat).
 */
class WaTemplateService
{
    /** Default notifikasi "teknisi berangkat" (dipakai bila baris belum ada). */
    public const DEFAULT_BERANGKAT = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n"
        ."Bapak/Ibu {nama}\n\n"
        .'Teknisi kami ({teknisi}) sedang menuju lokasi Anda untuk mengerjakan {layanan}.'
        ." Mohon pastikan lokasi dapat diakses saat teknisi tiba.\n\n"
        ."Terima kasih atas kepercayaan Anda.\n\n"
        ."Hormat kami,\n{nama_usaha}";

    /** Default notifikasi "pembayaran diterima + invoice". */
    public const DEFAULT_PEMBAYARAN_LUNAS = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n"
        ."Bapak/Ibu {nama}\n\n"
        .'Terima kasih, pembayaran invoice {nomor_invoice} sebesar {total} telah kami terima.'
        ." Invoice terlampir pada pesan ini.\n\n"
        ."{tautan_invoice}\n\n"
        ."Terima kasih atas kepercayaan Anda.\n\n"
        ."Hormat kami,\n{nama_usaha}";

    /** Katalog default: kode => [nama, isi] — dipakai seeder & fallback. */
    public const DEFAULTS = [
        'berangkat' => [
            'nama' => 'Notifikasi Teknisi Berangkat',
            'isi' => self::DEFAULT_BERANGKAT,
        ],
        'pembayaran_lunas' => [
            'nama' => 'Notifikasi Pembayaran + Invoice',
            'isi' => self::DEFAULT_PEMBAYARAN_LUNAS,
        ],
    ];

    /** Placeholder yang dikenali (untuk helper text di halaman admin). */
    public const PLACEHOLDER = [
        'nama',
        'nama_usaha',
        'layanan',
        'alamat',
        'teknisi',
        'jadwal',
        'nomor_invoice',
        'total',
        'tautan_invoice',
    ];

    public function placeholderList(): string
    {
        return collect(self::PLACEHOLDER)->map(fn (string $p): string => '{'.$p.'}')->implode(', ');
    }

    /**
     * Teks final untuk `kode` dengan placeholder terisi dari `$data`.
     * - Baris ada & aktif  → isi baris.
     * - Baris ada & nonaktif → null (pemakaian otomatis dilewati).
     * - Baris tidak ada     → default bawaan `DEFAULTS`.
     * - Kode tak dikenal    → null.
     */
    public function teks(string $kode, array $data = []): ?string
    {
        $template = WaTemplate::query()->where('kode', $kode)->first();

        if ($template !== null && ! $template->aktif) {
            return null;
        }

        $isi = $template?->isi ?? (self::DEFAULTS[$kode]['isi'] ?? null);

        return $isi === null ? null : $this->render($isi, $data);
    }

    /**
     * Ganti placeholder {kunci} dari `$data`, lalu BUANG placeholder yang
     * belum terisi agar token mentah tidak bocor ke customer.
     */
    public function render(string $isi, array $data = []): string
    {
        $ganti = [];
        foreach ($data as $kunci => $nilai) {
            $ganti['{'.$kunci.'}'] = (string) ($nilai ?? '');
        }

        $teks = strtr($isi, $ganti);

        return trim((string) preg_replace('/\{[a-zA-Z0-9_]+\}/', '', $teks));
    }
}
