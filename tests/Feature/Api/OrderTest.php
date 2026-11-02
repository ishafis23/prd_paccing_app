<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTechnician;
use App\Models\OrderUnitReport;
use App\Models\TemporaryPhotoUpload;
use App\Models\User;
use App\Models\WorkReportPhoto;
use App\Support\FotoLaporanSlot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->mk = fn (): User => tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->teknisi = ($this->mk)();

    // Order Cuci AC dengan $n baris (1 unit/baris) bertaut ke unit AC.
    $this->buatOrder = function (int $n = 1, OrderStatus $status = OrderStatus::Dikerjakan, ?User $pic = null): Order {
        $order = Order::factory()->create([
            'teknisi_id' => ($pic ?? $this->teknisi)->id,
            'status' => $status,
            'jenis_pelanggan' => CustomerJenis::Perorangan,
            'tanggal_jadwal' => today(),
        ]);
        $alamat = CustomerAddress::factory()->create(['customer_id' => $order->customer_id, 'nama_lokasi' => 'CK Pondok Indah']);
        $order->update(['customer_address_id' => $alamat->id]);
        $order->orderItems()->delete();

        for ($i = 1; $i <= $n; $i++) {
            $ac = CustomerAcUnit::factory()->create([
                'customer_id' => $order->customer_id, 'customer_address_id' => $alamat->id,
                'kode_unit' => "AC-0{$i}", 'kode_ruangan' => "Ruang {$i}",
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id, 'kategori' => ServiceType::CuciAc, 'nama_layanan' => 'Cuci Standar',
                'customer_ac_unit_id' => $ac->id, 'jumlah' => 1, 'harga' => 75000,
            ]);
        }

        return $order->fresh('orderItems');
    };

    $this->foto = fn (string $nama = 'a.jpg') => UploadedFile::fake()->image($nama, 200, 200);
});

it('jadwal memuat order PIC dan order tim; order tim lain tidak muncul', function () {
    $rekan = ($this->mk)();
    $milikku = ($this->buatOrder)(1, OrderStatus::Terjadwal);
    $tim = ($this->buatOrder)(1, OrderStatus::Terjadwal, $rekan);
    OrderTechnician::create(['order_id' => $tim->id, 'teknisi_id' => $this->teknisi->id]);
    $orang = ($this->buatOrder)(1, OrderStatus::Terjadwal, ($this->mk)());

    Sanctum::actingAs($this->teknisi);
    $res = $this->getJson('/api/v1/jadwal')->assertOk();
    $ids = collect($res->json('data.jadwal'))->pluck('id')->all();

    expect($ids)->toContain($milikku->id)->toContain($tim->id)->not->toContain($orang->id)
        ->and($res->json('data.jadwal.0.status'))->toBe('terjadwal');
});

it('detail order milik teknisi lain 404, milik sendiri memuat item, unit & foto wajib', function () {
    $order = ($this->buatOrder)(2);
    $orang = ($this->buatOrder)(1, OrderStatus::Dikerjakan, ($this->mk)());

    Sanctum::actingAs($this->teknisi);
    $this->getJson("/api/v1/order/{$orang->id}")->assertStatus(404);

    $res = $this->getJson("/api/v1/order/{$order->id}")->assertOk()
        ->assertJsonPath('data.id', $order->id)->assertJsonPath('data.saya_pic', true);
    expect($res->json('data.items'))->toHaveCount(2)
        ->and($res->json('data.units'))->toHaveCount(2)
        ->and($res->json('data.items.0.slot_foto'))->not->toBeEmpty()
        ->and($res->json('data.items.0.harga'))->toBe(75000)
        ->and($res->json('data.pembayaran.metode_opsi'))->toContain('cash');
});

it('berangkat ditolak 422 selagi ada penghalang administrasi, lolos setelahnya', function () {
    $order = ($this->buatOrder)(1, OrderStatus::Terjadwal);
    $kemarin = today()->subDay()->toDateString();
    DailyAttendance::create(['user_id' => $this->teknisi->id, 'tanggal' => $kemarin, 'jam_datang' => $kemarin.' 08:00:00']);

    Sanctum::actingAs($this->teknisi);
    $this->postJson("/api/v1/order/{$order->id}/berangkat")->assertStatus(422)->assertJsonPath('code', 'aturan_bisnis');

    $this->postJson('/api/v1/pengeluaran', [
        'tanggal_input' => $kemarin, 'kategori' => 'bensin', 'qty' => 1, 'harga' => 20000, 'nominal' => 20000,
    ])->assertCreated();

    $this->postJson("/api/v1/order/{$order->id}/berangkat")->assertOk()->assertJsonPath('data.status', 'menuju_lokasi');
});

it('check-in pertama hari itu wajib foto titik pertama; berikutnya tidak', function () {
    $order = ($this->buatOrder)(1, OrderStatus::MenujuLokasi);

    Sanctum::actingAs($this->teknisi);
    $this->postJson("/api/v1/order/{$order->id}/check-in")->assertStatus(422)->assertJsonPath('code', 'foto_titik_pertama_wajib');

    $this->post("/api/v1/order/{$order->id}/check-in", ['foto_titik_pertama' => ($this->foto)()], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.status', 'dikerjakan');

    $kedua = ($this->buatOrder)(1, OrderStatus::MenujuLokasi);
    $this->postJson("/api/v1/order/{$kedua->id}/check-in")->assertOk()->assertJsonPath('data.status', 'dikerjakan');
});

it('kendala menandai order terkendala; order orang lain 404', function () {
    $order = ($this->buatOrder)(1, OrderStatus::Dikerjakan);
    $orang = ($this->buatOrder)(1, OrderStatus::Dikerjakan, ($this->mk)());

    Sanctum::actingAs($this->teknisi);
    $this->postJson("/api/v1/order/{$orang->id}/kendala", ['alasan' => 'Customer tidak ada'])->assertStatus(404);
    $this->postJson("/api/v1/order/{$order->id}/kendala", ['alasan' => 'Customer tidak ada'])
        ->assertOk()->assertJsonPath('data.status', 'terkendala')->assertJsonPath('data.alasan_kendala', 'Customer tidak ada');
});

it('tambah layanan, batalkan item, hapus layanan', function () {
    $order = ($this->buatOrder)(1, OrderStatus::Dikerjakan);
    Sanctum::actingAs($this->teknisi);

    $res = $this->postJson("/api/v1/order/{$order->id}/layanan", [
        'nama_layanan' => 'Ganti Kapasitor', 'kategori' => ServiceType::CuciAc->value, 'harga' => 150000, 'jumlah' => 1,
    ])->assertOk();
    expect($res->json('data.items'))->toHaveCount(2);

    $baru = collect($res->json('data.items'))->firstWhere('nama_layanan', 'Ganti Kapasitor');
    $this->postJson("/api/v1/order/{$order->id}/item/{$baru['id']}/batal")->assertOk();
    expect(OrderItem::find($baru['id'])->dibatalkan())->toBeTrue();

    $this->deleteJson("/api/v1/order/{$order->id}/item/{$baru['id']}/batal")->assertOk();
    $this->deleteJson("/api/v1/order/{$order->id}/layanan/{$baru['id']}")->assertOk();
    expect(OrderItem::where('order_id', $order->id)->count())->toBe(1);
});

it('alur laporan: foto ditampung per slot (idempoten), keterangan unit, kirim laporan memakai foto tampungan', function () {
    $order = ($this->buatOrder)(1);
    $item = $order->orderItems->first();
    $slots = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))->keys();

    Sanctum::actingAs($this->teknisi);
    $detail = $this->getJson("/api/v1/order/{$order->id}")->assertOk();
    $unitId = $detail->json('data.units.0.id');

    // Unggah ulang slot yang sama tidak menggandakan.
    foreach ($slots as $slot) {
        $this->post("/api/v1/order/{$order->id}/foto", ['file' => ($this->foto)(), 'tipe' => 'kategori', 'order_item_id' => $item->id, 'slot' => $slot], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'ditampung');
    }
    $this->post("/api/v1/order/{$order->id}/foto", ['file' => ($this->foto)('b.jpg'), 'tipe' => 'kategori', 'order_item_id' => $item->id, 'slot' => $slots->first()], ['Accept' => 'application/json'])->assertCreated();
    expect(TemporaryPhotoUpload::where('order_id', $order->id)->count())->toBe($slots->count());

    // Slot tidak valid ditolak.
    $this->post("/api/v1/order/{$order->id}/foto", ['file' => ($this->foto)(), 'tipe' => 'kategori', 'order_item_id' => $item->id, 'slot' => 'ngawur'], ['Accept' => 'application/json'])->assertStatus(422);

    // Laporan terkirim; foto wajib terpenuhi, keterangan unit masih kurang.
    $res = $this->postJson("/api/v1/order/{$order->id}/laporan", ['catatan' => 'Selesai dicuci'])->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Selesai)
        ->and(WorkReportPhoto::count())->toBe($slots->count())
        ->and(TemporaryPhotoUpload::count())->toBe(0)
        ->and(collect($res->json('data.order.foto_wajib_kurang'))->pluck('jenis')->all())->toBe(['keterangan']);

    // Isi keterangan unit -> lengkap, hilang dari daftar lengkapi.
    $this->getJson('/api/v1/lengkapi-laporan')->assertOk()->assertJsonPath('meta.jumlah', 1);
    $this->putJson("/api/v1/order/{$order->id}/unit/{$unitId}", [
        'lokasi_label' => 'CK', 'posisi' => 'Ruang Server', 'jenis_pekerjaan' => 'Cuci', 'bagian' => 'indoor',
        'suhu' => '16.5', 'rpm' => '1200', 'kondisi' => 'normal',
    ])->assertOk()->assertJsonPath('data.lengkap', true);
    $this->getJson('/api/v1/lengkapi-laporan')->assertOk()->assertJsonPath('meta.jumlah', 0);
});

it('simpan unit tidak normal tanpa catatan 422; hapus foto laporan memunculkan kekurangan lagi', function () {
    $order = ($this->buatOrder)(1);
    $item = $order->orderItems->first();
    Sanctum::actingAs($this->teknisi);
    $unitId = $this->getJson("/api/v1/order/{$order->id}")->json('data.units.0.id');

    $this->putJson("/api/v1/order/{$order->id}/unit/{$unitId}", [
        'bagian' => 'indoor', 'suhu' => '16', 'kondisi' => 'tidak_normal',
    ])->assertStatus(422)->assertJsonPath('code', 'validasi');

    foreach (collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))->keys() as $slot) {
        $this->post("/api/v1/order/{$order->id}/foto", ['file' => ($this->foto)(), 'tipe' => 'kategori', 'order_item_id' => $item->id, 'slot' => $slot], ['Accept' => 'application/json'])->assertCreated();
    }
    $this->postJson("/api/v1/order/{$order->id}/laporan", ['catatan' => 'oke selesai'])->assertOk();

    $foto = WorkReportPhoto::first();
    $this->deleteJson("/api/v1/order/{$order->id}/foto/{$foto->id}")->assertOk();
    $kurang = collect($this->getJson("/api/v1/order/{$order->id}/foto")->json('data.foto_wajib_kurang'));
    expect($kurang->where('jenis', 'foto'))->not->toBeEmpty();

    // Setelah laporan ada, unggah slot yang kurang langsung tersimpan.
    $slot = $kurang->where('jenis', 'foto')->first();
    $this->post("/api/v1/order/{$order->id}/foto", ['file' => ($this->foto)(), 'tipe' => 'kategori', 'order_item_id' => $slot['order_item_id'], 'slot' => $slot['kode_slot']], ['Accept' => 'application/json'])
        ->assertCreated()->assertJsonPath('data.status', 'tersimpan');
});

it('tutup order tanpa metode bayar 422; dengan metode & bukti berhasil', function () {
    $order = ($this->buatOrder)(1, OrderStatus::Selesai);
    Sanctum::actingAs($this->teknisi);

    $this->postJson("/api/v1/order/{$order->id}/tutup")->assertStatus(422)->assertJsonPath('code', 'aturan_bisnis');

    $this->postJson("/api/v1/order/{$order->id}/pembayaran/metode", ['metode' => PaymentMethod::Transfer->value])
        ->assertOk()->assertJsonPath('data.pembayaran.metode_dipilih', 'transfer');
    $this->post("/api/v1/order/{$order->id}/pembayaran/bukti", ['foto' => ($this->foto)()], ['Accept' => 'application/json'])->assertOk();
    $this->postJson("/api/v1/order/{$order->id}/pembayaran/lapor-bayar", ['sudah' => true])->assertOk()->assertJsonPath('data.pembayaran.bayar_dilaporkan', true);

    $this->postJson("/api/v1/order/{$order->id}/tutup")->assertOk()->assertJsonPath('data.ditutup', true);
});

it('setoran tunai telat menahan aksi tulis order sampai dilaporkan', function () {
    $order = ($this->buatOrder)(1, OrderStatus::Terjadwal);
    $lama = ($this->buatOrder)(1, OrderStatus::Selesai);
    $lama->update(['metode_dipilih' => PaymentMethod::Cash, 'metode_dipilih_pada' => now()->subDays(3)]);

    Sanctum::actingAs($this->teknisi);
    $this->postJson("/api/v1/order/{$order->id}/kendala", ['alasan' => 'x'])->assertStatus(422)->assertJsonPath('code', 'aturan_bisnis');
    $this->getJson("/api/v1/order/{$order->id}")->assertOk(); // membaca tetap boleh
});

it('riwayat: filter nama customer dan paginasi', function () {
    $o = ($this->buatOrder)(1, OrderStatus::Selesai);
    ($this->buatOrder)(1, OrderStatus::Selesai);
    Sanctum::actingAs($this->teknisi);

    $semua = $this->getJson('/api/v1/riwayat')->assertOk();
    expect($semua->json('meta.total'))->toBe(2);

    $filter = $this->getJson('/api/v1/riwayat?q='.urlencode((string) $o->customer->nama))->assertOk();
    expect(collect($filter->json('data'))->pluck('id')->all())->toContain($o->id);
});
