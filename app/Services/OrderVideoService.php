<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderVideo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Dokumentasi video pengerjaan (revisi 14 Nov 2026 poin 13): teknisi anggota order boleh
 * menambah video (mp4/mov/webm/3gp), dari galeri atau rekam langsung. Satu aturan untuk
 * API mobile dan portal web.
 */
class OrderVideoService
{
    public const FOLDER = 'order-videos';

    /** ekstensi => mime yang diterima */
    public const FORMAT = [
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime'],
        'webm' => ['video/webm'],
        '3gp' => ['video/3gpp', 'video/3gpp2'],
    ];

    public function __construct(private readonly StorageQuotaService $quota) {}

    public static function ekstensiDiterima(): string
    {
        return implode(',', array_keys(self::FORMAT));
    }

    public function tambah(Order $order, User $teknisi, UploadedFile $file, ?string $keterangan = null): OrderVideo
    {
        if (! $order->diassignkanKe($teknisi)) {
            throw new BusinessRuleException('Order ini bukan tugas teknisi Anda.');
        }

        if ($order->status === OrderStatus::Batal) {
            throw new BusinessRuleException('Order batal tidak bisa diberi dokumentasi.');
        }

        $this->validasiFile($file);

        if ($order->videos()->count() >= (int) config('video.maks_per_order')) {
            throw new BusinessRuleException(sprintf('Maksimal %d video per order.', (int) config('video.maks_per_order')));
        }

        $this->quota->pastikanCukup((int) $file->getSize());

        $path = $file->store(self::FOLDER, 'public');
        StorageQuotaService::lupakanCache();

        return OrderVideo::create([
            'order_id' => $order->id,
            'user_id' => $teknisi->id,
            'path' => $path,
            'nama_asli' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $file->getMimeType(),
            'ukuran' => (int) $file->getSize(),
            'keterangan' => filled($keterangan) ? mb_substr(trim($keterangan), 0, 255) : null,
        ]);
    }

    /** Hanya pengunggah, dan order belum ditutup. */
    public function hapus(OrderVideo $video, User $teknisi): void
    {
        if ((int) $video->user_id !== (int) $teknisi->id) {
            throw new BusinessRuleException('Hanya pengunggah yang bisa menghapus video ini.');
        }

        if ($video->order?->sudahDitutup()) {
            throw new BusinessRuleException('Order sudah ditutup; video tidak bisa dihapus.');
        }

        $path = $video->path;
        $video->delete();
        Storage::disk('public')->delete($path);
        StorageQuotaService::lupakanCache();
    }

    private function validasiFile(UploadedFile $file): void
    {
        if (! $file->isValid()) {
            throw new BusinessRuleException('Unggahan video gagal (file rusak atau melebihi batas server).');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if (! isset(self::FORMAT[$ext]) || ! in_array($mime, self::FORMAT[$ext], true)) {
            throw new BusinessRuleException('Format video tidak didukung. Gunakan '.strtoupper(self::ekstensiDiterima()).'.');
        }

        $maks = (int) config('video.maks_mb') * 1024 * 1024;
        if ((int) $file->getSize() > $maks) {
            throw new BusinessRuleException(sprintf('Ukuran video maksimal %d MB.', (int) config('video.maks_mb')));
        }
    }
}
