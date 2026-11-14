<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Services\FotoTimestampService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config(['foto.timestamp' => true]);
    Storage::fake('public');

    // Foto putih polos: cap (teks putih di kotak gelap) harus mengubah piksel pojok kanan bawah.
    $this->putih = function (string $ext = 'png', int $w = 800, int $h = 600): string {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        $path = tempnam(sys_get_temp_dir(), 'ts').'.'.$ext;
        $ext === 'png' ? imagepng($im, $path) : imagejpeg($im, $path, 90);

        return $path;
    };
    $this->pikselGelap = function (string $path): int {
        $im = imagecreatefromstring(file_get_contents($path));
        $gelap = 0;
        for ($x = (int) (imagesx($im) * 0.6); $x < imagesx($im); $x += 3) {
            for ($y = (int) (imagesy($im) * 0.85); $y < imagesy($im); $y += 3) {
                if ((imagecolorat($im, $x, $y) & 0xFF) < 128) {
                    $gelap++;
                }
            }
        }

        return $gelap;
    };
});

it('tempelFile menggambar cap di pojok kanan bawah (png & jpeg), ukuran gambar tetap', function (string $ext) {
    $path = ($this->putih)($ext);
    expect(($this->pikselGelap)($path))->toBe(0);

    expect(app(FotoTimestampService::class)->tempelFile($path))->toBeTrue()
        ->and(($this->pikselGelap)($path))->toBeGreaterThan(20)
        ->and(getimagesize($path)[0])->toBe(800)
        ->and(getimagesize($path)[1])->toBe(600);
})->with(['png', 'jpg']);

it('file bukan gambar atau fitur mati dibiarkan', function () {
    $txt = tempnam(sys_get_temp_dir(), 'ts');
    file_put_contents($txt, 'bukan gambar');
    expect(app(FotoTimestampService::class)->tempelFile($txt))->toBeFalse()
        ->and(file_get_contents($txt))->toBe('bukan gambar');

    config(['foto.timestamp' => false]);
    $path = ($this->putih)();
    expect(app(FotoTimestampService::class)->tempelFile($path))->toBeFalse()
        ->and(($this->pikselGelap)($path))->toBe(0);
});

it('upload API melewati middleware: foto order tercap, foto pengeluaran tercap setelah hash, profil tidak', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $teknisi = tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    Sanctum::actingAs($teknisi);
    config(['expense.foto_wajib' => true]);

    // Pengeluaran: hash disimpan dari foto ASLI, file tersimpan sudah bercap.
    $asli = ($this->putih)('png');
    $hashAsli = hash_file('sha256', $asli);
    $this->post('/api/v1/pengeluaran', [
        'tanggal_input' => today()->toDateString(), 'kategori' => 'bensin', 'qty' => 1, 'harga' => 20000, 'nominal' => 20000,
        'foto' => new UploadedFile($asli, 'a.png', 'image/png', null, true),
    ], ['Accept' => 'application/json'])->assertCreated();

    $e = \App\Models\TeknisiExpense::first();
    expect($e->foto_hash)->toBe($hashAsli)
        ->and(($this->pikselGelap)(Storage::disk('public')->path($e->foto_path)))->toBeGreaterThan(20);

    // Foto profil tidak dicap.
    $profil = ($this->putih)('png');
    $this->post('/api/v1/akun/foto-profil', ['foto' => new UploadedFile($profil, 'p.png', 'image/png', null, true)], ['Accept' => 'application/json']);
    $tersimpan = Storage::disk('public')->allFiles('foto-profil');
    if ($tersimpan !== []) {
        expect(($this->pikselGelap)(Storage::disk('public')->path($tersimpan[0])))->toBe(0);
    }
});
