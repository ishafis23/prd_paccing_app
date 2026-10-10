<?php

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Jobs\BuatLaporanBulananJob;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\LaporanBulanan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportPhoto;
use App\Services\LaporanBulananService;
use App\Services\LaporanPdfService;
use App\Services\LaporanPengerjaanService;
use App\Services\UnitReportService;
use App\Support\FotoLaporanSlot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');

    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $this->teknisi = User::factory()->create();
    $this->teknisi->assignRole(RoleName::Teknisi->value);

    $this->customer = Customer::factory()->create(['nama' => 'Circle K Makassar']);
    $this->cabang = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'nama_lokasi' => 'CK Dadi']);

    // Order Cuci AC `jumlah` unit pada $tanggal; bila $denganFoto, tiap unit diberi keterangan + 1 foto.
    $this->buatOrder = function (int $jumlah = 3, bool $denganFoto = true, string $tanggal = '2026-09-01', ?Customer $customer = null, ?CustomerAddress $cabang = null): Order {
        $customer ??= $this->customer;
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_address_id' => ($cabang ?? ($customer->is($this->customer) ? $this->cabang : null))?->id,
            'teknisi_id' => $this->teknisi->id,
            'status' => OrderStatus::Selesai,
            'jenis_pelanggan' => CustomerJenis::Company,
            'tanggal_jadwal' => $tanggal,
        ]);
        $order->orderItems()->delete();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'kategori' => ServiceType::CuciAc,
            'nama_layanan' => 'Cuci AC',
            'jumlah' => $jumlah,
        ]);
        $order = $order->fresh('orderItems');

        if (! $denganFoto) {
            return $order;
        }

        $units = app(UnitReportService::class)->siapkan($order);
        $laporan = WorkReport::factory()->create(['order_id' => $order->id, 'teknisi_id' => $this->teknisi->id]);
        $slot = array_key_first(FotoLaporanSlot::untuk(ServiceType::CuciAc));

        foreach ($units as $i => $unit) {
            $unit->update([
                'lokasi_label' => 'CK Dadi',
                'posisi' => 'Di atas toilet',
                'jenis_pekerjaan' => 'Cuci Standar',
                'suhu' => 17.5,
                'rpm' => $i === 0 ? 1250 : null,
                'kondisi' => 'normal',
            ]);
            WorkReportPhoto::create([
                'work_report_id' => $laporan->id,
                'order_item_id' => $unit->order_item_id,
                'order_unit_report_id' => $unit->id,
                'unit_no' => $i + 1,
                'slot' => $slot,
                'path' => UploadedFile::fake()->image("u{$i}.jpg", 1600, 1200)->store('work-reports', 'public'),
                'urutan' => 0,
            ]);
        }

        return $order->fresh(['orderItems']);
    };

    // PDF tak terkompresi supaya teksnya bisa dicari di uji.
    $this->pdfTeks = fn (array $dokumen): string => app(LaporanPdfService::class)->render($dokumen, ['compress' => 0]);
});

it('PDF ter-generate untuk order tanpa foto dan tanpa data unit (placeholder, bukan error)', function () {
    $order = ($this->buatOrder)(2, denganFoto: false);

    $dokumen = app(LaporanPengerjaanService::class)->dokumenOrder($order);
    $pdf = app(LaporanPdfService::class)->render($dokumen);

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(LaporanPdfService::jumlahHalaman($pdf))->toBeGreaterThanOrEqual(1)
        ->and($dokumen['kunjungan'][0]['unit'][0]['foto'][0]['ada'])->toBeFalse();

    expect(view('laporan.preview', ['dokumen' => $dokumen, 'urlPdf' => '#'])->render())->toContain('Foto belum diunggah');
});

it('PDF order 3 unit berfoto memuat nama customer, cabang, tanggal, dan caption unit', function () {
    $order = ($this->buatOrder)(3);
    $dokumen = app(LaporanPengerjaanService::class)->dokumenOrder($order);
    $pdf = ($this->pdfTeks)($dokumen);

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(LaporanPdfService::jumlahHalaman($pdf))->toBeGreaterThanOrEqual(1)
        ->and(collect($dokumen['kunjungan'][0]['unit'])->pluck('foto')->flatten(1)->where('ada', true))->toHaveCount(3)
        ->and($pdf)->toContain('CIRCLE K MAKASSAR')
        ->toContain('CK Dadi')
        ->toContain('1 September 2026')
        ->toContain('Cuci AC 3 Unit')
        ->toContain('CK DADI')
        ->toContain('UNIT SATU')
        ->toContain('UNIT TIGA')
        ->toContain('DI ATAS TOILET')
        ->toContain('CUCI STANDAR')
        ->toContain('1250')   // RPM unit 1 (caption bisa terpotong baris di PDF)
        ->toContain('17,5');
});

it('caption melewati bagian tanpa data dan tidak menulis nol', function () {
    $order = ($this->buatOrder)(1);
    $unit = OrderUnitReport::where('order_id', $order->id)->first();
    $unit->update(['posisi' => null, 'rpm' => 0, 'suhu' => null]);

    expect(app(LaporanPengerjaanService::class)->captionUnit($unit->fresh(), 'CK Dadi'))
        ->toBe('CK DADI · UNIT SATU · CUCI STANDAR');
});

it('preview HTML memuat caption dan tidak membocorkan path /storage/', function () {
    $order = ($this->buatOrder)(3);

    $html = $this->actingAs($this->admin)
        ->get(route('laporan.preview', $order))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('LAPORAN MAINTENANCE AC CIRCLE K MAKASSAR')
        ->toContain('CK DADI · UNIT SATU · DI ATAS TOILET · CUCI STANDAR · RPM 1250 · SUHU 17,5°C')
        ->toContain('Cabang')
        ->toContain('Hari/Tanggal')
        ->toContain('data:image/jpeg;base64')
        ->not->toContain('/storage/');
});

it('unduh PDF per order mengembalikan application/pdf', function () {
    $order = ($this->buatOrder)(2);

    $res = $this->actingAs($this->admin)->get(route('laporan.pdf', $order))->assertOk();

    expect($res->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr($res->getContent(), 0, 4))->toBe('%PDF');
});

it('foto diperkecil maksimal 1000px dan hasilnya di-cache di disk privat', function () {
    $order = ($this->buatOrder)(1);
    $foto = WorkReportPhoto::first();
    $kompres = app(\App\Services\FotoKompresService::class);

    $a = $kompres->kecilkan($foto->path);
    $b = $kompres->kecilkan($foto->path);

    expect(max($a['lebar'], $a['tinggi']))->toBeLessThanOrEqual(1000)
        ->and($a['path'])->toBe($b['path'])
        ->and(Storage::disk('local')->allFiles('laporan/cache'))->toHaveCount(1)
        ->and($kompres->kecilkan('work-reports/tidak-ada.jpg'))->toBeNull();
});

it('render tidak butuh HTTP: APP_URL tidak terjangkau tetap menghasilkan PDF dan HTML tanpa URL http', function () {
    config(['app.url' => 'http://host-tidak-ada.invalid']);
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    $order = ($this->buatOrder)(3);

    $dokumen = app(LaporanPengerjaanService::class)->dokumenOrder($order);
    $pdf = app(LaporanPdfService::class)->render($dokumen);
    $html = view('laporan.pdf', ['dokumen' => $dokumen])->render();

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and($html)->not->toContain('host-tidak-ada.invalid')
        ->and(preg_match_all('/src="(?!data:)/', $html))->toBe(0);
});

it('teknisi hanya boleh membuka laporan order miliknya', function () {
    $milik = ($this->buatOrder)(1);
    $lain = ($this->buatOrder)(1);
    $lain->update(['teknisi_id' => User::factory()->create()->id]);

    $this->actingAs($this->teknisi)->get(route('laporan.preview', $milik))->assertOk();
    $this->actingAs($this->teknisi)->get(route('laporan.pdf', $milik))->assertOk();
    $this->actingAs($this->teknisi)->get(route('laporan.preview', $lain))->assertForbidden();
    $this->actingAs($this->teknisi)->get(route('laporan.pdf', $lain))->assertForbidden();
    $this->actingAs($this->admin)->get(route('laporan.preview', $lain))->assertOk();
    auth()->logout();
    $this->get(route('laporan.preview', $milik))->assertRedirect();
});

it('laporan bulanan hanya memuat order customer & bulan itu, terurut tanggal', function () {
    $lain = Customer::factory()->create(['nama' => 'Customer Lain']);
    $o2 = ($this->buatOrder)(1, false, '2026-09-20');
    $o1 = ($this->buatOrder)(1, false, '2026-09-03');
    ($this->buatOrder)(1, false, '2026-08-30');                 // bulan lain
    ($this->buatOrder)(1, false, '2026-09-10', $lain);          // customer lain
    $batal = ($this->buatOrder)(1, false, '2026-09-11');
    $batal->update(['status' => OrderStatus::Batal]);

    $baris = app(LaporanBulananService::class)->ajukan($this->customer, '2026-09', null, $this->admin, sinkron: true);

    expect($baris->status)->toBe(LaporanBulanan::SELESAI)
        ->and($baris->jumlah_order)->toBe(2)
        ->and($baris->ukuran_bytes)->toBeGreaterThan(0)
        ->and(Storage::disk('local')->exists($baris->path))->toBeTrue()
        ->and(substr(Storage::disk('local')->get($baris->path), 0, 4))->toBe('%PDF')
        ->and(app(LaporanBulananService::class)->orderPeriode($baris)->pluck('id')->all())->toBe([$o1->id, $o2->id]);
});

it('laporan bulanan bisa dibatasi satu cabang', function () {
    $cabang2 = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'nama_lokasi' => 'CK Maipa']);
    $a = ($this->buatOrder)(1, false, '2026-09-03');
    ($this->buatOrder)(1, false, '2026-09-04', null, $cabang2);

    $baris = app(LaporanBulananService::class)->ajukan($this->customer, '2026-09', $this->cabang->id, $this->admin, sinkron: true);

    expect($baris->jumlah_order)->toBe(1)
        ->and(app(LaporanBulananService::class)->orderPeriode($baris)->first()->id)->toBe($a->id);
});

it('mode antrean mengisi status menunggu lalu selesai saat job dijalankan', function () {
    Queue::fake();
    ($this->buatOrder)(1, false, '2026-09-03');

    $baris = app(LaporanBulananService::class)->ajukan($this->customer, '2026-09', null, $this->admin);

    Queue::assertPushed(BuatLaporanBulananJob::class, fn ($j) => $j->laporanBulananId === $baris->id);
    expect($baris->fresh()->status)->toBe(LaporanBulanan::MENUNGGU)
        ->and($baris->fresh()->path)->toBeNull();

    (new BuatLaporanBulananJob($baris->id))->handle(app(LaporanBulananService::class));

    expect($baris->fresh()->status)->toBe(LaporanBulanan::SELESAI)
        ->and($baris->fresh()->selesai())->toBeTrue();
});

it('laporan bulanan tanpa order mencatat status gagal dengan pesan asli', function () {
    $baris = app(LaporanBulananService::class)->ajukan($this->customer, '2026-01', null, $this->admin, sinkron: true);

    expect($baris->status)->toBe(LaporanBulanan::GAGAL)
        ->and($baris->pesan_error)->toContain('Tidak ada order pada periode');
});

it('laporan bulanan yang meledak mencatat pesan error asli, bukan sekadar gagal', function () {
    ($this->buatOrder)(1, false, '2026-09-03');
    $service = new LaporanBulananService(
        app(LaporanPengerjaanService::class),
        new class extends LaporanPdfService
        {
            public function render(array $dokumen, array $opsiOutput = []): string
            {
                throw new RuntimeException('mesin PDF rusak');
            }
        },
        app(\App\Services\StorageQuotaService::class),
    );

    $baris = $service->ajukan($this->customer, '2026-09', null, $this->admin, sinkron: true);

    expect($baris->status)->toBe(LaporanBulanan::GAGAL)
        ->and($baris->pesan_error)->toContain('mesin PDF rusak');
});

it('PDF laporan bulanan menempati kuota penyimpanan', function () {
    ($this->buatOrder)(1, false, '2026-09-03');
    $kuota = app(\App\Services\StorageQuotaService::class);
    $sebelum = $kuota->pakaiBytes(segar: true);

    $baris = app(LaporanBulananService::class)->ajukan($this->customer, '2026-09', null, $this->admin, sinkron: true);

    expect($kuota->pakaiBytes(segar: true))->toBeGreaterThanOrEqual($sebelum + $baris->ukuran_bytes);
});

it('hanya Owner/Admin/Finance yang boleh membuat & mengunduh laporan bulanan', function () {
    ($this->buatOrder)(1, false, '2026-09-03');

    expect(fn () => app(LaporanBulananService::class)->ajukan($this->customer, '2026-09', null, $this->teknisi, sinkron: true))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    $baris = app(LaporanBulananService::class)->ajukan($this->customer, '2026-09', null, $this->admin, sinkron: true);

    $this->actingAs($this->teknisi)->get(route('laporan.bulanan.unduh', $baris))->assertForbidden();
    $this->actingAs($this->admin)->get(route('laporan.bulanan.unduh', $baris))->assertOk();

    $this->actingAs($this->teknisi)->get('/admin/laporan-bulanan')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin/laporan-bulanan')->assertOk();
});

it('halaman Laporan Bulanan: tombol sinkron menghasilkan file dan tampil di daftar', function () {
    ($this->buatOrder)(1, false, '2026-09-03');

    \Livewire\Livewire::actingAs($this->admin)
        ->test(\App\Filament\Pages\LaporanBulananCustomer::class)
        ->fillForm(['customer_id' => $this->customer->id, 'bulan' => '2026-09'])
        ->call('buatSinkron')
        ->assertHasNoFormErrors()
        ->assertSee('Unduh PDF');

    expect(LaporanBulanan::first()->status)->toBe(LaporanBulanan::SELESAI);
});
