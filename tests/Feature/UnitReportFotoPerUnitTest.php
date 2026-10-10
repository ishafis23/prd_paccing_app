<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Livewire\Teknisi\OrderDetail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Models\User;
use App\Models\WorkReportPhoto;
use App\Services\TeknisiService;
use App\Services\UnitReportService;
use App\Support\FotoLaporanSlot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');

    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);

    // Satu baris Cuci AC dengan jumlah=3 (bentuk order "60rb × 3").
    $this->buatOrder = function (int $jumlah = 3, OrderStatus $status = OrderStatus::Dikerjakan): Order {
        $order = Order::factory()->create([
            'teknisi_id' => $this->teknisi->id,
            'status' => $status,
            'jenis_pelanggan' => CustomerJenis::Perorangan,
        ]);
        $order->orderItems()->delete();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'kategori' => ServiceType::CuciAc,
            'nama_layanan' => 'Cuci AC',
            'jumlah' => $jumlah,
        ]);

        return $order->fresh('orderItems');
    };

    $this->wajib = array_keys(FotoLaporanSlot::wajibUntuk(ServiceType::CuciAc));
});

it('foto yang diunggah pada Unit 2 menempel ke unit 2 saja (unit_no = urutan dalam baris)', function () {
    $order = ($this->buatOrder)(3);
    $units = app(UnitReportService::class)->siapkan($order);
    $slot = $this->wajib[0];

    app(TeknisiService::class)->submitLaporan($order->fresh('orderItems'), $this->teknisi, ['catatan' => 'ok', 'materials' => []]);

    $foto = app(UnitReportService::class)->simpanFotoUnit(
        $units[1], $slot, UploadedFile::fake()->image('a.jpg'), $this->teknisi
    );

    expect($foto->order_unit_report_id)->toBe($units[1]->id)
        ->and($foto->unit_no)->toBe(2)
        ->and(WorkReportPhoto::where('order_unit_report_id', $units[0]->id)->count())->toBe(0)
        ->and(WorkReportPhoto::where('order_unit_report_id', $units[2]->id)->count())->toBe(0);
    Storage::disk('public')->assertExists($foto->path);
});

it('mengganti foto pada slot yang sama menghapus file lama', function () {
    $order = ($this->buatOrder)(3);
    $units = app(UnitReportService::class)->siapkan($order);
    $slot = $this->wajib[0];
    app(TeknisiService::class)->submitLaporan($order->fresh('orderItems'), $this->teknisi, ['catatan' => 'ok', 'materials' => []]);

    $service = app(UnitReportService::class);
    $lama = $service->simpanFotoUnit($units[1], $slot, UploadedFile::fake()->image('lama.jpg'), $this->teknisi);
    $baru = $service->simpanFotoUnit($units[1], $slot, UploadedFile::fake()->image('baru.jpg'), $this->teknisi);

    expect(WorkReportPhoto::where('order_unit_report_id', $units[1]->id)->where('slot', $slot)->count())->toBe(1);
    Storage::disk('public')->assertMissing($lama->path);
    Storage::disk('public')->assertExists($baru->path);
});

it('fotoWajibKurang menyebut unit yang fotonya kurang pada baris jumlah=3', function () {
    $order = ($this->buatOrder)(3);
    $units = app(UnitReportService::class)->siapkan($order);
    app(TeknisiService::class)->submitLaporan($order->fresh('orderItems'), $this->teknisi, ['catatan' => 'ok', 'materials' => []]);

    $service = app(UnitReportService::class);
    foreach ($this->wajib as $slot) {
        $service->simpanFotoUnit($units[0], $slot, 'work-reports/u1-'.$slot.'.jpg', $this->teknisi);
        $service->simpanFotoUnit($units[2], $slot, 'work-reports/u3-'.$slot.'.jpg', $this->teknisi);
    }

    $kurang = collect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->where('jenis', 'foto');

    expect($kurang)->toHaveCount(count($this->wajib))
        ->and($kurang->pluck('unit_report_id')->unique()->all())->toBe([$units[1]->id])
        ->and($kurang->first()['label'])->toStartWith('Cuci AC · Unit 2 · ');

    // Unit 2 dilengkapi -> tidak ada foto yang kurang lagi.
    foreach ($this->wajib as $slot) {
        $service->simpanFotoUnit($units[1], $slot, 'work-reports/u2-'.$slot.'.jpg', $this->teknisi);
    }

    expect(collect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->where('jenis', 'foto'))->toHaveCount(0);
});

it('foto lama tanpa order_unit_report_id tetap tampil di unit pertama dan dihitung milik unit pertama', function () {
    $order = ($this->buatOrder)(3);
    $item = $order->orderItems->first();
    $units = app(UnitReportService::class)->siapkan($order);
    $slot = $this->wajib[0];

    $laporan = app(TeknisiService::class)->submitLaporan($order->fresh('orderItems'), $this->teknisi, [
        'catatan' => 'ok', 'materials' => [],
        'foto_kategori' => [['order_item_id' => $item->id, 'slot' => $slot, 'path' => 'work-reports/lama.jpg']],
    ]);
    WorkReportPhoto::where('work_report_id', $laporan->id)->update(['order_unit_report_id' => null]);

    $kurang = collect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->where('jenis', 'foto');
    expect($kurang->where('unit_report_id', $units[0]->id)->pluck('kode_slot')->all())->not->toContain($slot)
        ->and($kurang->where('unit_report_id', $units[1]->id)->pluck('kode_slot')->all())->toContain($slot);

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order->fresh()])
        ->call('bukaUnit', 1)
        ->assertSeeHtml('storage/work-reports/lama.jpg');
});

it('baris jumlah=1 tetap per baris: unggahan baris tidak disembunyikan & aturan per baris×slot', function () {
    $order = ($this->buatOrder)(1);
    app(UnitReportService::class)->siapkan($order);
    app(TeknisiService::class)->submitLaporan($order->fresh('orderItems'), $this->teknisi, ['catatan' => 'ok', 'materials' => []]);

    $kurang = collect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->where('jenis', 'foto');

    expect($kurang)->toHaveCount(count($this->wajib))
        ->and($kurang->pluck('unit_report_id')->filter()->all())->toBe([])
        ->and($kurang->first()['label'])->not->toContain('Unit');
});

it('Livewire: foto Unit 2 diunggah lalu disimpan; unggahan per baris disembunyikan untuk jumlah>1', function () {
    $order = ($this->buatOrder)(3);
    $units = app(UnitReportService::class)->siapkan($order);
    app(TeknisiService::class)->submitLaporan($order->fresh('orderItems'), $this->teknisi, ['catatan' => 'ok', 'materials' => [], 'butuh_followup' => true]);
    $slot = $this->wajib[0];

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order->fresh()])
        ->assertSee('Foto diunggah per unit')
        ->assertDontSeeHtml("photoUpload('fotoKategori.{$order->orderItems->first()->id}.")
        ->call('toggleUnit', 2)
        ->assertSeeHtml("photoUpload('fotoUnit.{$units[1]->id}.{$slot}'")
        ->set("fotoUnit.{$units[1]->id}.{$slot}", UploadedFile::fake()->image('u2.jpg'))
        ->call('simpanFotoUnitTerunggah', $units[1]->id)
        ->assertHasNoErrors();

    $foto = WorkReportPhoto::where('slot', $slot)->get();
    expect($foto)->toHaveCount(1)
        ->and($foto->first()->order_unit_report_id)->toBe($units[1]->id)
        ->and($foto->first()->unit_no)->toBe(2);
});

it('Livewire: foto unit yang dipilih sebelum laporan ikut tersimpan saat submit', function () {
    $order = ($this->buatOrder)(3);
    $units = app(UnitReportService::class)->siapkan($order);
    $slot = $this->wajib[0];

    Livewire::actingAs($this->teknisi)
        ->test(OrderDetail::class, ['order' => $order->fresh()])
        ->set('catatan', 'Selesai dikerjakan')
        ->set("fotoUnit.{$units[2]->id}.{$slot}", UploadedFile::fake()->image('u3.jpg'))
        ->call('submitLaporan')
        ->assertHasNoErrors();

    $foto = WorkReportPhoto::where('slot', $slot)->first();
    expect($foto)->not->toBeNull()
        ->and($foto->order_unit_report_id)->toBe($units[2]->id)
        ->and($foto->unit_no)->toBe(3);
});

it('order tanpa baris unit (data lama) tidak terblokir walau jumlah>1', function () {
    $order = ($this->buatOrder)(3, OrderStatus::Selesai);

    $kurang = collect(app(TeknisiService::class)->fotoWajibKurang($order->fresh('orderItems')))->where('jenis', 'foto');

    // Tanpa baris unit -> perilaku lama (per baris×slot), tanpa label unit.
    expect($kurang->pluck('unit_report_id')->filter()->all())->toBe([]);
});
