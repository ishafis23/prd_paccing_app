<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\PaymentChannel;
use App\Models\WorkReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Daftar & kelola file di folder penyimpanan terkelola (B27–B30):
 * `work-reports/` (foto laporan) dan `payment-channels/` (gambar QRIS).
 *
 * Daftar dibangun dengan SCAN folder real-time (tanpa tabel DB); referensi
 * tiap file dicocokkan dari kolom DB supaya label & status akurat, termasuk
 * file yatim (tanpa referensi) yang ikut dihitung kuota.
 */
class FilePenyimpananService
{
    public const FOLDER_QRIS = 'payment-channels';

    public function __construct(private readonly StorageQuotaService $quota)
    {
    }

    /**
     * Folder terkelola (selalu relatif thd disk penyimpanan).
     *
     * @return array<int, string>
     */
    public function folderTerkelola(): array
    {
        return [
            (string) config('penyimpanan.folder_foto', 'work-reports'),
            self::FOLDER_QRIS,
        ];
    }

    /**
     * Seluruh file di folder terkelola beserta metadata & referensinya,
     * diurutkan termuda dulu.
     *
     * @return Collection<int, array{
     *     path: string,
     *     nama: string,
     *     folder: string,
     *     ukuran: int,
     *     mtime: ?Carbon,
     *     status: 'foto_sebelum'|'foto_sesudah'|'qris'|'yatim',
     *     label: string,
     *     ref_id: ?int
     * }>
     */
    public function daftarFile(): Collection
    {
        $disk = $this->quota->disk();
        $referensi = $this->petaReferensi();
        $baris = collect();

        foreach ($this->folderTerkelola() as $folder) {
            if (! $disk->exists($folder)) {
                continue;
            }

            foreach ($disk->allFiles($folder) as $path) {
                $ref = $referensi[$path] ?? null;

                $baris->push([
                    'path' => $path,
                    'nama' => basename($path),
                    'folder' => $folder,
                    'ukuran' => (int) $disk->size($path),
                    'mtime' => $this->waktuModifikasi($disk, $path),
                    'status' => $ref['status'] ?? 'yatim',
                    'label' => $ref['label'] ?? 'Tanpa referensi',
                    'ref_id' => $ref['ref_id'] ?? null,
                ]);
            }
        }

        return $baris->sortByDesc(fn (array $b): ?Carbon => $b['mtime'])->values();
    }

    /**
     * Hapus file fisik + kosongkan kolom DB yang merujuknya (B30a).
     * Aman dipanggil berulang (idempotent); file di luar folder terkelola
     * ditolak. Riwayat order/laporan TIDAK dihapus.
     *
     * @throws BusinessRuleException bila path di luar folder terkelola
     */
    public function hapusFile(string $path): void
    {
        $folder = $this->folderDariPath($path);

        if ($folder === null || ! in_array($folder, $this->folderTerkelola(), true)) {
            throw new BusinessRuleException('File berada di luar folder penyimpanan yang dikelola.');
        }

        // 1) Kosongkan kolom DB yang merujuk path ini (bisa 0–2 baris).
        WorkReport::query()
            ->where(fn ($q) => $q->where('foto_sebelum', $path)->orWhere('foto_sesudah', $path))
            ->get()
            ->each(function (WorkReport $report) use ($path): void {
                foreach (['foto_sebelum', 'foto_sesudah'] as $kolom) {
                    if ($report->{$kolom} === $path) {
                        $report->{$kolom} = null;
                    }
                }
                $report->save();
            });

        PaymentChannel::query()
            ->where('gambar', $path)
            ->get()
            ->each(function (PaymentChannel $channel): void {
                $channel->gambar = null;
                $channel->save();
            });

        // 2) Buang file fisik bila masih ada.
        $disk = $this->quota->disk();
        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        StorageQuotaService::lupakanCache();
    }

    public function folderDariPath(string $path): ?string
    {
        $folder = dirname($path);

        return $folder === '.' ? null : $folder;
    }

    /**
     * Peta path file -> referensi DB.
     *
     * @return array<string, array{status: string, label: string, ref_id: int}>
     */
    private function petaReferensi(): array
    {
        $peta = [];

        WorkReport::query()
            ->with(['order.customer:id,nama'])
            ->where(fn ($q) => $q->whereNotNull('foto_sebelum')->orWhereNotNull('foto_sesudah'))
            ->get()
            ->each(function (WorkReport $report) use (&$peta): void {
                $order = $report->order;
                $konteks = sprintf(
                    '#%d%s%s',
                    $order?->id ?? $report->order_id,
                    $order?->customer?->nama ? ' · '.$order->customer->nama : '',
                    $report->created_at ? ' · '.$report->created_at->format('d/m/Y') : ''
                );

                foreach ([
                    'foto_sebelum' => 'Foto sebelum',
                    'foto_sesudah' => 'Foto sesudah',
                ] as $kolom => $jenis) {
                    $path = $report->{$kolom};
                    if ($path !== null) {
                        $peta[$path] = [
                            'status' => $kolom,
                            'label' => $jenis.' — Order '.$konteks,
                            'ref_id' => $report->id,
                        ];
                    }
                }
            });

        PaymentChannel::query()
            ->whereNotNull('gambar')
            ->get()
            ->each(function (PaymentChannel $channel) use (&$peta): void {
                $peta[$channel->gambar] = [
                    'status' => 'qris',
                    'label' => 'QRIS — '.($channel->nama ?: 'channel #'.$channel->id),
                    'ref_id' => $channel->id,
                ];
            });

        return $peta;
    }

    private function waktuModifikasi($disk, string $path): ?Carbon
    {
        try {
            $waktu = $disk->lastModified($path);

            return $waktu === false ? null : Carbon::createFromTimestamp($waktu);
        } catch (\Throwable) {
            return null;
        }
    }
}
