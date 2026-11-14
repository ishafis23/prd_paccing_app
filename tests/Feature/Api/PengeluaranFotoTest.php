<?php

use App\Enums\RoleName;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\FotoBuktiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    config(['expense.foto_wajib' => true]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->teknisi = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    Sanctum::actingAs($this->teknisi);

    $this->body = fn (array $ubah = []): array => $ubah + [
        'tanggal_input' => today()->toDateString(), 'kategori' => 'bensin', 'qty' => 1, 'harga' => 20000, 'nominal' => 20000,
    ];

    // Foto bergambar (bukan polos) supaya hash perseptualnya bermakna; $seed membedakan isi.
    $this->foto = function (int $seed, string $nama = 'bukti.png'): UploadedFile {
        $im = imagecreatetruecolor(64, 64);
        mt_srand($seed);
        for ($i = 0; $i < 12; $i++) {
            $warna = imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($im, mt_rand(0, 40), mt_rand(0, 40), mt_rand(20, 63), mt_rand(20, 63), $warna);
        }
        $path = tempnam(sys_get_temp_dir(), 'ft').'.png';
        imagepng($im, $path);

        return new UploadedFile($path, $nama, 'image/png', null, true);
    };
});

it('tanpa foto bukti -> 422 saat foto wajib', function () {
    $this->postJson('/api/v1/pengeluaran', ($this->body)())->assertStatus(422);

    expect(TeknisiExpense::count())->toBe(0);
});

it('foto wajib bisa dimatikan lewat config', function () {
    config(['expense.foto_wajib' => false]);

    $this->postJson('/api/v1/pengeluaran', ($this->body)())->assertCreated();
});

it('dengan foto -> tersimpan beserta hash dan timestamp, foto_url ada di respons', function () {
    $res = $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(1)]), ['Accept' => 'application/json'])
        ->assertCreated();

    $e = TeknisiExpense::first();
    expect($e->foto_path)->not->toBeNull()
        ->and($e->foto_hash)->toHaveLength(64)
        ->and($e->foto_diunggah_pada)->not->toBeNull()
        ->and($res->json('data.foto_url'))->toContain('pengeluaran-bukti');
    Storage::disk('public')->assertExists($e->foto_path);
});

it('foto yang sama persis ditolak 422 (juga oleh teknisi lain) dan tidak menyimpan apa pun', function () {
    $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(7)]), ['Accept' => 'application/json'])->assertCreated();

    $lain = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    Sanctum::actingAs($lain);

    $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(7, 'lain.png')]), ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('code', 'validasi');

    expect(TeknisiExpense::count())->toBe(1)
        ->and(Storage::disk('public')->allFiles('pengeluaran-bukti'))->toHaveCount(1);
});

it('foto yang disimpan ulang (byte beda, gambar sama) tetap terdeteksi kembar', function () {
    $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(11)]), ['Accept' => 'application/json'])->assertCreated();

    // Gambar identik, di-encode ulang sebagai JPEG -> sha256 beda, hash perseptual sama.
    $asli = ($this->foto)(11);
    $im = imagecreatefrompng($asli->getRealPath());
    $jpg = tempnam(sys_get_temp_dir(), 'ft').'.jpg';
    imagejpeg($im, $jpg, 80);
    $ulang = new UploadedFile($jpg, 'ulang.jpg', 'image/jpeg', null, true);

    $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => $ulang]), ['Accept' => 'application/json'])
        ->assertStatus(422);
});

it('foto berbeda diterima', function () {
    $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(21)]), ['Accept' => 'application/json'])->assertCreated();
    $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(22)]), ['Accept' => 'application/json'])->assertCreated();

    expect(TeknisiExpense::count())->toBe(2);
});

it('mengganti foto saat ubah: foto lama dihapus, foto sendiri tidak dianggap kembar', function () {
    $id = $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(31)]), ['Accept' => 'application/json'])->json('data.id');
    $lama = TeknisiExpense::find($id)->foto_path;

    $this->post("/api/v1/pengeluaran/{$id}", ($this->body)(['_method' => 'PUT', 'foto' => ($this->foto)(32)]), ['Accept' => 'application/json'])->assertOk();

    $baru = TeknisiExpense::find($id)->foto_path;
    expect($baru)->not->toBe($lama);
    Storage::disk('public')->assertMissing($lama);
    Storage::disk('public')->assertExists($baru);
});

it('hapus pengeluaran ikut menghapus file foto', function () {
    $id = $this->post('/api/v1/pengeluaran', ($this->body)(['foto' => ($this->foto)(41)]), ['Accept' => 'application/json'])->json('data.id');
    $path = TeknisiExpense::find($id)->foto_path;

    $this->deleteJson("/api/v1/pengeluaran/{$id}")->assertOk();

    Storage::disk('public')->assertMissing($path);
});

it('ringkasan bulan_ini punya total numerik (perbaikan Rp NaN)', function () {
    config(['expense.foto_wajib' => false]);
    $this->postJson('/api/v1/pengeluaran', ($this->body)(['harga' => 15000, 'nominal' => 15000]))->assertCreated();
    $this->postJson('/api/v1/pengeluaran', ($this->body)(['harga' => 35000, 'nominal' => 35000]))->assertCreated();

    $this->getJson('/api/v1/pengeluaran')->assertOk()
        ->assertJsonPath('ringkasan.bulan_ini.total', 50000)
        ->assertJsonPath('ringkasan.hari_ini.total', 50000);
});

it('phash gambar polos null (tidak dihitung kembar) dan jarak Hamming benar', function () {
    $svc = app(FotoBuktiService::class);
    $polos = imagecreatetruecolor(32, 32);
    ob_start();
    imagepng($polos);
    $isi = ob_get_clean();

    expect($svc->phash($isi))->toBeNull()
        ->and($svc->jarak('ffffffffffffffff', 'fffffffffffffff0'))->toBe(4);
});
