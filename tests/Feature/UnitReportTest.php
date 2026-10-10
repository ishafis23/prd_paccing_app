<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Teknisi\LengkapiLaporan;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Models\PhotoReportTemplate;
use App\Models\User;
use App\Models\WorkReportPhoto;
use App\Services\PhotoReportTemplateService;
use App\Services\TeknisiService;
use App\Services\UnitReportService;
use App\Support\FotoLaporanSlot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->mkUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    $this->teknisi = ($this->mkUser)(RoleName::Teknisi->value);

    // Order Cuci AC dengan $n baris (1 unit per baris) bertaut ke unit AC.
    $this->buatOrder = function (int $n = 3, OrderStatus $status = OrderStatus::Dikerjakan): Order {
        $order = Order::factory()->create([
            'teknisi_id' => $this->teknisi->id,
            'status' => $status,
            'jenis_pelanggan' => CustomerJenis::Perorangan,
        ]);
        $alamat = CustomerAddress::factory()->create([
            'customer_id' => $order->customer_id,
            'nama_lokasi' => 'CK Pondok Indah',
        ]);
        $order->update(['customer_address_id' => $alamat->id]);
        $order->orderItems()->delete();

        for ($i = 1; $i <= $n; $i++) {
            $ac = CustomerAcUnit::factory()->create([
                'customer_id' => $order->customer_id,
                'customer_address_id' => $alamat->id,
                'kode_unit' => "AC-0{$i}",
                'kode_ruangan' => "Ruang {$i}",
            ]);
            OrderItem::factory()->create([
                'order_id' => $order->id,
                'kategori' => ServiceType::CuciAc,
                'nama_layanan' => 'Cuci Standar',
                'customer_ac_unit_id' => $ac->id,
                'jumlah' => 1,
            ]);
        }

        return $order->fresh('orderItems');
    };

    $this->dataLengkap = fn (array $ubah = []): array => array_merge([
        'lokasi_label' => 'CK Pondok Indah',
        'posisi' => 'Ruang Server',
        'jenis_pekerjaan' => 'Cuci Standar',
        'bagian' => 'indoor',
        'suhu' => '16.5',
        'rpm' => '1200',
        'kondisi' => 'normal',
        'catatan_kondisi' => '',
    ], $ubah);
});

it('migrasi: foto lama tetap ada dengan unit_no = 1 dan seed field_set Cuci AC terisi', function () {
    $order = ($this->buatOrder)(1);
    $item = $order->orderItems->first();
    $laporan = app(TeknisiService::class)->submitLaporan($order, $this->teknisi, [
        'catatan' => 'ok', 'materials' => [],
        'foto_kategori' => [['order_item_id' => $item->id, 'slot' => 'foto_tampak_depan_lokasi', 'path' => 'work-reports/a.jpg']],
    ]);

    $foto = WorkReportPhoto::query()->where('work_report_id', $laporan->id)->first();
    expect($foto->unit_no)->toBe(1)->and($foto->order_unit_report_id)->toBeNull();

    $indoor = PhotoReportTemplate::query()->where('kode_slot', 'foto_cek_suhu_indoor')->value('field_set');
    $outdoor = PhotoReportTemplate::query()->where('kode_slot', 'foto_area_unit_outdoor')->value('field_set');
    $umum = PhotoReportTemplate::query()->where('kode_slot', 'foto_tampak_depan_lokasi')->value('field_set');
    expect([$indoor, $outdoor, $umum])->toBe(['indoor_lengkap', 'outdoor', 'bebas']);
});

it('order 3 unit membentuk 3 baris order_unit_reports dengan prefill benar, idempotent', function () {
    $order = ($this->buatOrder)(3);

    $units = app(UnitReportService::class)->siapkan($order);
    app(UnitReportService::class)->siapkan($order);

    expect(OrderUnitReport::where('order_id', $order->id)->count())->toBe(3)
        ->and($units->pluck('unit_no')->all())->toBe([1, 2, 3])
        ->and($units->pluck('lokasi_label')->unique()->all())->toBe(['CK Pondok Indah'])
        ->and($units->pluck('posisi')->all())->toBe(['Ruang 1', 'Ruang 2', 'Ruang 3'])
        ->and($units->pluck('jenis_pekerjaan')->unique()->all())->toBe(['Cuci Standar'])
        ->and($units->pluck('kondisi')->filter()->all())->toBe([])
        ->and($units->pluck('customer_ac_unit_id')->filter()->count())->toBe(3);
});

it('satu baris jumlah=3 juga membentuk 3 unit berurutan, penambahan layanan melanjutkan nomor', function () {
    $order = ($this->buatOrder)(1);
    $order->orderItems->first()->update(['jumlah' => 3]);

    expect(app(UnitReportService::class)->siapkan($order->fresh())->pluck('unit_no')->all())->toBe([1, 2, 3]);

    OrderItem::factory()->create(['order_id' => $order->id, 'kategori' => ServiceType::CuciAc, 'nama_layanan' => 'Cuci Tambahan', 'jumlah' => 1]);
    $lagi = app(UnitReportService::class)->siapkan($order->fresh());

    expect($lagi->pluck('unit_no')->all())->toBe([1, 2, 3, 4]);
});

it('kondisi tidak_normal tanpa catatan ditolak (service & Livewire), tersimpan bila ada catatan', function () {
    $order = ($this->buatOrder)(1);
    $unit = app(UnitReportService::class)->siapkan($order)->first();

    expect(fn () => app(UnitReportService::class)->simpan($unit, ($this->dataLengkap)(['kondisi' => 'tidak_normal']), $this->teknisi))
        ->toThrow(ValidationException::class);
    expect($unit->fresh()->kondisi)->toBeNull();

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set("unitForm.{$unit->id}", ($this->dataLengkap)(['kondisi' => 'tidak_normal', 'catatan_kondisi' => '']))
        ->call('simpanUnit', $unit->id)
        ->assertHasErrors(["unitForm.{$unit->id}.catatan_kondisi"]);
    expect($unit->fresh()->kondisi)->toBeNull();

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set("unitForm.{$unit->id}", ($this->dataLengkap)(['kondisi' => 'tidak_normal', 'catatan_kondisi' => 'Bocor freon']))
        ->call('simpanUnit', $unit->id)
        ->assertHasNoErrors();
    expect($unit->fresh())->kondisi->toBe('tidak_normal')->catatan_kondisi->toBe('Bocor freon');
});

it('suhu wajib untuk slot indoor_lengkap, RPM opsional; outdoor/bebas membuang suhu & RPM', function () {
    $order = ($this->buatOrder)(1);
    $unit = app(UnitReportService::class)->siapkan($order)->first();
    $service = app(UnitReportService::class);

    expect(fn () => $service->simpan($unit, ($this->dataLengkap)(['suhu' => '']), $this->teknisi))
        ->toThrow(ValidationException::class);

    $service->simpan($unit, ($this->dataLengkap)(['rpm' => '']), $this->teknisi);
    expect($unit->fresh())->rpm->toBeNull()->and((float) $unit->fresh()->suhu)->toBe(16.5);

    // Admin mengubah semua slot Cuci AC jadi outdoor -> suhu/RPM tidak relevan lagi.
    PhotoReportTemplate::query()->where('kategori', 'cuci_ac')->update(['field_set' => 'outdoor']);
    app(PhotoReportTemplateService::class)->lupakanCache('cuci_ac');

    $service->simpan($unit->fresh(), ($this->dataLengkap)(['suhu' => '', 'rpm' => '900']), $this->teknisi);
    expect($unit->fresh())->suhu->toBeNull()->rpm->toBeNull();
});

it('fotoWajibKurang memuat keterangan yang kurang & tidak memuat yang sudah lengkap (end-to-end Livewire)', function () {
    $order = ($this->buatOrder)(2);
    $item = $order->orderItems->first();
    $semuaSlot = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))->keys()
        ->flatMap(fn ($slot) => $order->orderItems->map(fn ($i) => ['order_item_id' => $i->id, 'slot' => $slot, 'path' => "work-reports/{$i->id}-{$slot}.jpg"]))
        ->all();

    // Buka halaman saat Dikerjakan -> baris unit tersiap; lalu laporan disubmit dgn semua foto.
    $komponen = Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order]);
    expect(OrderUnitReport::where('order_id', $order->id)->count())->toBe(2);

    app(TeknisiService::class)->submitLaporan($order->fresh('orderItems'), $this->teknisi, [
        'catatan' => 'Selesai', 'materials' => [], 'foto_kategori' => $semuaSlot,
    ]);

    $service = app(TeknisiService::class);
    $kurang = collect($service->fotoWajibKurang($order->fresh('orderItems')));
    expect($kurang->where('jenis', 'foto'))->toHaveCount(0)
        ->and($kurang->where('jenis', 'keterangan'))->toHaveCount(2)
        ->and($kurang->where('jenis', 'keterangan')->pluck('label')->all())->toBe(['Keterangan Unit 1', 'Keterangan Unit 2'])
        ->and($service->perluDilengkapi($order->fresh()))->toBeTrue();

    $unit1 = OrderUnitReport::where('order_id', $order->id)->where('unit_no', 1)->first();

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order->fresh()])
        ->set("unitForm.{$unit1->id}", ($this->dataLengkap)())
        ->call('simpanUnit', $unit1->id)
        ->assertHasNoErrors();

    $setelah = collect($service->fotoWajibKurang($order->fresh('orderItems')));
    expect($unit1->fresh()->kondisi)->toBe('normal')
        ->and($setelah->where('jenis', 'keterangan'))->toHaveCount(1)
        ->and($setelah->where('jenis', 'keterangan')->first()['unit_no'])->toBe(2);

    $unit2 = OrderUnitReport::where('order_id', $order->id)->where('unit_no', 2)->first();
    $service2 = app(UnitReportService::class);
    $service2->simpan($unit2, ($this->dataLengkap)(['kondisi' => 'tidak_normal', 'catatan_kondisi' => 'Berisik']), $this->teknisi);

    expect($service->fotoWajibKurang($order->fresh('orderItems')))->toBe([])
        ->and($service->perluDilengkapi($order->fresh()))->toBeFalse();
});

it('berangkat diblokir selama keterangan unit kurang, lolos setelah dilengkapi', function () {
    $order = ($this->buatOrder)(1);
    $item = $order->orderItems->first();
    $unit = app(UnitReportService::class)->siapkan($order)->first();
    $semua = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))->keys()
        ->map(fn ($slot) => ['order_item_id' => $item->id, 'slot' => $slot, 'path' => "work-reports/{$slot}.jpg"])->all();
    app(TeknisiService::class)->submitLaporan($order, $this->teknisi, ['catatan' => 'ok', 'materials' => [], 'foto_kategori' => $semua]);

    $baru = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal]);

    expect(fn () => app(TeknisiService::class)->berangkat($baru, $this->teknisi))
        ->toThrow(BusinessRuleException::class, 'Keterangan Unit 1');

    app(UnitReportService::class)->simpan($unit, ($this->dataLengkap)(), $this->teknisi);

    expect(app(TeknisiService::class)->berangkat($baru->fresh(), $this->teknisi)->status)->toBe(OrderStatus::MenujuLokasi);
});

it('order lama tanpa data unit tidak terblokir & tidak otomatis dibuatkan unit saat sudah selesai', function () {
    $order = ($this->buatOrder)(2, OrderStatus::Selesai);
    $semua = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))->keys()
        ->flatMap(fn ($slot) => $order->orderItems->map(fn ($i) => ['order_item_id' => $i->id, 'slot' => $slot, 'path' => "work-reports/{$i->id}{$slot}.jpg"]))
        ->all();
    $laporan = \App\Models\WorkReport::factory()->create(['order_id' => $order->id, 'teknisi_id' => $this->teknisi->id]);
    foreach ($semua as $baris) {
        WorkReportPhoto::create(['work_report_id' => $laporan->id, 'urutan' => 0] + $baris);
    }

    // Teknisi membuka detail order lama yang sudah Selesai -> tetap tanpa data unit.
    Livewire::actingAs($this->teknisi)->test(OrderDetail::class, ['order' => $order])->assertOk();

    expect(OrderUnitReport::where('order_id', $order->id)->count())->toBe(0)
        ->and(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->toBe([])
        ->and(app(TeknisiService::class)->perluDilengkapi($order->fresh()))->toBeFalse();

    $baru = Order::factory()->create(['teknisi_id' => $this->teknisi->id, 'status' => OrderStatus::Terjadwal]);
    expect(app(TeknisiService::class)->berangkat($baru, $this->teknisi)->status)->toBe(OrderStatus::MenujuLokasi);
});

it('salin posisi dari unit sebelumnya mengisi form unit berikutnya', function () {
    $order = ($this->buatOrder)(2);
    $units = app(UnitReportService::class)->siapkan($order);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->set("unitForm.{$units[0]->id}.posisi", 'Lobi Lantai 2')
        ->call('salinPosisiSebelumnya', $units[1]->id)
        ->assertSet("unitForm.{$units[1]->id}.posisi", 'Lobi Lantai 2');
});

it('accordion Keterangan Unit tampil per unit; suhu/RPM hanya untuk indoor_lengkap; deep-link ?unit=N membuka unit', function () {
    $order = ($this->buatOrder)(3);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Keterangan Unit')
        ->assertSee('Unit 1')->assertSee('Unit 2')->assertSee('Unit 3')
        ->assertDontSee('Simpan Unit 2')
        ->call('toggleUnit', 2)
        ->assertSee('Simpan Unit 2')
        ->assertSee('Salin posisi dari unit sebelumnya')
        ->assertSee('Suhu (°C)');

    Livewire::withQueryParams(['unit' => 3])->actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->assertSee('Simpan Unit 3');

    PhotoReportTemplate::query()->where('kategori', 'cuci_ac')->update(['field_set' => 'outdoor']);
    app(PhotoReportTemplateService::class)->lupakanCache('cuci_ac');

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order])
        ->call('toggleUnit', 1)
        ->assertSee('Simpan Unit 1')
        ->assertDontSee('Suhu (°C)');
});

it('teknisi lain tidak boleh mengisi keterangan unit order orang lain', function () {
    $order = ($this->buatOrder)(1);
    $unit = app(UnitReportService::class)->siapkan($order)->first();
    $lain = ($this->mkUser)(RoleName::Teknisi->value);

    expect(fn () => app(UnitReportService::class)->simpan($unit, ($this->dataLengkap)(), $lain))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('halaman Lengkapi Laporan: 200 untuk teknisi, 403 untuk non-teknisi, redirect untuk guest', function () {
    $this->get('/teknisi/lengkapi-laporan')->assertRedirect('/login');

    foreach ([RoleName::Admin, RoleName::Owner, RoleName::Finance] as $role) {
        $this->actingAs(($this->mkUser)($role->value))->get('/teknisi/lengkapi-laporan')->assertForbidden();
    }

    $this->actingAs($this->teknisi)->get('/teknisi/lengkapi-laporan')->assertOk()->assertSee('Semua laporan sudah lengkap');
});

it('Lengkapi Laporan menampilkan order yang kurang dengan tautan ke unit pertama yang kurang', function () {
    $order = ($this->buatOrder)(2);
    $item = $order->orderItems->first();
    app(UnitReportService::class)->siapkan($order);
    $semua = collect(FotoLaporanSlot::untuk(ServiceType::CuciAc))->keys()
        ->flatMap(fn ($slot) => $order->orderItems->map(fn ($i) => ['order_item_id' => $i->id, 'slot' => $slot, 'path' => "work-reports/{$i->id}{$slot}.jpg"]))->all();
    app(TeknisiService::class)->submitLaporan($order, $this->teknisi, ['catatan' => 'ok', 'materials' => [], 'foto_kategori' => $semua]);

    Livewire::actingAs($this->teknisi)
        ->test(LengkapiLaporan::class)
        ->assertSee($order->customer->nama)
        ->assertSee('2 keterangan unit kurang')
        ->assertSee('?unit=1#unit-1', false);

    $this->actingAs($this->teknisi)->get('/teknisi')->assertOk()->assertSee('Lengkapi');
    $this->actingAs($this->teknisi)->get('/teknisi/riwayat')->assertOk()->assertSee('Perlu dilengkapi');
});

it('admin mengatur field_set template lewat service; nilai tidak valid ditolak', function () {
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $template = PhotoReportTemplate::query()->where('kode_slot', 'foto_tampak_depan_lokasi')->first();
    $service = app(PhotoReportTemplateService::class);

    $service->perbarui($template, ['field_set' => 'outdoor'], $admin);
    expect($template->fresh()->field_set)->toBe('outdoor');

    expect(fn () => $service->perbarui($template->fresh(), ['field_set' => 'ngawur'], $admin))
        ->toThrow(BusinessRuleException::class);
});

it('admin boleh menambal keterangan unit & foto susulan; hanya Owner/Admin/Finance', function () {
    $order = ($this->buatOrder)(1);
    $item = $order->orderItems->first();
    $unit = app(UnitReportService::class)->siapkan($order)->first();
    $admin = ($this->mkUser)(RoleName::Admin->value);
    $finance = ($this->mkUser)(RoleName::Finance->value);

    app(UnitReportService::class)->simpan($unit, ($this->dataLengkap)(['posisi' => 'Dapur']), $admin);
    expect($unit->fresh()->posisi)->toBe('Dapur');

    // Tanpa laporan -> foto susulan ditolak dengan pesan jelas.
    expect(fn () => app(UnitReportService::class)->tambahFotoSusulan($order, $admin, $item->id, 'foto_tampak_depan_lokasi', 'work-reports/x.jpg'))
        ->toThrow(BusinessRuleException::class, 'Belum ada laporan');

    app(TeknisiService::class)->submitLaporan($order, $this->teknisi, ['catatan' => 'ok', 'materials' => [], 'foto_kategori' => []]);
    $foto = app(UnitReportService::class)->tambahFotoSusulan($order->fresh(), $finance, $item->id, 'foto_tampak_depan_lokasi', 'work-reports/x.jpg');

    expect($foto->unit_no)->toBe(1)->and($foto->order_unit_report_id)->toBe($unit->id);

    expect(fn () => app(UnitReportService::class)->tambahFotoSusulan($order->fresh(), $this->teknisi, $item->id, 'foto_tampak_depan_lokasi', 'work-reports/y.jpg'))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
});

it('tab Laporan Pengerjaan di halaman lihat Order merender untuk admin', function () {
    $order = ($this->buatOrder)(2);
    app(UnitReportService::class)->siapkan($order);
    $admin = ($this->mkUser)(RoleName::Admin->value);

    $this->actingAs($admin)
        ->get(\App\Filament\Resources\OrderResource::getUrl('view', ['record' => $order]))
        ->assertOk()
        ->assertSee('Laporan Pengerjaan')
        ->assertSee('Edit Keterangan Unit');
});
