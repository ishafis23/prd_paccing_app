<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\PhotoReportTemplateResource;
use App\Filament\Resources\PhotoReportTemplateResource\Pages\ListPhotoReportTemplates;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PhotoReportTemplate;
use App\Models\User;
use App\Services\PhotoReportTemplateService;
use App\Services\TeknisiService;
use App\Support\FotoLaporanSlot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->admin = ($this->mkUser)(RoleName::Admin->value);
});

// --- PhotoReportTemplateService ---------------------------------------------

it('untukKategori balikin hanya slot aktif, terurut sesuai urutan', function () {
    $service = app(PhotoReportTemplateService::class);

    $slot = $service->untukKategori(ServiceType::CuciAc);

    expect($slot)->toBe([
        'foto_tampak_depan_lokasi' => 'Foto Tampak Depan Lokasi',
        'foto_sesudah_cuci_indoor' => 'Foto Sesudah Cuci (Indoor)',
        'foto_sesudah_cuci_outdoor' => 'Foto Sesudah Cuci (Outdoor)',
        'foto_area_unit_indoor' => 'Foto Area Unit (Indoor)',
        'foto_area_unit_outdoor' => 'Foto Area Unit (Outdoor)',
        'foto_cek_suhu_indoor' => 'Foto Cek Suhu (Indoor)',
    ]);
});

it('untukKategori tidak menampilkan slot yang dinonaktifkan', function () {
    $service = app(PhotoReportTemplateService::class);
    $row = PhotoReportTemplate::query()->where('kategori', ServiceType::CuciAc->value)->first();

    $row->update(['aktif' => false]);
    $service->lupakanCache(ServiceType::CuciAc->value);

    expect($service->untukKategori(ServiceType::CuciAc))->not->toHaveKey($row->kode_slot);
});

it('untukKategori(null) balikin array kosong', function () {
    expect(app(PhotoReportTemplateService::class)->untukKategori(null))->toBe([]);
});

it('daftarWajib hanya balikin slot aktif & wajib', function () {
    $service = app(PhotoReportTemplateService::class);

    expect($service->daftarWajib(ServiceType::CuciAc))->toHaveCount(6)
        ->and($service->daftarWajib(ServiceType::ServiceAc))->toBe([]);
});

it('tambah membuat template baru dgn kode_slot slug otomatis', function () {
    $template = app(PhotoReportTemplateService::class)->tambah([
        'kategori' => ServiceType::Bongkar->value,
        'label' => 'Foto Nomor Seri Unit',
        'urutan' => 5,
        'wajib' => true,
    ], $this->admin);

    expect($template->kode_slot)->toBe('foto_nomor_seri_unit')
        ->and($template->label)->toBe('Foto Nomor Seri Unit')
        ->and($template->wajib)->toBeTrue()
        ->and($template->aktif)->toBeTrue();

    expect(app(PhotoReportTemplateService::class)->untukKategori(ServiceType::Bongkar))
        ->toHaveKey('foto_nomor_seri_unit');
});

it('tambah menolak label kosong & kategori kosong', function () {
    expect(fn () => app(PhotoReportTemplateService::class)->tambah(['kategori' => ServiceType::Bongkar->value], $this->admin))
        ->toThrow(BusinessRuleException::class, 'Label foto wajib');

    expect(fn () => app(PhotoReportTemplateService::class)->tambah(['label' => 'Foto X'], $this->admin))
        ->toThrow(BusinessRuleException::class, 'Kategori wajib');
});

it('tambah menolak slug dobel di kategori yang sama, tapi boleh di kategori beda', function () {
    app(PhotoReportTemplateService::class)->tambah([
        'kategori' => ServiceType::Bongkar->value,
        'label' => 'Foto Tes',
    ], $this->admin);

    expect(fn () => app(PhotoReportTemplateService::class)->tambah([
        'kategori' => ServiceType::Bongkar->value,
        'label' => 'Foto Tes',
    ], $this->admin))->toThrow(BusinessRuleException::class, 'Sudah ada slot');

    // Kategori beda, slug sama -> boleh.
    $lain = app(PhotoReportTemplateService::class)->tambah([
        'kategori' => ServiceType::Relokasi->value,
        'label' => 'Foto Tes',
    ], $this->admin);
    expect($lain->kode_slot)->toBe('foto_tes');
});

it('tambah/toggleAktif/perbarui hanya boleh Admin/Owner', function (string $role) {
    $user = ($this->mkUser)($role);

    app(PhotoReportTemplateService::class)->tambah(['kategori' => ServiceType::Bongkar->value, 'label' => 'X'], $user);
})->with(['finance' => RoleName::Finance->value, 'teknisi' => RoleName::Teknisi->value])
    ->throws(AuthorizationException::class);

it('toggleAktif membalik nilai aktif', function () {
    $row = PhotoReportTemplate::query()->where('kategori', ServiceType::Bongkar->value)->first();
    $semula = $row->aktif;

    $hasil = app(PhotoReportTemplateService::class)->toggleAktif($row, $this->admin);

    expect($hasil->aktif)->toBe(! $semula);
});

it('perbarui mengubah label/urutan/wajib, mengabaikan kode_slot', function () {
    $row = PhotoReportTemplate::query()->where('kategori', ServiceType::Bongkar->value)->first();
    $kodeAsli = $row->kode_slot;

    $hasil = app(PhotoReportTemplateService::class)->perbarui($row, [
        'label' => 'Label Baru',
        'urutan' => 9,
        'wajib' => true,
        'kode_slot' => 'coba_ubah',
    ], $this->admin);

    expect($hasil->label)->toBe('Label Baru')
        ->and($hasil->urutan)->toBe(9)
        ->and($hasil->wajib)->toBeTrue()
        ->and($hasil->kode_slot)->toBe($kodeAsli);
});

// --- FotoLaporanSlot (delegasi) ---------------------------------------------

it('FotoLaporanSlot::untuk delegasi ke service & fallback utk kategori kosong template', function () {
    expect(FotoLaporanSlot::untuk(ServiceType::CuciAc))->toHaveCount(6)
        ->and(FotoLaporanSlot::untuk(ServiceType::PengadaanAc))->toBe(['sebelum' => 'Sebelum', 'sesudah' => 'Sesudah'])
        ->and(FotoLaporanSlot::untuk(null))->toBe(['sebelum' => 'Sebelum', 'sesudah' => 'Sesudah']);
});

it('FotoLaporanSlot::wajibUntuk cocok dgn seed (Cuci AC wajib semua, lainnya belum)', function () {
    expect(FotoLaporanSlot::wajibUntuk(ServiceType::CuciAc))->toHaveCount(6)
        ->and(FotoLaporanSlot::wajibUntuk(ServiceType::ServiceAc))->toBe([])
        ->and(FotoLaporanSlot::wajibUntuk(null))->toBe([]);
});

// --- TeknisiService::submitLaporan (validasi wajib) -------------------------

function buatOrderDenganKategori(User $teknisi, ServiceType $kategori): Order
{
    $order = Order::factory()->create([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Dikerjakan,
        'jenis_pelanggan' => CustomerJenis::Perorangan,
    ]);

    $order->orderItems()->first()?->delete();
    OrderItem::factory()->create([
        'order_id' => $order->id,
        'kategori' => $kategori,
        'nama_layanan' => $kategori->value,
    ]);

    return $order->fresh('orderItems');
}

it('submitLaporan TETAP berhasil meski foto wajib (Cuci AC) belum lengkap — direvisi 18 Sep', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $item = $order->orderItems->first();

    $report = app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Sudah dicuci.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $item->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg'],
        ],
    ]);

    expect($report)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Selesai);
});

it('fotoWajibKurang balikin 5 slot yang belum diisi (dari 6) setelah laporan disubmit', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $item = $order->orderItems->first();

    app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Sudah dicuci.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $item->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg'],
        ],
    ]);

    $kurang = app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems'));

    expect($kurang)->toHaveCount(5)
        ->and(collect($kurang)->pluck('kode_slot')->all())->not->toContain('foto_tampak_depan_lokasi');
});

it('lengkapiFotoWajib menambah foto ke laporan yang sudah tersubmit', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $item = $order->orderItems->first();

    app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Sudah dicuci.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $item->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg'],
        ],
    ]);

    $sisaKurang = collect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))
        ->map(fn ($k) => ['order_item_id' => $item->id, 'slot' => $k['kode_slot'], 'path' => "work-reports/{$k['kode_slot']}.jpg"])
        ->all();

    app(TeknisiService::class)->lengkapiFotoWajib($order->fresh(), $teknisi, $sisaKurang);

    expect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->toBe([]);
});

it('lengkapiFotoWajib ditolak kalau laporan belum pernah disubmit', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);

    app(TeknisiService::class)->lengkapiFotoWajib($order, $teknisi, []);
})->throws(BusinessRuleException::class, 'belum disubmit');

it('berangkat DITOLAK kalau ada order lain (belum ditutup) yang foto wajibnya belum lengkap', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $ordersLama = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $itemLama = $ordersLama->orderItems->first();

    app(TeknisiService::class)->submitLaporan($ordersLama, $teknisi, [
        'catatan' => 'Sudah dicuci.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $itemLama->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg'],
        ],
    ]);

    $orderBaru = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Terjadwal]);

    app(TeknisiService::class)->berangkat($orderBaru, $teknisi);
})->throws(BusinessRuleException::class, 'Lengkapi dulu foto wajib');

it('berangkat BOLEH setelah foto wajib order sebelumnya dilengkapi', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $ordersLama = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $itemLama = $ordersLama->orderItems->first();

    $fotoKategori = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))
        ->keys()
        ->map(fn ($slot) => ['order_item_id' => $itemLama->id, 'slot' => $slot, 'path' => "work-reports/{$slot}.jpg"])
        ->all();

    app(TeknisiService::class)->submitLaporan($ordersLama, $teknisi, [
        'catatan' => 'Sudah dicuci lengkap.',
        'materials' => [],
        'foto_kategori' => $fotoKategori,
    ]);

    $orderBaru = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Terjadwal]);

    $hasil = app(TeknisiService::class)->berangkat($orderBaru, $teknisi);

    expect($hasil->status)->toBe(OrderStatus::MenujuLokasi);
});

it('berangkat BOLEH meski foto wajib order sebelumnya kurang, asalkan order itu sudah ditutup (B32)', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $ordersLama = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $itemLama = $ordersLama->orderItems->first();

    app(TeknisiService::class)->submitLaporan($ordersLama, $teknisi, [
        'catatan' => 'Sudah dicuci.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $itemLama->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg'],
        ],
    ]);
    $ordersLama->fresh()->update(['metode_dipilih' => PaymentMethod::Cash, 'ditutup_pada' => now()]);

    $orderBaru = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Terjadwal]);

    $hasil = app(TeknisiService::class)->berangkat($orderBaru, $teknisi);

    expect($hasil->status)->toBe(OrderStatus::MenujuLokasi);
});

it('submitLaporan berhasil kalau semua foto wajib (Cuci AC) lengkap', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $item = $order->orderItems->first();

    $fotoKategori = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))
        ->keys()
        ->map(fn ($slot) => ['order_item_id' => $item->id, 'slot' => $slot, 'path' => "work-reports/{$slot}.jpg"])
        ->all();

    $report = app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Sudah dicuci lengkap.',
        'materials' => [],
        'foto_kategori' => $fotoKategori,
    ]);

    expect($report)->not->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Selesai);
});

it('submitLaporan tetap longgar (tidak ditolak) utk kategori yang belum ditandai wajib', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::ServiceAc);

    $report = app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Selesai service, tanpa foto.',
        'materials' => [],
        'foto_kategori' => [],
    ]);

    expect($report)->not->toBeNull();
});

// --- Livewire: OrderDetail "Lengkapi Foto Wajib" ----------------------------

it('OrderDetail menampilkan & memproses form Lengkapi Foto Wajib', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $item = $order->orderItems->first();

    app(TeknisiService::class)->submitLaporan($order, $teknisi, [
        'catatan' => 'Sudah dicuci.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $item->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg'],
        ],
    ]);

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order->fresh()])
        ->assertSee('Lengkapi Foto Wajib')
        ->assertSee('Foto Sesudah Cuci (Indoor)')
        ->set("fotoLengkapi.{$item->id}.foto_sesudah_cuci_indoor", UploadedFile::fake()->image('a.jpg'))
        ->set("fotoLengkapi.{$item->id}.foto_sesudah_cuci_outdoor", UploadedFile::fake()->image('b.jpg'))
        ->set("fotoLengkapi.{$item->id}.foto_area_unit_indoor", UploadedFile::fake()->image('c.jpg'))
        ->set("fotoLengkapi.{$item->id}.foto_area_unit_outdoor", UploadedFile::fake()->image('d.jpg'))
        ->set("fotoLengkapi.{$item->id}.foto_cek_suhu_indoor", UploadedFile::fake()->image('e.jpg'))
        ->call('lengkapiFotoWajib')
        ->assertOk();

    expect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->toBe([]);
});

it('notifikasi sukses submitLaporan menyebutkan persis foto wajib yang masih kurang', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $item = $order->orderItems->first();

    Livewire::actingAs($teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set('catatan', 'Sudah dicuci.')
        ->set("fotoKategori.{$item->id}.foto_tampak_depan_lokasi", UploadedFile::fake()->image('a.jpg'))
        ->call('submitLaporan')
        ->assertSee('Foto Cek Suhu (Indoor)')
        ->assertSee('berikutnya');
});

it('notifikasi sukses submitLaporan tidak sebut kekurangan kalau semua foto wajib sudah lengkap', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $order = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $item = $order->orderItems->first();

    $component = Livewire::actingAs($teknisi)->test(OrderDetail::class, ['order' => $order]);
    $component->set('catatan', 'Sudah dicuci lengkap.');
    foreach (array_keys(FotoLaporanSlot::untuk(ServiceType::CuciAc)) as $slot) {
        $component->set("fotoKategori.{$item->id}.{$slot}", UploadedFile::fake()->image("{$slot}.jpg"));
    }
    $component->call('submitLaporan')
        ->assertSee('Laporan berhasil disubmit. Semua foto wajib sudah lengkap.');
});

it('pesan gagal berangkat menyebutkan persis foto wajib yang masih kurang', function () {
    $teknisi = ($this->mkUser)(RoleName::Teknisi->value);
    $ordersLama = buatOrderDenganKategori($teknisi, ServiceType::CuciAc);
    $itemLama = $ordersLama->orderItems->first();

    app(TeknisiService::class)->submitLaporan($ordersLama, $teknisi, [
        'catatan' => 'Sudah dicuci.',
        'materials' => [],
        'foto_kategori' => [
            ['order_item_id' => $itemLama->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg'],
        ],
    ]);

    $orderBaru = Order::factory()->create(['teknisi_id' => $teknisi->id, 'status' => OrderStatus::Terjadwal]);

    expect(fn () => app(TeknisiService::class)->berangkat($orderBaru, $teknisi))
        ->toThrow(BusinessRuleException::class, 'Foto Cek Suhu (Indoor)');
});

// --- Filament: PhotoReportTemplateResource -----------------------------------

it('halaman Template Foto Laporan bisa diakses Owner/Admin/Finance, bukan Teknisi', function (string $role, bool $boleh) {
    $user = ($this->mkUser)($role);

    $response = $this->actingAs($user)->get(PhotoReportTemplateResource::getUrl());

    $boleh ? $response->assertOk() : $response->assertForbidden();
})->with([
    'owner' => [RoleName::Owner->value, true],
    'admin' => [RoleName::Admin->value, true],
    'finance' => [RoleName::Finance->value, true],
    'teknisi' => [RoleName::Teknisi->value, false],
]);

it('aksi Tambah Item membuat PhotoReportTemplate baru lewat Livewire', function () {
    Livewire::actingAs($this->admin)
        ->test(ListPhotoReportTemplates::class)
        ->callTableAction('tambahItem', data: [
            'kategori' => ServiceType::Bongkar->value,
            'label' => 'Foto Tambahan',
            'urutan' => 10,
            'wajib' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect(PhotoReportTemplate::query()->where('kode_slot', 'foto_tambahan')->exists())->toBeTrue();
});

it('EditAction mengubah label tapi tidak bisa mengubah kode_slot', function () {
    $row = PhotoReportTemplate::query()->where('kategori', ServiceType::Bongkar->value)->first();
    $kodeAsli = $row->kode_slot;

    Livewire::actingAs($this->admin)
        ->test(ListPhotoReportTemplates::class)
        ->callTableAction('edit', $row, data: ['label' => 'Label Diubah', 'urutan' => 1, 'wajib' => false])
        ->assertHasNoTableActionErrors();

    expect($row->fresh()->label)->toBe('Label Diubah')
        ->and($row->fresh()->kode_slot)->toBe($kodeAsli);
});
