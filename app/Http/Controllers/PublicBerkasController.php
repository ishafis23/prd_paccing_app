<?php

namespace App\Http\Controllers;

use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Endpoint PUBLIK untuk mengunduh snapshot PDF lampiran WA (dev-plan/23 §5.4).
 * Server gateway mengunduhnya sendiri saat kirim dokumen — karena itu tanpa
 * login, keamanannya dari tanda tangan HMAC + kedaluwarsa + whitelist folder
 * (konsep arm_app `app/pages/surat/file.php`, tapi atas path snapshot).
 */
class PublicBerkasController extends Controller
{
    public function tampil(Request $request): BinaryFileResponse
    {
        abort_if(config('wa.pdf_secret') === '' || config('wa.pdf_secret') === null, 500, 'WA_PDF_SECRET belum dikonfigurasi.');

        $file = (string) $request->query('file', '');
        $name = (string) $request->query('name', 'dokumen.pdf');
        $exp = (int) $request->query('exp', 0);
        $sig = (string) $request->query('sig', '');

        // Hanya file di folder snapshot, tanpa path traversal.
        $valid = str_starts_with($file, 'wa-dokumen/')
            && ! str_contains($file, '..')
            && $file === preg_replace('/[^A-Za-z0-9_\-\/. ]/', '', $file);

        abort_unless($valid, 400, 'Nama file tidak valid.');

        abort_unless(
            app(WhatsappService::class)->tandaTanganSah($file, $name, $exp, $sig),
            403,
            'Tanda tangan tidak valid atau sudah kedaluwarsa.'
        );

        abort_unless(Storage::disk('local')->exists($file), 404, 'File tidak ditemukan.');

        $path = Storage::disk('local')->path($file);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($name).'"',
        ]);
    }
}
