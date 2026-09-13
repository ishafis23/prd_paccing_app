<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WorkReportPhoto;
use App\Services\OrderService;
use App\Services\TeknisiService;
use App\Support\FotoLaporanSlot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkTeknisi = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Teknisi->value);

        return $user;
    };

    $this->mkAdmin = function (): User {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Admin->value);

        return $user;
    };
});

it('FotoLaporanSlot punya template urut utk tiap kategori & fallback default', function () {
    expect(array_keys(FotoLaporanSlot::untuk(ServiceType::CuciAc)))
        ->toBe(['outdoor_proses', 'indoor_proses', 'indoor_sebelum', 'indoor_sesudah_suhu']);
    expect(array_keys(FotoLaporanSlot::untuk(ServiceType::TambahFreon)))
        ->toBe(['tekanan_sebelum', 'tekanan_sesudah']);
    expect(array_keys(FotoLaporanSlot::untuk(ServiceType::ServiceAc)))
        ->toBe(['kondisi_sebelum', 'proses_service', 'kondisi_sesudah']);
    expect(array_keys(FotoLaporanSlot::untuk(ServiceType::Instalasi)))
        ->toBe(['lokasi_sebelum', 'unit_terpasang', 'testing_suhu']);
    expect(array_keys(FotoLaporanSlot::untuk(ServiceType::Relokasi)))
        ->toBe(['lokasi_asal', 'lokasi_baru', 'unit_terpasang']);
    expect(array_keys(FotoLaporanSlot::untuk(ServiceType::Bongkar)))
        ->toBe(['sebelum_bongkar', 'sesudah_bongkar']);
    expect(array_keys(FotoLaporanSlot::untuk(null)))->toBe(['sebelum', 'sesudah']);
});

it('submitLaporan dengan foto_kategori membuat WorkReportPhoto sesuai order_item & urutan slot', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems->first(); // kategori CuciAc dari factory default

    $report = app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai cuci AC.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $item->id, 'slot' => 'indoor_sebelum', 'path' => 'work-reports/indoor-sebelum.jpg'],
            ['order_item_id' => $item->id, 'slot' => 'outdoor_proses', 'path' => 'work-reports/outdoor-proses.jpg'],
        ],
    ]);

    expect($report->photos)->toHaveCount(2);

    $outdoor = $report->photos->firstWhere('slot', 'outdoor_proses');
    $indoor = $report->photos->firstWhere('slot', 'indoor_sebelum');
    expect($outdoor->order_item_id)->toBe($item->id);
    expect($outdoor->urutan)->toBe(0); // outdoor_proses = slot pertama di template CuciAc
    expect($indoor->urutan)->toBe(2); // indoor_sebelum = slot ketiga
});

it('submitLaporan menolak order_item yang bukan milik order', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $itemLain = OrderItem::factory()->create();

    app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $itemLain->id, 'slot' => 'outdoor_proses', 'path' => 'x.jpg'],
        ],
    ]);
})->throws(BusinessRuleException::class, 'tidak ditemukan');

it('submitLaporan menolak slot yang tidak sesuai template kategori', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems->first();

    app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $item->id, 'slot' => 'slot_ngasal', 'path' => 'x.jpg'],
        ],
    ]);
})->throws(BusinessRuleException::class, 'tidak valid');

it('admin menambah layanan dgn kategori TambahFreon, teknisi submit foto sesuai template freon', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    $itemFreon = app(OrderService::class)->tambahLayanan($order, [
        'nama_layanan' => 'Tambah Freon',
        'kategori' => ServiceType::TambahFreon->value,
        'harga' => 50000,
    ], $admin);

    $report = app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Freon ditambah.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $itemFreon->id, 'slot' => 'tekanan_sebelum', 'path' => 'a.jpg'],
            ['order_item_id' => $itemFreon->id, 'slot' => 'tekanan_sesudah', 'path' => 'b.jpg'],
        ],
    ]);

    expect($report->photos)->toHaveCount(2);
});

it('form Livewire submitLaporan mengunggah foto per kategori & menyimpan ke disk', function () {
    Storage::fake('public');
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems->first();

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('catatan', 'Selesai cuci AC.')
        ->set("fotoKategori.{$item->id}.outdoor_proses", UploadedFile::fake()->image('outdoor.jpg'))
        ->call('submitLaporan')
        ->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Selesai);
    $photo = WorkReportPhoto::where('order_item_id', $item->id)->where('slot', 'outdoor_proses')->first();
    expect($photo)->not->toBeNull();
    Storage::disk('public')->assertExists($photo->path);
});

it('form teknisi menampilkan slot foto sesuai kategori tiap order_item', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Outdoor - Proses')
        ->assertSee('Indoor - Sesudah (Cek Suhu)');
});

it('halaman view order admin menampilkan foto per kategori', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems->first();

    app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai cuci AC.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $item->id, 'slot' => 'outdoor_proses', 'path' => 'work-reports/outdoor.jpg'],
        ],
    ]);

    $this->actingAs($admin)->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Foto per Kategori')
        ->assertSee('Outdoor Proses');
});
