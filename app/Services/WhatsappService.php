<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Support\Url;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Klien gateway WhatsApp (dev-plan/23) — port dari arm_app
 * `vendor/wablasted/WablastedService.php`.
 *
 * Fase 23-A: get-qr, pre-flight status, reset session.
 * Fase 23-B/C: send-teks, send-dokumen, kirim ulang (menyusul).
 *
 * Aturan: `connection_status` device TIDAK dipercaya dari kolom saja —
 * sumber kebenaran = hasil get-qr ("ok & tanpa gambar QR" = terhubung),
 * lalu kolom disinkronkan (konsep arm_app proxy.php:244-278).
 */
class WhatsappService
{
    /** Apakah fitur kirim diaktifkan (kill-switch). */
    public function aktif(): bool
    {
        return (bool) config('wa.aktif', false);
    }

    /**
     * Ambil QR / status koneksi device.
     *
     * @return array{ok:bool,status_code:int,body:mixed,parsed:mixed,error:?string,message:?string,has_qr:bool,image:?string}
     */
    public function qr(WaDevice $device): array
    {
        $base = $this->baseUrl($device, 'qr');

        if ($base === null) {
            return $this->gagal('Base URL QR belum dikonfigurasi (WA_BASE_URL_QR atau kolom ProviderAccount).');
        }

        return $this->normalisasiQr($this->call($base.'/api/qr/', $this->params($device)));
    }

    /**
     * Pre-flight: panggil get-qr, tandai terhubung bila "ok & tanpa QR image",
     * lalu sinkronkan kolom device. Kembalikan array hasil qr() + `terhubung`.
     *
     * @return array<string, mixed>
     */
    public function status(WaDevice $device): array
    {
        $hasil = $this->qr($device);
        $terhubung = ! empty($hasil['ok']) && empty($hasil['has_qr']);

        $device->forceFill([
            'connection_status' => $terhubung ? WaDevice::KONEKSI_TERHUBUNG : WaDevice::KONEKSI_BELUM,
            'last_checked_at' => now(),
        ])->save();

        $hasil['terhubung'] = $terhubung;

        return $hasil;
    }

    /**
     * Reset/logout sesi WA device (scan ulang saat QR basi). Endpoint bridge
     * apiwablasted (api.php) — body {action:reset_session, device}, Bearer
     * document_token. Sumber kebenaran tetap get-qr sesudahnya.
     *
     * @return array{ok:bool,status_code:int,body:mixed,parsed:mixed,error:?string,message:?string}
     */
    public function resetSession(WaDevice $device): array
    {
        $url = $this->resetUrl($device);
        if ($url === null) {
            return $this->gagal('Endpoint reset belum dikonfigurasi (WA_BASE_URL_RESET / WA_BASE_URL_DOCUMENT).');
        }

        $headers = [];
        $token = trim((string) config('wa.document_token'));
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $resp = Http::withOptions($this->opsiHttp())
                ->withHeaders($headers)
                ->asJson()
                ->post($url, [
                    'device' => $device->device_name,
                    'action' => 'reset_session',
                ]);
        } catch (\Throwable $e) {
            return $this->gagal('Reset gagal: '.$e->getMessage());
        }

        $parsed = $resp->json();
        $body = (string) $resp->body();

        return [
            'ok' => $resp->successful(),
            'status_code' => $resp->status(),
            'body' => $body,
            'parsed' => $parsed ?? $body,
            'error' => null,
            'message' => is_array($parsed) ? ($parsed['message'] ?? null) : null,
        ];
    }

    /**
     * Ambil daftar grup WA yang diikuti nomor device (menu "Cek ID Group
     * WA"). Endpoint JSON + Bearer terpisah (WA_BASE_URL_GROUPS /
     * WA_GROUPS_TOKEN) — bukan bagian dari pre-flight/kirim, jadi tidak
     * lewat pastikanBolehKirim()/wa_logs.
     *
     * @return array{ok:bool,groups:array<int,array{id:string,subject:string}>,error:?string}
     */
    public function daftarGroup(WaDevice $device): array
    {
        $url = $this->baseUrl($device, 'groups');
        if ($url === null) {
            return ['ok' => false, 'groups' => [], 'error' => 'Base URL grup belum dikonfigurasi (WA_BASE_URL_GROUPS).'];
        }

        $headers = [];
        $token = trim((string) config('wa.groups_token'));
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $resp = Http::withOptions($this->opsiHttp())
                ->withHeaders($headers)
                ->asJson()
                ->post($url, $this->params($device));
        } catch (\Throwable $e) {
            return ['ok' => false, 'groups' => [], 'error' => 'Gagal mengambil daftar grup: '.$e->getMessage()];
        }

        if (! $resp->successful()) {
            return ['ok' => false, 'groups' => [], 'error' => 'Gateway membalas HTTP '.$resp->status().'.'];
        }

        $list = data_get($resp->json(), 'data.groups', []);
        $groups = collect(is_array($list) ? $list : [])
            ->map(fn ($g): array => ['id' => (string) ($g['id'] ?? ''), 'subject' => (string) ($g['subject'] ?? '')])
            ->filter(fn (array $g): bool => $g['id'] !== '')
            ->values()
            ->all();

        return ['ok' => true, 'groups' => $groups, 'error' => null];
    }

    // ---------------------------------------------------------------
    // Kirim (fase 23-B/23-C) — setiap kirim WAJIB lewat salah satu method
    // orkestrasi ini agar pre-flight, rate limit, dan wa_logs konsisten.
    // ---------------------------------------------------------------

    /**
     * Kirim satu pesan teks. Menyimpan `pesan` final ke wa_logs sehingga bisa
     * dikirim ulang tanpa menyusun teks lagi.
     */
    public function kirimTeks(
        User $user,
        WaDevice $device,
        string $target,
        string $pesan,
        ?Model $related = null,
        string $jenisKirim = 'teks',
        int $percobaan = 1,
        ?int $logAsalId = null
    ): WaLog {
        $this->pastikanBolehKirim($user, $device);
        $target = $this->targetValid($target);

        $resp = $this->sendText($device, $target, $pesan);

        return $this->catat($user, $device, [
            'action' => 'send-teks',
            'target' => $target,
            'jenis_kirim' => $jenisKirim,
            'pesan' => $pesan,
            'related' => $related,
            'percobaan' => $percobaan,
            'log_asal_id' => $logAsalId,
            'request' => ['target' => $target, 'jenis' => 'teks'],
        ], $resp);
    }

    /**
     * Kirim satu pesan teks ke GRUP WhatsApp. Berbeda dari kirimTeks(),
     * target TIDAK dinormalisasi sebagai nomor — JID grup (mis.
     * `120363023284398181@g.us`) dipakai apa adanya karena normalisasi nomor
     * akan merusaknya. Pre-flight + rate limit + wa_logs tetap sama.
     */
    public function kirimGrup(
        User $user,
        WaDevice $device,
        string $grup,
        string $pesan,
        ?Model $related = null,
        string $jenisKirim = 'grup',
        int $percobaan = 1,
        ?int $logAsalId = null
    ): WaLog {
        $this->pastikanBolehKirim($user, $device);
        $grup = $this->targetGrupValid($grup);

        $resp = $this->sendGrup($device, $grup, $pesan);

        return $this->catat($user, $device, [
            'action' => 'send-grup',
            'target' => $grup,
            'jenis_kirim' => $jenisKirim,
            'pesan' => $pesan,
            'related' => $related,
            'percobaan' => $percobaan,
            'log_asal_id' => $logAsalId,
            'request' => ['target' => $grup, 'jenis' => 'teks', 'tujuan' => 'grup'],
        ], $resp);
    }

    /**
     * Kirim pesan teks atas nama SISTEM (notifikasi otomatis ke customer —
     * dev-plan/27). Beda dari kirimTeks(): tidak memakai rate limit per-user,
     * melainkan rate limit global (config `wa.rate_limit_sistem`), dan
     * `wa_logs.user_id` boleh null (dari pemilik device bila ada).
     */
    public function kirimTeksSistem(
        WaDevice $device,
        string $target,
        string $pesan,
        ?Model $related = null,
        string $jenisKirim = 'notif',
        ?User $user = null,
    ): WaLog {
        if (! $this->aktif()) {
            throw new BusinessRuleException('Fitur kirim WA sedang nonaktif (WA_AKTIF=false).');
        }

        $status = $this->status($device);
        if (empty($status['terhubung'])) {
            throw new BusinessRuleException('Device WA belum terhubung (dicek real-time).');
        }

        $limit = (int) config('wa.rate_limit_sistem', 0);
        if ($limit > 0) {
            $n = WaLog::query()
                ->where('action', 'like', 'send%')
                ->where('created_at', '>=', now()->subMinute())
                ->count();

            if ($n >= $limit) {
                throw new BusinessRuleException('Rate limit notifikasi sistem tercapai. Coba lagi sebentar.');
            }
        }

        $target = $this->targetValid($target);

        $resp = $this->sendText($device, $target, $pesan);

        return $this->catat($user, $device, [
            'action' => 'send-teks',
            'target' => $target,
            'jenis_kirim' => $jenisKirim,
            'pesan' => $pesan,
            'related' => $related,
            'request' => ['target' => $target, 'jenis' => 'teks', 'sistem' => true],
        ], $resp);
    }

    /**
     * Kirim satu dokumen (PDF snapshot) lewat bridge JSON + URL publik
     * bertanda tangan. `dokumen_path` disimpan ke log → kirim ulang tidak
     * render ulang.
     */
    public function kirimDokumen(
        User $user,
        WaDevice $device,
        string $target,
        string $dokumenPath,
        string $caption,
        string $namafile,
        ?Model $related = null,
        string $jenisKirim = 'invoice',
        int $percobaan = 1,
        ?int $logAsalId = null
    ): WaLog {
        $this->pastikanBolehKirim($user, $device);
        $target = $this->targetValid($target);

        $dokumenUrl = $this->urlPublik($dokumenPath, $namafile);
        $resp = $this->sendDocument($device, $target, $dokumenUrl, $caption, $namafile);

        return $this->catat($user, $device, [
            'action' => 'send-document',
            'target' => $target,
            'jenis_kirim' => $jenisKirim,
            'pesan' => $caption,
            'dokumen_path' => $dokumenPath,
            'dokumen_namafile' => $namafile,
            'dokumen_url' => $dokumenUrl,
            'related' => $related,
            'percobaan' => $percobaan,
            'log_asal_id' => $logAsalId,
            'request' => ['target' => $target, 'jenis' => 'document', 'namafile' => $namafile],
        ], $resp);
    }

    /**
     * Kirim satu gambar (JPG/PNG) + caption ke NOMOR pribadi. Pola diadopsi
     * dari proyek `pmb-onegate-bersih` (gateway WABlasted yang sama): satu
     * endpoint kirim, field `jenis=gambar` + `gambar=<url>` (dibangun lewat
     * urlPublik(), sama seperti dokumen). `gambar_path` disimpan ke log →
     * kirim ulang tidak perlu upload ulang.
     */
    public function kirimGambar(
        User $user,
        WaDevice $device,
        string $target,
        string $gambarPath,
        string $caption,
        string $namafile,
        ?Model $related = null,
        string $jenisKirim = 'gambar',
        int $percobaan = 1,
        ?int $logAsalId = null
    ): WaLog {
        $this->pastikanBolehKirim($user, $device);
        $target = $this->targetValid($target);

        return $this->kirimGambarTervalidasi(
            $user, $device, $target, $gambarPath, $caption, $namafile,
            $related, $jenisKirim, $percobaan, $logAsalId, 'send-gambar',
        );
    }

    /** Kirim satu gambar + caption ke GRUP WhatsApp (JID apa adanya). */
    public function kirimGambarGrup(
        User $user,
        WaDevice $device,
        string $grup,
        string $gambarPath,
        string $caption,
        string $namafile,
        ?Model $related = null,
        string $jenisKirim = 'gambar-grup',
        int $percobaan = 1,
        ?int $logAsalId = null
    ): WaLog {
        $this->pastikanBolehKirim($user, $device);
        $grup = $this->targetGrupValid($grup);

        return $this->kirimGambarTervalidasi(
            $user, $device, $grup, $gambarPath, $caption, $namafile,
            $related, $jenisKirim, $percobaan, $logAsalId, 'send-gambar-grup',
        );
    }

    /** Inti kirim gambar setelah target divalidasi (dipakai kirimGambar & kirimGambarGrup). */
    private function kirimGambarTervalidasi(
        ?User $user,
        WaDevice $device,
        string $target,
        string $gambarPath,
        string $caption,
        string $namafile,
        ?Model $related,
        string $jenisKirim,
        int $percobaan,
        ?int $logAsalId,
        string $action
    ): WaLog {
        $gambarUrl = $this->urlPublik($gambarPath, $namafile);
        $resp = $this->sendImage($device, $target, $gambarUrl, $caption);

        return $this->catat($user, $device, [
            'action' => $action,
            'target' => $target,
            'jenis_kirim' => $jenisKirim,
            'pesan' => $caption,
            'dokumen_path' => $gambarPath,
            'dokumen_namafile' => $namafile,
            'dokumen_url' => $gambarUrl,
            'related' => $related,
            'percobaan' => $percobaan,
            'log_asal_id' => $logAsalId,
            'request' => ['target' => $target, 'jenis' => 'gambar', 'namafile' => $namafile],
        ], $resp);
    }

    /**
     * Kirim ulang baris wa_logs memakai payload tersimpan — TANPA menyusun
     * pesan / render PDF lagi. Membuat baris log BARU (log lama tetap) dengan
     * `log_asal_id` + `percobaan` naik.
     */
    public function kirimUlang(WaLog $log, ?string $target = null): WaLog
    {
        $user = $log->user;
        $device = $log->device;

        if ($user === null) {
            throw new BusinessRuleException('Log kirim tidak punya pengguna.');
        }
        if ($device === null) {
            throw new BusinessRuleException('Device pengirim log ini sudah tidak ada.');
        }
        if (! filled($log->pesan)) {
            throw new BusinessRuleException('Log ini tidak menyimpan isi pesan — tidak bisa dikirim ulang.');
        }

        $target = $target !== null && trim($target) !== '' ? $target : $log->target;

        if ($log->action === 'send-grup') {
            return $this->kirimGrup(
                $user,
                $device,
                (string) $target,
                (string) $log->pesan,
                $log->related,
                (string) ($log->jenis_kirim ?: 'grup'),
                $log->percobaan + 1,
                $log->id,
            );
        }

        if ($log->action === 'send-document' && filled($log->dokumen_path)) {
            if (! Storage::disk('local')->exists($log->dokumen_path)) {
                throw new BusinessRuleException('File dokumen snapshot sudah tidak ada — buat & kirim ulang dari sumbernya.');
            }

            return $this->kirimDokumen(
                $user,
                $device,
                $target,
                $log->dokumen_path,
                (string) $log->pesan,
                (string) ($log->dokumen_namafile ?: basename($log->dokumen_path)),
                $log->related,
                (string) ($log->jenis_kirim ?: 'dokumen'),
                $log->percobaan + 1,
                $log->id,
            );
        }

        if (in_array($log->action, ['send-gambar', 'send-gambar-grup'], true) && filled($log->dokumen_path)) {
            if (! Storage::disk('local')->exists($log->dokumen_path)) {
                throw new BusinessRuleException('File gambar sudah tidak ada — upload & kirim ulang dari sumbernya.');
            }

            $namafile = (string) ($log->dokumen_namafile ?: basename($log->dokumen_path));

            return $log->action === 'send-gambar-grup'
                ? $this->kirimGambarGrup(
                    $user, $device, (string) $target, $log->dokumen_path, (string) $log->pesan, $namafile,
                    $log->related, (string) ($log->jenis_kirim ?: 'gambar-grup'), $log->percobaan + 1, $log->id,
                )
                : $this->kirimGambar(
                    $user, $device, (string) $target, $log->dokumen_path, (string) $log->pesan, $namafile,
                    $log->related, (string) ($log->jenis_kirim ?: 'gambar'), $log->percobaan + 1, $log->id,
                );
        }

        return $this->kirimTeks(
            $user,
            $device,
            $target,
            (string) $log->pesan,
            $log->related,
            (string) ($log->jenis_kirim ?: 'teks'),
            $log->percobaan + 1,
            $log->id,
        );
    }

    /** Simpan binary PDF sebagai snapshot; kembalikan path relatif disk local. */
    public function simpanSnapshot(string $binary, string $namafile): string
    {
        $ext = pathinfo($namafile, PATHINFO_EXTENSION) ?: 'pdf';
        $file = 'wa-dokumen/'.date('Y/m').'/'.Str::random(32).'.'.$ext;

        Storage::disk('local')->put($file, $binary);

        return $file;
    }

    /** Simpan binary gambar (JPG/PNG) lampiran WA; kembalikan path relatif disk local. */
    public function simpanGambar(string $binary, string $namafile): string
    {
        $ext = pathinfo($namafile, PATHINFO_EXTENSION) ?: 'jpg';
        $file = 'wa-gambar/'.date('Y/m').'/'.Str::random(32).'.'.$ext;

        Storage::disk('local')->put($file, $binary);

        return $file;
    }

    /** URL publik bertanda tangan HMAC + kedaluwarsa untuk file snapshot. */
    public function urlPublik(string $path, string $namafile, ?int $berlakuJam = 24): string
    {
        $exp = now()->addHours($berlakuJam ?? 24)->timestamp;
        $sig = hash_hmac('sha256', $path.'|'.$namafile.'|'.$exp, (string) config('wa.pdf_secret'));

        return Url::absolute('berkas.publik', [
            'file' => $path,
            'name' => $namafile,
            'exp' => $exp,
            'sig' => $sig,
        ]);
    }

    /** Verifikasi tanda tangan URL publik (dipakai PublicBerkasController). */
    public function tandaTanganSah(string $path, string $namafile, int $exp, string $sig): bool
    {
        $secret = (string) config('wa.pdf_secret');
        if ($secret === '' || $exp < now()->timestamp) {
            return false;
        }

        $expected = hash_hmac('sha256', $path.'|'.$namafile.'|'.$exp, $secret);

        return hash_equals($expected, $sig);
    }

    // ---------------------------------------------------------------
    // Panggilan HTTP tingkat rendah
    // ---------------------------------------------------------------

    /**
     * POST form ke endpoint send induk.
     *
     * @return array{ok:bool,status_code:int,body:string,parsed:mixed,error:?string}
     */
    public function sendText(WaDevice $device, string $target, string $pesan): array
    {
        $base = $this->baseUrl($device, 'send');
        if ($base === null) {
            return $this->gagalHttp('Base URL kirim belum dikonfigurasi (WA_BASE_URL_SEND).');
        }

        return $this->call($base.'/api/send/', $this->params($device) + [
            'jenis' => 'teks',
            'pesan' => $pesan,
            'target' => $target,
        ]);
    }

    /**
     * POST form ke endpoint kirim untuk GRUP. Memakai base URL grup bila
     * dikonfigurasi (kolom ProviderAccount `base_url_group` / WA_BASE_URL_GROUP),
     * jika tidak ada fallback ke base URL kirim. `target` = JID grup apa adanya.
     *
     * @return array{ok:bool,status_code:int,body:string,parsed:mixed,error:?string}
     */
    public function sendGrup(WaDevice $device, string $grup, string $pesan): array
    {
        $base = $this->baseUrl($device, 'group') ?? $this->baseUrl($device, 'send');
        if ($base === null) {
            return $this->gagalHttp('Base URL grup belum dikonfigurasi (WA_BASE_URL_GROUP / WA_BASE_URL_SEND).');
        }

        return $this->call($base.'/api/send/', $this->params($device) + [
            'jenis' => 'teks',
            'pesan' => $pesan,
            'target' => $grup,
        ]);
    }

    /**
     * POST form ke endpoint send induk — jenis=gambar + URL gambar publik
     * (field `gambar`). Pola diadopsi dari `pmb-onegate-bersih` (gateway
     * WABlasted yang sama, action `send_gambar`): satu endpoint kirim,
     * dibedakan lewat `jenis`, gambar dikirim sebagai URL — bukan base64.
     *
     * @return array{ok:bool,status_code:int,body:string,parsed:mixed,error:?string}
     */
    public function sendImage(WaDevice $device, string $target, string $gambarUrl, string $pesan): array
    {
        $base = $this->baseUrl($device, 'send');
        if ($base === null) {
            return $this->gagalHttp('Base URL kirim belum dikonfigurasi (WA_BASE_URL_SEND).');
        }

        return $this->call($base.'/api/send/', $this->params($device) + [
            'jenis' => 'gambar',
            'pesan' => $pesan,
            'target' => $target,
            'gambar' => $gambarUrl,
        ]);
    }

    /**
     * POST JSON ke bridge dokumen (api_document.php) — URL + namafile.
     *
     * @return array{ok:bool,status_code:int,body:string,parsed:mixed,error:?string}
     */
    public function sendDocument(WaDevice $device, string $target, string $dokumenUrl, string $caption, string $namafile): array
    {
        $base = $this->baseUrl($device, 'document');
        if ($base === null) {
            return $this->gagalHttp('Base URL dokumen belum dikonfigurasi (WA_BASE_URL_DOCUMENT).');
        }

        $url = str_ends_with($base, '.php') ? $base : $base.'/api/send-document/';

        $headers = [];
        $token = trim((string) config('wa.document_token'));
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $resp = Http::withOptions($this->opsiHttp())
                ->withHeaders($headers)
                ->asJson()
                ->post($url, $this->params($device) + [
                    'target' => $target,
                    'pesan' => $caption,
                    'jenis' => 'document',
                    'dokumen' => $dokumenUrl,
                    'namafile' => $namafile,
                ]);
        } catch (\Throwable $e) {
            return $this->gagalHttp('Kirim dokumen gagal: '.$e->getMessage());
        }

        $body = (string) $resp->body();

        return [
            'ok' => $resp->successful(),
            'status_code' => $resp->status(),
            'body' => $body,
            'parsed' => $resp->json() ?? $body,
            'error' => null,
        ];
    }

    /** Normalisasi nomor WA: buang non-digit, 0-awal → 62, tambah 62 bila perlu. */
    public function normalizeNumber(string $nomor): string
    {
        $s = preg_replace('/\D/', '', $nomor) ?? '';

        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, '0')) {
            $s = '62'.substr($s, 1);
        }
        if (! str_starts_with($s, '62')) {
            $s = '62'.$s;
        }

        return $s;
    }

    // ---------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------

    /** Gate kirim: fitur aktif → device terhubung (pre-flight) → rate limit. */
    private function pastikanBolehKirim(User $user, WaDevice $device): void
    {
        if (! $this->aktif()) {
            throw new BusinessRuleException('Fitur kirim WA sedang nonaktif (WA_AKTIF=false).');
        }

        $status = $this->status($device);
        if (empty($status['terhubung'])) {
            throw new BusinessRuleException('Device WA belum terhubung (dicek real-time). Scan QR dulu di menu "Perangkat & Tes WA".');
        }

        $limit = (int) config('wa.rate_limit', 0);
        if ($limit > 0) {
            $n = WaLog::query()
                ->where('user_id', $user->id)
                ->where('action', 'like', 'send%')
                ->where('created_at', '>=', now()->subMinute())
                ->count();

            if ($n >= $limit) {
                throw new BusinessRuleException('Terlalu banyak kirim. Coba lagi beberapa saat.');
            }
        }
    }

    /** Normalisasi + validasi nomor tujuan (min 10 digit). */
    private function targetValid(string $target): string
    {
        $target = $this->normalizeNumber($target);
        if (strlen($target) < 10) {
            throw new BusinessRuleException('Nomor tujuan WA tidak valid (minimal 10 digit, format 62812xxxx).');
        }

        return $target;
    }

    /**
     * Validasi target GRUP: dipakai apa adanya (TIDAK lewat normalizeNumber)
     * supaya JID `120363023284398181@g.us` tidak rusak jadi nomor.
     */
    private function targetGrupValid(string $target): string
    {
        $target = trim($target);
        if ($target === '') {
            throw new BusinessRuleException('Target grup WA tidak valid (contoh: 120363023284398181@g.us).');
        }

        return $target;
    }

    /**
     * Tulis baris wa_logs (audit + sumber kirim ulang).
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $resp
     */
    private function catat(?User $user, WaDevice $device, array $data, array $resp): WaLog
    {
        $sukses = ! empty($resp['ok']);
        $related = $data['related'] ?? null;

        return WaLog::create([
            'user_id' => $user?->id,
            'wa_device_id' => $device->id,
            'action' => $data['action'],
            'target' => $data['target'],
            'jenis_kirim' => $data['jenis_kirim'] ?? null,
            'pesan' => $data['pesan'] ?? null,
            'dokumen_path' => $data['dokumen_path'] ?? null,
            'dokumen_namafile' => $data['dokumen_namafile'] ?? null,
            'dokumen_url' => $data['dokumen_url'] ?? null,
            'related_type' => $related instanceof Model ? $related::class : null,
            'related_id' => $related instanceof Model ? $related->getKey() : null,
            'status' => $sukses ? WaLog::STATUS_SUKSES : WaLog::STATUS_GAGAL,
            'error_message' => $sukses
                ? null
                : ($resp['error'] ?? (is_string($resp['parsed'] ?? null) ? mb_substr($resp['parsed'], 0, 500) : null)),
            'dikirim_pada' => $sukses ? now() : null,
            'percobaan' => $data['percobaan'] ?? 1,
            'log_asal_id' => $data['log_asal_id'] ?? null,
            'request_payload' => $data['request'] ?? null,
            'response_payload' => [
                'status_code' => $resp['status_code'] ?? null,
                'body' => isset($resp['body']) ? mb_substr((string) $resp['body'], 0, 4000) : null,
                'error' => $resp['error'] ?? null,
            ],
        ]);
    }

    /** @return array{ok:false,status_code:0,body:null,parsed:null,error:string} */
    private function gagalHttp(string $pesan): array
    {
        return ['ok' => false, 'status_code' => 0, 'body' => '', 'parsed' => null, 'error' => $pesan];
    }

    /** Base URL endpoint per jenis: kolom ProviderAccount menang, lalu config. */
    private function baseUrl(WaDevice $device, string $jenis): ?string
    {
        $kolom = match ($jenis) {
            'qr' => 'base_url_qr',
            'send' => 'base_url_send',
            'group' => 'base_url_group',
            'document' => 'base_url_document',
            default => null,
        };

        $nilai = $kolom ? ($device->providerAccount?->{$kolom} ?? null) : null;
        $nilai = trim((string) ($nilai ?: config('wa.base_url.'.$jenis)));

        return $nilai === '' ? null : rtrim($nilai, '/');
    }

    /** URL reset: config reset → turunan document (.php → folder sama/api.php). */
    private function resetUrl(WaDevice $device): ?string
    {
        $override = trim((string) config('wa.base_url.reset'));
        if ($override !== '') {
            return $override;
        }

        $base = $this->baseUrl($device, 'document');
        if ($base === null) {
            return null;
        }

        return str_ends_with($base, '.php')
            ? dirname($base).'/api.php'
            : $base.'/api.php';
    }

    /** Parameter identitas kirim ke provider. */
    private function params(WaDevice $device): array
    {
        return [
            'iduser' => $device->providerAccount?->iduser,
            'device' => $device->device_name,
        ];
    }

    private function opsiHttp(): array
    {
        return [
            'verify' => (bool) config('wa.verify_ssl', true),
            'timeout' => (int) config('wa.timeout', 30),
        ];
    }

    /**
     * POST form; bila provider membalas 405 (harus GET), fallback GET
     * (pola arm_app WablastedService::call()).
     *
     * @return array{ok:bool,status_code:int,body:string,parsed:mixed,error:?string}
     */
    private function call(string $url, array $params): array
    {
        try {
            $resp = Http::withOptions($this->opsiHttp())->asForm()->post($url, $params);
            if ($resp->status() === 405) {
                $resp = Http::withOptions($this->opsiHttp())->get($url, $params);
            }
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status_code' => 0,
                'body' => '',
                'parsed' => null,
                'error' => $e->getMessage(),
            ];
        }

        $body = (string) $resp->body();

        return [
            'ok' => $resp->successful(),
            'status_code' => $resp->status(),
            'body' => $body,
            'parsed' => $resp->json() ?? $body,
            'error' => null,
        ];
    }

    /** @param array{ok:bool,status_code:int,body:string,parsed:mixed,error:?string} $resp */
    private function normalisasiQr(array $resp): array
    {
        $parsed = $resp['parsed'];
        $body = is_array($parsed) ? $parsed : [];

        $image = $body['qr'] ?? $body['image'] ?? $body['base64'] ?? null;
        $hasQr = $image !== null;

        if (! $hasQr && is_string($parsed) && str_starts_with($parsed, 'data:image')) {
            $hasQr = true;
            $image = $parsed;
        }

        if (is_string($image) && $image !== ''
            && ! str_starts_with($image, 'data:')
            && ! str_starts_with($image, 'http')) {
            $image = 'data:image/png;base64,'.$image;
        }

        return [
            'ok' => $resp['ok'],
            'status_code' => $resp['status_code'],
            'body' => $resp['body'],
            'parsed' => $parsed,
            'error' => $resp['error'],
            'message' => is_array($parsed) ? ($parsed['message'] ?? null) : null,
            'has_qr' => $hasQr,
            'image' => $image,
        ];
    }

    /** @return array{ok:false,status_code:0,body:null,parsed:null,error:string,message:null,has_qr:false,image:null} */
    private function gagal(string $pesan): array
    {
        return [
            'ok' => false,
            'status_code' => 0,
            'body' => null,
            'parsed' => null,
            'error' => $pesan,
            'message' => null,
            'has_qr' => false,
            'image' => null,
        ];
    }
}
