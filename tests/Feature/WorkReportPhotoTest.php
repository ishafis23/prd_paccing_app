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
    // dev-plan/17: Cuci AC sekarang dari photo_report_templates (data admin), bukan hardcode.
    expect(array_keys(FotoLaporanSlot::untuk(ServiceType::CuciAc)))
        ->toBe([
            'foto_tampak_depan_lokasi',
            'foto_sesudah_cuci_indoor',
            'foto_sesudah_cuci_outdoor',
            'foto_area_unit_indoor',
            'foto_area_unit_outdoor',
            'foto_cek_suhu_indoor',
        ]);
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

    // dev-plan/17: Cuci AC sekarang wajib ke-6 slotnya diisi.
    $fotoKategori = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))
        ->keys()
        ->map(fn ($slot) => ['order_item_id' => $item->id, 'slot' => $slot, 'path' => "work-reports/{$slot}.jpg"])
        ->all();

    $report = app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai cuci AC.',
        'materials' => [],
        'foto_kategori' => $fotoKategori,
    ]);

    expect($report->photos)->toHaveCount(6);

    $pertama = $report->photos->firstWhere('slot', 'foto_tampak_depan_lokasi');
    $terakhir = $report->photos->firstWhere('slot', 'foto_cek_suhu_indoor');
    expect($pertama->order_item_id)->toBe($item->id);
    expect($pertama->urutan)->toBe(0);
    expect($terakhir->urutan)->toBe(5);
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

    // dev-plan/17: item stub bawaan order (kategori Cuci AC) sekarang wajib
    // 6 foto — dihapus dulu supaya tes ini murni soal item Freon yg ditambah.
    $order->orderItems()->delete();

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
    // dev-plan/17: Cuci AC sekarang wajib 6 foto — tes ini fokus ke mekanisme
    // upload/simpan-ke-disk, jadi pindah ke kategori yg belum wajib (2 slot).
    $item->update(['kategori' => ServiceType::TambahFreon]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('catatan', 'Freon ditambah.')
        ->set("fotoKategori.{$item->id}.tekanan_sebelum", UploadedFile::fake()->image('tekanan-sebelum.jpg'))
        ->set("fotoKategori.{$item->id}.tekanan_sesudah", UploadedFile::fake()->image('tekanan-sesudah.jpg'))
        ->call('submitLaporan')
        ->assertOk();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Selesai);
    $photo = WorkReportPhoto::where('order_item_id', $item->id)->where('slot', 'tekanan_sebelum')->first();
    expect($photo)->not->toBeNull();
    Storage::disk('public')->assertExists($photo->path);
});

it('form teknisi menampilkan slot foto sesuai kategori tiap order_item', function () {
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Foto Tampak Depan Lokasi')
        ->assertSee('Foto Cek Suhu (Indoor)');
});

it('halaman view order admin menampilkan foto per kategori', function () {
    $admin = ($this->mkAdmin)();
    $teknisi = ($this->mkTeknisi)();
    $order = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Dikerjakan]);
    $item = $order->orderItems->first();

    // dev-plan/17: Cuci AC sekarang wajib ke-6 slotnya diisi.
    $fotoKategori = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))
        ->keys()
        ->map(fn ($slot) => ['order_item_id' => $item->id, 'slot' => $slot, 'path' => "work-reports/{$slot}.jpg"])
        ->all();

    app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai cuci AC.',
        'materials' => [],
        'foto_kategori' => $fotoKategori,
    ]);

    $this->actingAs($admin)->get("/admin/orders/{$order->id}")
        ->assertOk()
        ->assertSee('Foto per Kategori')
        ->assertSee('Foto Tampak Depan Lokasi');
});
