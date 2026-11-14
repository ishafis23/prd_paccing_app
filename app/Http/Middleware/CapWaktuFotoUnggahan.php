<?php

namespace App\Http\Middleware;

use App\Services\FotoTimestampService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cap tanggal & jam server pada setiap foto yang diunggah teknisi (revisi 14 Nov 2026 poin 3).
 * Dikecualikan: foto profil (bukan dokumentasi) dan pengeluaran (dicap oleh FotoBuktiService
 * SETELAH hash kembar dihitung, supaya deteksi foto kembar tidak terganggu oleh cap).
 */
class CapWaktuFotoUnggahan
{
    private const DIKECUALIKAN = ['api/v1/akun/foto-profil', 'api/v1/pengeluaran', 'api/v1/pengeluaran/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (FotoTimestampService::aktif() && $request->isMethod('POST') && ! $request->is(self::DIKECUALIKAN)) {
            $service = app(FotoTimestampService::class);

            foreach ($this->semuaFile($request->allFiles()) as $file) {
                if ($file->isValid() && str_starts_with((string) $file->getMimeType(), 'image/')) {
                    $service->tempelFile($file->getRealPath());
                }
            }
        }

        return $next($request);
    }

    /** @return iterable<UploadedFile> */
    private function semuaFile(array $files): iterable
    {
        foreach ($files as $f) {
            if ($f instanceof UploadedFile) {
                yield $f;
            } elseif (is_array($f)) {
                yield from $this->semuaFile($f);
            }
        }
    }
}
