<?php

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\WaLogResource;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Services\WhatsappService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');

    config([
        'wa.aktif' => true,
        'wa.document_token' => 'tok123',
        'wa.pdf_secret' => 'rahasia',
        'wa.rate_limit' => 0,
        'wa.base_url.group' => null,
    ]);

    $this->provider = ProviderAccount::factory()->create([
        'base_url_qr' => 'https://gw.test',
        'base_url_send' => 'https://gw.test',
        'base_url_document' => 'https://gw.test/apiwablasted/api_document.php',
    ]);
    $this->device = WaDevice::factory()->for($this->provider, 'providerAccount')->create([
        'connection_status' => WaDevice::KONEKSI_TERHUBUNG,
    ]);
    $this->user = User::factory()->create();
    $this->user->assignRole(RoleName::Admin->value);

    // Pre-flight qr: default "ok tanpa QR" = terhubung. Test bisa menimpa
    // $this->qrResponse untuk mensimulasikan device belum terhubung.
    $this->qrResponse = ['status' => 'ok'];
    Http::fake([
        'https://gw.test/api/qr/*' => fn () => Http::response($this->qrResponse, 200),
        'https://gw.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
        'https://gw.test/apiwablasted/api_document.php' => Http::response(['status' => 'terkirim'], 200),
    ]);
});

it('kirimTeks menulis wa_logs sukses + nomor ternormalisasi', function () {
    $log = app(WhatsappService::class)->kirimTeks(
        $this->user, $this->device, '0812-3456-7890', 'Halo tes',
    );

    expect($log->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($log->target)->toBe('6281234567890')
        ->and($log->pesan)->toBe('Halo tes')
        ->and($log->dikirim_pada)->not->toBeNull()
        ->and($log->sukses())->toBeTrue();
});

it('kirimTeks menolak bila pre-flight menyatakan device belum terhubung', function () {
    $this->qrResponse = ['qr' => 'data:image/png;base64,AAA'];

    expect(fn () => app(WhatsappService::class)->kirimTeks($this->user, $this->device, '6281234567890', 'x'))
        ->toThrow(BusinessRuleException::class);

    expect(WaLog::query()->count())->toBe(0);
});

it('menegakkan rate limit per menit', function () {
    config(['wa.rate_limit' => 2]);

    app(WhatsappService::class)->kirimTeks($this->user, $this->device, '6281111111111', 'a');
    app(WhatsappService::class)->kirimTeks($this->user, $this->device, '6281111111112', 'b');

    expect(fn () => app(WhatsappService::class)->kirimTeks($this->user, $this->device, '6281111111113', 'c'))
        ->toThrow(BusinessRuleException::class, 'Terlalu banyak kirim');
});

it('kirimGrup mempertahankan JID grup apa adanya + menulis wa_logs', function () {
    $jid = '120363023284398181@g.us';

    $log = app(WhatsappService::class)->kirimGrup(
        $this->user, $this->device, $jid, 'Halo grup',
    );

    expect($log->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($log->target)->toBe($jid)
        ->and($log->action)->toBe('send-grup')
        ->and($log->jenis_kirim)->toBe('grup');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send/')
        && $request['target'] === $jid
        && $request['jenis'] === 'teks');
});

it('kirimGrup memakai base URL grup bila dikonfigurasi', function () {
    $this->provider->update(['base_url_group' => 'https://grup.test']);
    Http::fake([
        'https://gw.test/api/qr/*' => fn () => Http::response(['status' => 'ok'], 200),
        'https://grup.test/api/send/*' => Http::response(['status' => 'terkirim'], 200),
    ]);

    $log = app(WhatsappService::class)->kirimGrup($this->user, $this->device, '1234567890@g.us', 'x');

    expect($log->status)->toBe(WaLog::STATUS_SUKSES);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://grup.test/api/send/'));
});

it('kirimUlang log grup tetap memakai JID grup apa adanya', function () {
    $jid = '120363023284398181@g.us';
    $asal = WaLog::create([
        'user_id' => $this->user->id,
        'wa_device_id' => $this->device->id,
        'action' => 'send-grup',
        'target' => $jid,
        'jenis_kirim' => 'grup',
        'pesan' => 'Pesan grup yang gagal',
        'status' => WaLog::STATUS_GAGAL,
        'error_message' => 'timeout',
        'percobaan' => 1,
    ]);

    $baru = app(WhatsappService::class)->kirimUlang($asal);

    expect($baru->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($baru->action)->toBe('send-grup')
        ->and($baru->target)->toBe($jid)
        ->and($baru->log_asal_id)->toBe($asal->id);
});

it('kirimDokumen menyimpan snapshot + mengirim URL bertanda tangan dengan Bearer', function () {
    $path = app(WhatsappService::class)->simpanSnapshot('%PDF-1.4 dummy', 'invoice-INV1.pdf');

    $log = app(WhatsappService::class)->kirimDokumen(
        $this->user, $this->device, '6281234567890', $path, 'Caption invoice', 'invoice-INV1.pdf', null, 'invoice',
    );

    expect($log->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($log->dokumen_path)->toBe($path)
        ->and($log->dokumen_namafile)->toBe('invoice-INV1.pdf');
    Storage::disk('local')->assertExists($path);

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/apiwablasted/api_document.php')
            && $request['jenis'] === 'document'
            && $request['namafile'] === 'invoice-INV1.pdf'
            && str_contains((string) $request['dokumen'], 'berkas/publik')
            && $request->hasHeader('Authorization', 'Bearer tok123');
    });
});

it('kirimGambar menyimpan file + mengirim jenis=gambar dengan URL bertanda tangan', function () {
    $path = app(WhatsappService::class)->simpanGambar('dummy-binary-jpg', 'resi.jpg');

    $log = app(WhatsappService::class)->kirimGambar(
        $this->user, $this->device, '0812-3456-7890', $path, 'Caption gambar', 'resi.jpg',
    );

    expect($log->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($log->action)->toBe('send-gambar')
        ->and($log->target)->toBe('6281234567890')
        ->and($log->dokumen_path)->toBe($path)
        ->and($log->dokumen_namafile)->toBe('resi.jpg');
    Storage::disk('local')->assertExists($path);

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/api/send/')
            && $request['jenis'] === 'gambar'
            && $request['target'] === '6281234567890'
            && str_contains((string) $request['gambar'], 'berkas/publik');
    });
});

it('kirimGambarGrup mempertahankan JID grup apa adanya', function () {
    $jid = '120363023284398181@g.us';
    $path = app(WhatsappService::class)->simpanGambar('dummy-binary-jpg', 'resi.jpg');

    $log = app(WhatsappService::class)->kirimGambarGrup(
        $this->user, $this->device, $jid, $path, 'Caption gambar grup', 'resi.jpg',
    );

    expect($log->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($log->action)->toBe('send-gambar-grup')
        ->and($log->target)->toBe($jid);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/send/')
        && $request['jenis'] === 'gambar' && $request['target'] === $jid);
});

it('kirimUlang gambar memakai file tersimpan tanpa upload ulang', function () {
    $path = app(WhatsappService::class)->simpanGambar('dummy-binary-jpg', 'resi.jpg');
    $asal = WaLog::create([
        'user_id' => $this->user->id,
        'wa_device_id' => $this->device->id,
        'action' => 'send-gambar',
        'target' => '6281234567890',
        'jenis_kirim' => 'gambar',
        'pesan' => 'Caption gambar',
        'dokumen_path' => $path,
        'dokumen_namafile' => 'resi.jpg',
        'status' => WaLog::STATUS_GAGAL,
        'percobaan' => 1,
    ]);

    $baru = app(WhatsappService::class)->kirimUlang($asal);

    expect($baru->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($baru->action)->toBe('send-gambar')
        ->and($baru->dokumen_path)->toBe($path)
        ->and($baru->log_asal_id)->toBe($asal->id);
});

it('kirimUlang menolak bila file gambar sudah tidak ada', function () {
    $asal = WaLog::create([
        'user_id' => $this->user->id,
        'wa_device_id' => $this->device->id,
        'action' => 'send-gambar',
        'target' => '6281234567890',
        'pesan' => 'x',
        'dokumen_path' => 'wa-gambar/hilang.jpg',
        'status' => WaLog::STATUS_GAGAL,
    ]);

    expect(fn () => app(WhatsappService::class)->kirimUlang($asal))
        ->toThrow(BusinessRuleException::class, 'sudah tidak ada');
});

it('kirimUlang memakai payload tersimpan (log baru + percobaan naik)', function () {
    $asal = WaLog::create([
        'user_id' => $this->user->id,
        'wa_device_id' => $this->device->id,
        'action' => 'send-teks',
        'target' => '6281234567890',
        'jenis_kirim' => 'teks',
        'pesan' => 'Pesan lama yang gagal',
        'status' => WaLog::STATUS_GAGAL,
        'error_message' => 'timeout',
        'percobaan' => 1,
    ]);

    $baru = app(WhatsappService::class)->kirimUlang($asal);

    expect($baru->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($baru->log_asal_id)->toBe($asal->id)
        ->and($baru->percobaan)->toBe(2)
        ->and($baru->pesan)->toBe('Pesan lama yang gagal')
        ->and($asal->fresh()->status)->toBe(WaLog::STATUS_GAGAL); // log lama tetap
});

it('kirimUlang dokumen memakai snapshot tersimpan tanpa menyentuh sumber', function () {
    $path = app(WhatsappService::class)->simpanSnapshot('%PDF-1.4 dummy', 'laporan.pdf');
    $asal = WaLog::create([
        'user_id' => $this->user->id,
        'wa_device_id' => $this->device->id,
        'action' => 'send-document',
        'target' => '6281234567890',
        'jenis_kirim' => 'laporan',
        'pesan' => 'Caption laporan',
        'dokumen_path' => $path,
        'dokumen_namafile' => 'laporan.pdf',
        'status' => WaLog::STATUS_GAGAL,
        'percobaan' => 1,
    ]);

    $baru = app(WhatsappService::class)->kirimUlang($asal, '6289999999999');

    expect($baru->status)->toBe(WaLog::STATUS_SUKSES)
        ->and($baru->target)->toBe('6289999999999') // nomor diganti
        ->and($baru->dokumen_path)->toBe($path)
        ->and($baru->log_asal_id)->toBe($asal->id);
});

it('kirimUlang menolak bila snapshot PDF sudah tidak ada', function () {
    $asal = WaLog::create([
        'user_id' => $this->user->id,
        'wa_device_id' => $this->device->id,
        'action' => 'send-document',
        'target' => '6281234567890',
        'pesan' => 'x',
        'dokumen_path' => 'wa-dokumen/hilang.pdf',
        'status' => WaLog::STATUS_GAGAL,
    ]);

    expect(fn () => app(WhatsappService::class)->kirimUlang($asal))
        ->toThrow(BusinessRuleException::class, 'sudah tidak ada');
});

it('endpoint publik berkas menyajikan snapshot hanya dengan tanda tangan sah', function () {
    Storage::disk('local')->put('wa-dokumen/uji.pdf', '%PDF-1.4 isi');

    $exp = now()->addHour()->timestamp;
    $sig = hash_hmac('sha256', 'wa-dokumen/uji.pdf|invoice.pdf|'.$exp, 'rahasia');
    $qs = http_build_query(['file' => 'wa-dokumen/uji.pdf', 'name' => 'invoice.pdf', 'exp' => $exp, 'sig' => $sig]);

    $this->get('/berkas/publik?'.$qs)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    // Sig salah → 403.
    $this->get('/berkas/publik?'.http_build_query(['file' => 'wa-dokumen/uji.pdf', 'name' => 'invoice.pdf', 'exp' => $exp, 'sig' => 'salah']))
        ->assertForbidden();

    // Kedaluwarsa → 403.
    $expLama = now()->subHour()->timestamp;
    $sigLama = hash_hmac('sha256', 'wa-dokumen/uji.pdf|invoice.pdf|'.$expLama, 'rahasia');
    $this->get('/berkas/publik?'.http_build_query(['file' => 'wa-dokumen/uji.pdf', 'name' => 'invoice.pdf', 'exp' => $expLama, 'sig' => $sigLama]))
        ->assertForbidden();

    // Di luar folder snapshot → 400.
    $this->get('/berkas/publik?'.http_build_query(['file' => 'lain/x.pdf', 'name' => 'x.pdf', 'exp' => $exp, 'sig' => $sig]))
        ->assertBadRequest();
});

it('daftarGroup mengembalikan daftar grup dari data.groups + mengirim Bearer token', function () {
    config(['wa.base_url.groups' => 'https://gw.test/app/api/getgroupwa.php', 'wa.groups_token' => 'tokgrup']);

    Http::fake([
        'https://gw.test/app/api/getgroupwa.php' => Http::response([
            'data' => [
                'groups' => [
                    ['id' => '120363040944048443@g.us', 'subject' => 'BA AC TRI GLOBAL', 'subjectOwner' => 'x', 'size' => 1],
                    ['id' => '120363288682463696@g.us', 'subject' => 'PENGERJAAN STELLA GRACIA'],
                ],
            ],
        ], 200),
    ]);

    $hasil = app(WhatsappService::class)->daftarGroup($this->device);

    expect($hasil['ok'])->toBeTrue()
        ->and($hasil['groups'])->toBe([
            ['id' => '120363040944048443@g.us', 'subject' => 'BA AC TRI GLOBAL'],
            ['id' => '120363288682463696@g.us', 'subject' => 'PENGERJAAN STELLA GRACIA'],
        ]);

    Http::assertSent(function ($request) {
        return str_ends_with($request->url(), '/getgroupwa.php')
            && $request['iduser'] === $this->provider->iduser
            && $request['device'] === $this->device->device_name
            && $request->hasHeader('Authorization', 'Bearer tokgrup');
    });
});

it('daftarGroup mengembalikan ok=false bila base URL belum dikonfigurasi', function () {
    config(['wa.base_url.groups' => null]);

    $hasil = app(WhatsappService::class)->daftarGroup($this->device);

    expect($hasil['ok'])->toBeFalse()
        ->and($hasil['groups'])->toBe([])
        ->and($hasil['error'])->toContain('WA_BASE_URL_GROUPS');
});

it('daftarGroup mengembalikan groups kosong bila respons tanpa data.groups', function () {
    config(['wa.base_url.groups' => 'https://gw.test/app/api/getgroupwa.php']);
    Http::fake(['https://gw.test/app/api/getgroupwa.php' => Http::response(['data' => []], 200)]);

    $hasil = app(WhatsappService::class)->daftarGroup($this->device);

    expect($hasil['ok'])->toBeTrue()->and($hasil['groups'])->toBe([]);
});

it('daftarGroup mengembalikan ok=false bila gateway membalas HTTP gagal', function () {
    config(['wa.base_url.groups' => 'https://gw.test/app/api/getgroupwa.php']);
    Http::fake(['https://gw.test/app/api/getgroupwa.php' => Http::response(['message' => 'error'], 500)]);

    $hasil = app(WhatsappService::class)->daftarGroup($this->device);

    expect($hasil['ok'])->toBeFalse()->and($hasil['groups'])->toBe([]);
});

it('WhatsappTemplate::resi menyertakan catatan teknisi bila ada laporan', function () {
    $order = \App\Models\Order::factory()->create();
    \App\Models\WorkReport::factory()->for($order)->create([
        'catatan_pengerjaan' => 'AC sudah dicuci dan dites dingin.',
        'waktu_selesai' => now(),
    ]);

    $teks = \App\Support\WhatsappTemplate::resi($order, 'Paccing', 'https://x.test/resi');

    expect($teks)->toContain('Catatan Teknisi: AC sudah dicuci dan dites dingin.');
});

it('WhatsappTemplate::resi tidak menyertakan catatan teknisi bila belum ada laporan', function () {
    $order = \App\Models\Order::factory()->create();

    $teks = \App\Support\WhatsappTemplate::resi($order, 'Paccing', 'https://x.test/resi');

    expect($teks)->not->toContain('Catatan Teknisi:');
});

it('riwayat hanya untuk Owner/Admin', function () {
    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);

    $this->actingAs($teknisi);
    expect(WaLogResource::canViewAny())->toBeFalse();

    $this->actingAs($this->user); // admin
    expect(WaLogResource::canViewAny())->toBeTrue();
});
