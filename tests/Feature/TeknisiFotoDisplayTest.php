<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportPhoto;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::disk('public')->put('work-reports/sebelum.jpg', 'foto-sebelum');
    Storage::disk('public')->put('work-reports/sesudah.jpg', 'foto-sesudah');

    $this->mkTeknisi = fn (): User => tap(User::factory()->create(), fn (User $u) => $u->assignRole(RoleName::Teknisi->value));
    $this->mkAdmin = fn (): User => tap(User::factory()->create(), fn (User $u) => $u->assignRole(RoleName::Admin->value));
});

function fotoOrderSelesai(User $teknisi): Order
{
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Selesai,
    ]);

    WorkReport::factory()->create([
        'order_id' => $order->id,
        'teknisi_id' => $teknisi->id,
        'catatan_pengerjaan' => 'Selesai cuci AC.',
        'foto_sebelum' => 'work-reports/sebelum.jpg',
        'foto_sesudah' => 'work-reports/sesudah.jpg',
    ]);

    return $order;
}

it('teknisi melihat foto sebelum & sesudah di preview detail order', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->actingAs($teknisi)
        ->get("/teknisi/order/{$order->id}")
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sebelum.jpg')
        ->assertSee('storage/work-reports/sesudah.jpg')
        ->assertSee('Foto Pengerjaan');
});

it('halaman riwayat teknisi menampilkan thumbnail foto sesudah', function () {
    $teknisi = ($this->mkTeknisi)();
    fotoOrderSelesai($teknisi);

    $this->actingAs($teknisi)
        ->get('/teknisi/riwayat')
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sesudah.jpg');
});

it('admin melihat foto sebelum & sesudah di detail order', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}")
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sebelum.jpg')
        ->assertSee('storage/work-reports/sesudah.jpg');
});

it('resi publik menampilkan foto pengerjaan', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->get("/resi/{$order->id}/{$order->pastikanResiToken()}")
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sebelum.jpg')
        ->assertSee('storage/work-reports/sesudah.jpg');
});

it('teknisi melihat blok "Perbarui Foto Laporan" setelah laporan disubmit', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->actingAs($teknisi)
        ->get("/teknisi/order/{$order->id}")
        ->assertSuccessful()
        ->assertSee('Perbarui Foto Laporan')
        ->assertSee('Ganti Foto Sebelum')
        ->assertSee('Ganti Foto Sesudah');
});

it('area foto teknisi memakai height yang lebih besar (h-44 preview, h-56 galeri)', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    $this->actingAs($teknisi)
        ->get("/teknisi/order/{$order->id}")
        ->assertSuccessful()
        ->assertSee('h-44')
        ->assertSee('h-56');
});

it('perbaruiFotoLaporan mengganti foto sebelum laporan terakhir & menghapus file lama', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);
    Storage::disk('public')->put('work-reports/sebelum-baru.jpg', 'foto-baru');

    app(TeknisiService::class)->perbaruiFotoLaporan(
        $order->fresh(),
        $teknisi,
        'work-reports/sebelum-baru.jpg',
        null,
    );

    $laporan = $order->workReports()->latest('id')->first();
    expect($laporan->foto_sebelum)->toBe('work-reports/sebelum-baru.jpg');
    expect($laporan->foto_sesudah)->toBe('work-reports/sesudah.jpg');
    Storage::disk('public')->assertMissing('work-reports/sebelum.jpg');
});

it('perbaruiFotoLaporan ditolak kalau laporan belum pernah disubmit', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    app(TeknisiService::class)->perbaruiFotoLaporan($order, $teknisi, 'work-reports/x.jpg', null);
})->throws(BusinessRuleException::class, 'setelah laporan disubmit');

it('perbaruiFotoLaporan ditolak kalau tidak ada satu pun foto pengganti', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    app(TeknisiService::class)->perbaruiFotoLaporan($order->fresh(), $teknisi, null, null);
})->throws(BusinessRuleException::class, 'minimal satu foto');

it('form teknisi bisa mengunggah foto pengganti sebelum setelah laporan disubmit', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('fotoSebelumBaru', UploadedFile::fake()->image('sebelum-baru.jpg'))
        ->call('simpanPerbaikanFoto')
        ->assertOk();

    $laporan = $order->workReports()->latest('id')->first();
    expect($laporan->foto_sebelum)->not->toBe('work-reports/sebelum.jpg');
    Storage::disk('public')->assertExists($laporan->foto_sebelum);
    Storage::disk('public')->assertMissing('work-reports/sebelum.jpg');
});

it('slot foto per layanan menampilkan elemen preview gambar, bukan cuma teks Terpilih', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    $this->actingAs($teknisi)
        ->get("/teknisi/order/{$order->id}")
        ->assertSuccessful()
        ->assertSee('alt="Pratinjau Foto Tampak Depan Lokasi"', false)
        ->assertSee('alt="Pratinjau Foto Cek Suhu (Indoor)"', false);
});

it('riwayat teknisi menampilkan galeri foto laporan (sebelum, sesudah, kategori)', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = fotoOrderSelesai($teknisi);
    $item = $order->orderItems->first();

    WorkReportPhoto::create([
        'work_report_id' => $order->workReports->first()->id,
        'order_item_id' => $item->id,
        'slot' => 'foto_tampak_depan_lokasi',
        'path' => 'work-reports/depan.jpg',
        'urutan' => 0,
    ]);

    $this->actingAs($teknisi)
        ->get('/teknisi/riwayat')
        ->assertSuccessful()
        ->assertSee('storage/work-reports/sebelum.jpg')
        ->assertSee('storage/work-reports/sesudah.jpg')
        ->assertSee('storage/work-reports/depan.jpg')
        ->assertSee('Foto Tampak Depan Lokasi');
});
