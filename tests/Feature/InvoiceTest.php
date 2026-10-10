<?php

use App\Enums\CustomerJenis;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Filament\Resources\InvoiceResource\Pages\ViewInvoice;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportPhoto;
use App\Services\BusinessInfoService;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\LaporanPdfService;
use App\Services\UnitReportService;
use App\Support\FotoLaporanSlot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
    BusinessInfoService::lupakanCache();

    $mk = function (string $role): User {
        $u = User::factory()->create();
        $u->assignRole($role);

        return $u;
    };
    $this->admin = $mk(RoleName::Admin->value);
    $this->finance = $mk(RoleName::Finance->value);
    $this->owner = $mk(RoleName::Owner->value);
    $this->teknisi = $mk(RoleName::Teknisi->value);

    $this->customer = Customer::factory()->create(['nama' => 'Pimpinan Stella Gracia School', 'no_hp' => '085240453080']);
    $this->cabang = CustomerAddress::factory()->create(['customer_id' => $this->customer->id, 'nama_lokasi' => 'Lakopi Mess Guru']);

    app(BusinessInfoService::class)->perbarui([
        'nama_usaha' => 'PT. Paccing Jaya Sejahtera',
        'alamat' => 'Jalan Lasuloro Dalam IV, Makassar',
        'kontak_wa' => '085240453080',
        'bank_nama' => 'BCA',
        'bank_rekening' => '7991090294',
        'bank_atas_nama' => 'Ranto Ari Pratama',
    ], $this->admin);

    // Order selesai, `jumlah` unit Cuci AC @ $harga; $foto = beri 1 foto per unit.
    $this->buatOrder = function (int $jumlah = 2, float $harga = 75000, string $tanggal = '2026-09-01', ?Customer $customer = null, bool $foto = false, OrderStatus $status = OrderStatus::Selesai): Order {
        $customer ??= $this->customer;
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'customer_address_id' => $customer->is($this->customer) ? $this->cabang->id : null,
            'teknisi_id' => $this->teknisi->id,
            'status' => $status,
            'jenis_pelanggan' => CustomerJenis::Company,
            'tanggal_jadwal' => $tanggal,
        ]);
        $order->orderItems()->delete();
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'kategori' => ServiceType::CuciAc,
            'nama_layanan' => 'Cuci AC Standar',
            'harga' => $harga,
            'jumlah' => $jumlah,
        ]);
        $order = $order->fresh('orderItems');

        if ($foto) {
            $laporan = WorkReport::factory()->create(['order_id' => $order->id, 'teknisi_id' => $this->teknisi->id]);
            $slot = array_key_first(FotoLaporanSlot::untuk(ServiceType::CuciAc));
            foreach (app(UnitReportService::class)->siapkan($order) as $i => $unit) {
                WorkReportPhoto::create([
                    'work_report_id' => $laporan->id,
                    'order_item_id' => $unit->order_item_id,
                    'order_unit_report_id' => $unit->id,
                    'unit_no' => $i + 1,
                    'slot' => $slot,
                    'path' => UploadedFile::fake()->image("u{$i}.jpg", 1200, 900)->store('work-reports', 'public'),
                    'urutan' => 0,
                ]);
            }
        }

        return $order->fresh(['orderItems']);
    };

    $this->bayar = fn (Order $o, float $jumlah, PaymentStatus $status): Payment => Payment::create([
        'order_id' => $o->id,
        'metode' => PaymentMethod::Transfer,
        'status' => $status,
        'total_tagihan' => $o->total(),
        'jumlah_dibayar' => $jumlah,
        'tanggal_bayar' => $status === PaymentStatus::Lunas ? '2026-09-05' : null,
        'dicatat_oleh' => $this->admin->id,
    ]);

    $this->svc = fn (): InvoiceService => app(InvoiceService::class);
});

// ---------------------------------------------------------------- (a) penomoran

it('(a) nomor INV-YYYYMM-0001 urut per bulan, reset bulan berikutnya, tidak ada kembar', function () {
    $nomor = [];
    foreach (range(1, 3) as $i) {
        $o = ($this->buatOrder)(1, 75000, '2026-09-0'.$i);
        $nomor[] = ($this->svc)()->buat([$o], $this->admin, ['tanggal' => '2026-09-1'.$i])->nomor;
    }

    expect($nomor)->toBe(['INV-202609-0001', 'INV-202609-0002', 'INV-202609-0003']);

    $o = ($this->buatOrder)(1, 75000, '2026-10-02');
    expect(($this->svc)()->buat([$o], $this->admin, ['tanggal' => '2026-10-03'])->nomor)->toBe('INV-202610-0001');

    // Invoice batal tetap memegang nomornya — tidak dipakai ulang.
    $batal = Invoice::query()->where('nomor', 'INV-202609-0003')->first();
    ($this->svc)()->batalkan($batal, $this->admin);
    $o = ($this->buatOrder)(1, 75000, '2026-09-20');
    expect(($this->svc)()->buat([$o], $this->admin, ['tanggal' => '2026-09-21'])->nomor)->toBe('INV-202609-0004');

    expect(Invoice::query()->pluck('nomor')->duplicates())->toBeEmpty();
});

it('(a) unique index menolak nomor kembar', function () {
    $o = ($this->buatOrder)();
    $inv = ($this->svc)()->buat([$o], $this->admin);

    expect(fn () => Invoice::create(['nomor' => $inv->nomor, 'customer_id' => $this->customer->id, 'tanggal' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('(a) bentrok nomor saat dua invoice dibuat bersamaan → penomoran diulang, tidak ada nomor kembar', function () {
    $o1 = ($this->buatOrder)(1, 75000, '2026-09-01');
    $pertama = ($this->svc)()->buat([$o1], $this->admin, ['tanggal' => '2026-09-10']);
    expect($pertama->nomor)->toBe('INV-202609-0001');

    // Simulasi balapan: percobaan pertama memakai nomor basi yang sudah diambil pihak lain.
    $balapan = new class(app(BusinessInfoService::class)) extends InvoiceService
    {
        public int $dipanggil = 0;

        protected function nomorUntuk(Carbon $tanggal): string
        {
            return ++$this->dipanggil === 1 ? 'INV-202609-0001' : parent::nomorUntuk($tanggal);
        }
    };

    $o2 = ($this->buatOrder)(1, 75000, '2026-09-02');
    $kedua = $balapan->buat([$o2], $this->admin, ['tanggal' => '2026-09-11']);

    expect($balapan->dipanggil)->toBe(2)
        ->and($kedua->nomor)->toBe('INV-202609-0002')
        ->and(Invoice::query()->pluck('nomor')->duplicates())->toBeEmpty()
        ->and(Invoice::count())->toBe(2);
});

// ------------------------------------------------- (b) baris & total, (c) saldo

it('(b) baris & total invoice = Σ item order yang dipilih (multi-order), deskripsi = tanggal + lokasi', function () {
    $o1 = ($this->buatOrder)(6, 75000, '2026-06-26');
    $o2 = ($this->buatOrder)(2, 75000, '2026-06-27');
    $o2->orderItems()->create(['nama_layanan' => 'Ganti Kapasitor', 'kategori' => ServiceType::CuciAc, 'harga' => 120000, 'jumlah' => 1]);
    $o2->orderItems()->create(['nama_layanan' => 'Batal', 'kategori' => ServiceType::CuciAc, 'harga' => 999000, 'jumlah' => 1, 'dibatalkan' => true]);
    $o2 = $o2->fresh('orderItems');

    $invoice = ($this->svc)()->buat([$o2, $o1], $this->admin);

    expect($invoice->items)->toHaveCount(3)
        ->and($invoice->orders)->toHaveCount(2)
        ->and((float) $invoice->total)->toBe($o1->total() + $o2->total())
        ->and((float) $invoice->subtotal)->toBe(450000.0 + 150000.0 + 120000.0)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft);

    $pertama = $invoice->items->first();
    expect($pertama->order_id)->toBe($o1->id)             // urut tanggal jadwal
        ->and($pertama->nama)->toBe('Cuci AC Standar')
        ->and((float) $pertama->jumlah)->toBe(6.0)
        ->and($pertama->deskripsi)->toBe("26 Juni 2026\nLakopi Mess Guru")
        ->and($invoice->bank_nama)->toBe('BCA')
        ->and($invoice->bank_rekening)->toBe('7991090294');

    // Ubah info usaha sesudahnya: snapshot bank di invoice lama tidak berubah.
    app(BusinessInfoService::class)->perbarui(['nama_usaha' => 'X', 'bank_nama' => 'BNI', 'bank_rekening' => '1'], $this->admin);
    expect($invoice->fresh()->bank_nama)->toBe('BCA');
});

it('(b) total dihitung dari invoice_items setelah baris diedit (bukan dari Order mentah)', function () {
    $o = ($this->buatOrder)(2, 75000);
    $invoice = ($this->svc)()->buat([$o], $this->admin);

    $invoice = ($this->svc)()->perbaruiBaris($invoice, [
        ['order_id' => $o->id, 'nama' => 'Cuci AC Standar', 'deskripsi' => 'Diubah', 'jumlah' => 3, 'harga' => 70000],
        ['nama' => 'Ongkos transport', 'jumlah' => 1, 'harga' => 50000],
    ], $this->admin);

    expect((float) $invoice->total)->toBe(260000.0)
        ->and((float) $invoice->subtotal)->toBe(260000.0)
        ->and($o->fresh()->total())->toBe(150000.0)   // order tidak ikut berubah
        ->and($invoice->items()->count())->toBe(2);
});

it('(b) baris hanya bisa diedit selama draft', function () {
    $invoice = ($this->svc)()->buat([($this->buatOrder)()], $this->admin);
    ($this->svc)()->tandaiTerkirim($invoice, $this->admin);

    expect(fn () => ($this->svc)()->perbaruiBaris($invoice->fresh(), [['nama' => 'x', 'jumlah' => 1, 'harga' => 1]], $this->admin))
        ->toThrow(BusinessRuleException::class);
});

it('(b) multi-order wajib satu customer, status selesai, dan belum punya invoice aktif', function () {
    $lain = Customer::factory()->create();
    $a = ($this->buatOrder)();
    $b = ($this->buatOrder)(1, 75000, '2026-09-02', $lain);
    $belum = ($this->buatOrder)(1, 75000, '2026-09-03', null, false, OrderStatus::Dikerjakan);

    expect(fn () => ($this->svc)()->buat([$a, $b], $this->admin))->toThrow(BusinessRuleException::class, 'customer yang sama');
    expect(fn () => ($this->svc)()->buat([$belum], $this->admin))->toThrow(BusinessRuleException::class, 'belum selesai');

    ($this->svc)()->buat([$a], $this->admin);
    expect(fn () => ($this->svc)()->buat([$a], $this->admin))->toThrow(BusinessRuleException::class, 'sudah punya invoice aktif');
    expect(($this->svc)()->bisaDibuatInvoice($a))->toBeFalse();
});

it('(c) saldo = total − Σ pembayaran tercatat pada order-order invoice', function () {
    $o1 = ($this->buatOrder)(2, 75000, '2026-09-01');   // 150.000
    $o2 = ($this->buatOrder)(1, 100000, '2026-09-02');  // 100.000
    $invoice = ($this->svc)()->buat([$o1, $o2], $this->admin);

    expect($invoice->saldo())->toBe(250000.0);

    ($this->bayar)($o1, 50000, PaymentStatus::Dp);
    expect($invoice->fresh()->saldo())->toBe(200000.0);

    ($this->bayar)($o2, 100000, PaymentStatus::Lunas);
    $inv = ($this->svc)()->tampilan($invoice->fresh());
    expect($inv['saldo'])->toBe(100000.0)->and($inv['dibayar'])->toBe(150000.0)->and($inv['total'])->toBe(250000.0);
});

// ------------------------------------------------------------ (d) status/lunas

it('(d) invoice lunas hanya bila SEMUA order lunas; sebagian tetap terkirim', function () {
    $o1 = ($this->buatOrder)(1, 75000, '2026-09-01');
    $o2 = ($this->buatOrder)(1, 75000, '2026-09-02');
    $invoice = ($this->svc)()->buat([$o1, $o2], $this->admin);

    // Draft tidak ikut sinkron pembayaran.
    ($this->bayar)($o1, 75000, PaymentStatus::Lunas);
    expect(($this->svc)()->segarkanStatus($invoice->fresh())->status)->toBe(InvoiceStatus::Draft);

    ($this->svc)()->tandaiTerkirim($invoice->fresh(), $this->admin);
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Terkirim)   // o2 belum lunas
        ->and($invoice->fresh()->saldo())->toBe(75000.0);

    ($this->bayar)($o2, 30000, PaymentStatus::Dp);
    expect(($this->svc)()->segarkanStatus($invoice->fresh())->status)->toBe(InvoiceStatus::Terkirim);

    Payment::query()->where('order_id', $o2->id)->update(['status' => PaymentStatus::Lunas->value, 'jumlah_dibayar' => 75000]);
    expect(($this->svc)()->segarkanStatus($invoice->fresh())->status)->toBe(InvoiceStatus::Lunas)
        ->and($invoice->fresh()->saldo())->toBe(0.0);
});

// ---------------------------------------------------------------- (e) izin

it('(e) Owner/Admin/Finance boleh membuat invoice; teknisi ditolak', function () {
    foreach ([$this->owner, $this->admin, $this->finance] as $user) {
        $o = ($this->buatOrder)();
        expect(($this->svc)()->buat([$o], $user)->dibuat_oleh)->toBe($user->id);
    }

    $o = ($this->buatOrder)();
    expect(fn () => ($this->svc)()->buat([$o], $this->teknisi))->toThrow(AuthorizationException::class);

    $invoice = Invoice::first();
    expect(fn () => ($this->svc)()->tandaiTerkirim($invoice, $this->teknisi))->toThrow(AuthorizationException::class);
    expect(fn () => ($this->svc)()->batalkan($invoice, $this->teknisi))->toThrow(AuthorizationException::class);
    expect(fn () => ($this->svc)()->perbaruiBaris($invoice, [['nama' => 'x', 'jumlah' => 1, 'harga' => 1]], $this->teknisi))->toThrow(AuthorizationException::class);
});

it('(e) HTTP & resource: teknisi ditolak, Owner/Admin/Finance boleh', function () {
    $invoice = ($this->svc)()->buat([($this->buatOrder)()], $this->admin);

    $this->get(route('invoice.pdf', $invoice))->assertRedirect();   // tamu → login
    $this->actingAs($this->teknisi)->get(route('invoice.pdf', $invoice))->assertForbidden();

    foreach ([$this->owner, $this->admin, $this->finance] as $user) {
        $this->actingAs($user)->get(route('invoice.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user);
        expect(InvoiceResource::canViewAny())->toBeTrue();
    }

    $this->actingAs($this->teknisi);
    expect(InvoiceResource::canViewAny())->toBeFalse();
    Livewire::actingAs($this->teknisi)->test(ListInvoices::class)->assertForbidden();
});

// ------------------------------------------------------------------ (f) PDF

it('(f) PDF ter-generate dengan/tanpa lampiran: lampiran menambah halaman, tanpa lampiran hanya faktur', function () {
    $o1 = ($this->buatOrder)(3, 75000, '2026-06-26', null, true);
    $o2 = ($this->buatOrder)(2, 75000, '2026-06-27', null, true);
    $invoice = ($this->svc)()->buat([$o1, $o2], $this->admin, ['tanggal' => '2026-10-03']);
    $pdf = app(InvoicePdfService::class);

    $tanpa = $pdf->render($invoice, false, ['compress' => 0]);
    $dengan = $pdf->render($invoice, true, ['compress' => 0]);

    expect(substr($tanpa, 0, 4))->toBe('%PDF')
        ->and(LaporanPdfService::jumlahHalaman($tanpa))->toBe(1)
        ->and(LaporanPdfService::jumlahHalaman($dengan))->toBeGreaterThan(1)
        // 2 kunjungan masing-masing mulai di halaman baru: faktur + minimal 2 halaman lampiran
        ->and(LaporanPdfService::jumlahHalaman($dengan))->toBeGreaterThanOrEqual(3)
        ->and($tanpa)->toContain('FAKTUR')
        ->toContain($invoice->nomor)
        ->toContain('PIMPINAN STELLA GRACIA SCHOOL')
        ->toContain('Saldo Jatuh Tempo')
        ->toContain('Terima kasih telah berbisnis dengan kami.')
        ->toContain('rekening BCA 7991090294 a.n Ranto Ari Pratama')
        ->toContain('Cuci AC Standar')
        ->toContain('225,000.00')
        ->toContain('IDR375,000.00')
        ->not->toContain('LAPORAN MAINTENANCE')
        ->and($dengan)->toContain($invoice->nomor)->toContain('UNIT SATU');
});

it('(f) info bank kosong: tidak mengarang rekening, ada catatan di PDF', function () {
    $info = app(BusinessInfoService::class)->data();
    $info->update(['bank_nama' => null, 'bank_rekening' => null, 'bank_atas_nama' => null]);
    BusinessInfoService::lupakanCache();

    $invoice = ($this->svc)()->buat([($this->buatOrder)()], $this->admin);
    $pdf = app(InvoicePdfService::class)->render($invoice, false, ['compress' => 0]);

    expect($pdf)->toContain('Informasi rekening pembayaran belum diisi')->not->toContain('Pembayaran dapat dilakukan');
});

// ------------------------------------------------------------ (g) tautan publik

it('(g) tautan publik: token benar → 200 (+PDF), token salah/draft → 404; token tidak ada di dalam PDF', function () {
    $invoice = ($this->svc)()->buat([($this->buatOrder)(2, 75000, '2026-09-01', null, true)], $this->admin);

    // Draft belum punya token / belum terbit.
    $this->get('/invoice/'.$invoice->id.'/apa-saja')->assertNotFound();

    ($this->svc)()->tandaiTerkirim($invoice, $this->admin);
    $token = $invoice->fresh()->token;

    $this->get(route('invoice.publik', [$invoice, $token]))
        ->assertOk()
        ->assertSee($invoice->nomor)
        ->assertSee('FAKTUR')
        ->assertSee('Unduh PDF');

    $this->get(route('invoice.publik', [$invoice, 'salah-'.$token]))->assertNotFound();
    $this->get(route('invoice.publik.pdf', [$invoice, 'salah']))->assertNotFound();

    $res = $this->get(route('invoice.publik.pdf', [$invoice, $token]))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect(substr($res->getContent(), 0, 4))->toBe('%PDF');

    $lampiran = $this->get(route('invoice.publik.pdf', [$invoice, $token]).'?lampiran=1')->assertOk()->getContent();
    expect(LaporanPdfService::jumlahHalaman($lampiran))->toBeGreaterThan(1);

    $teks = app(InvoicePdfService::class)->render($invoice->fresh(), true, ['compress' => 0]);
    expect($teks)->not->toContain($token);
});

it('(g) tombol Kirim via WA: wa.me/<no_hp>?text=… berisi nomor & tautan publik', function () {
    $invoice = ($this->svc)()->buat([($this->buatOrder)()], $this->admin);
    ($this->svc)()->tandaiTerkirim($invoice, $this->admin);

    $url = ($this->svc)()->urlWa($invoice->fresh());
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

    expect($url)->toStartWith('https://wa.me/6285240453080?text=')
        ->and($q['text'])->toContain($invoice->nomor)
        ->toContain('/invoice/'.$invoice->id.'/'.$invoice->fresh()->token);
});

// ------------------------------------------------------------------ (h) batal

it('(h) invoice batal: tidak tampil publik, tidak bisa diunduh, order bisa ditagih ulang', function () {
    $o = ($this->buatOrder)();
    $invoice = ($this->svc)()->buat([$o], $this->admin);
    ($this->svc)()->tandaiTerkirim($invoice, $this->admin);
    $token = $invoice->fresh()->token;
    $this->get(route('invoice.publik', [$invoice, $token]))->assertOk();

    ($this->svc)()->batalkan($invoice->fresh(), $this->admin);

    $this->get(route('invoice.publik', [$invoice, $token]))->assertNotFound();
    $this->get(route('invoice.publik.pdf', [$invoice, $token]))->assertNotFound();
    $this->actingAs($this->admin)->get(route('invoice.pdf', $invoice))->assertNotFound();

    expect(Invoice::query()->aktif()->count())->toBe(0)
        ->and(($this->svc)()->punyaInvoiceAktif($o))->toBeFalse()
        ->and(($this->svc)()->orderTersedia($this->customer)->pluck('id')->all())->toBe([$o->id])
        ->and(($this->svc)()->buat([$o], $this->admin)->nomor)->not->toBe($invoice->nomor);

    // Pembayaran lunas tidak "menghidupkan" kembali invoice batal.
    ($this->bayar)($o, 150000, PaymentStatus::Lunas);
    expect(($this->svc)()->segarkanStatus($invoice->fresh())->status)->toBe(InvoiceStatus::Batal);
});

// -------------------------------------------------- alur end-to-end (Livewire/HTTP)

it('e2e: Buat Invoice dari tabel Order → ubah 1 baris → unduh PDF → tautan publik benar/salah', function () {
    $o = ($this->buatOrder)(2, 75000, '2026-09-01');

    Livewire::actingAs($this->admin)
        ->test(ListOrders::class)
        ->assertTableActionVisible('buatInvoice', $o)
        ->callTableAction('buatInvoice', $o, data: ['tanggal' => '2026-10-03', 'jatuh_tempo' => '2026-10-10', 'catatan' => 'Mohon transfer.'])
        ->assertHasNoTableActionErrors();

    $invoice = Invoice::query()->firstOrFail();
    expect($invoice->nomor)->toBe('INV-202610-0001')->and((float) $invoice->total)->toBe(150000.0);

    // Order yang sudah punya invoice aktif: tombol Buat Invoice hilang, Lihat Invoice muncul.
    Livewire::actingAs($this->admin)->test(ListOrders::class)
        ->assertTableActionHidden('buatInvoice', $o->fresh())
        ->assertTableActionVisible('lihatInvoice', $o->fresh());

    // Ubah 1 baris lewat halaman invoice.
    $halaman = Livewire::actingAs($this->admin)
        ->test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->assertSee($invoice->nomor)
        ->mountAction('editBaris');

    // Form terisi baris lama (kunci repeater = uuid) → timpa baris yang sama.
    $kunci = array_key_first($halaman->instance()->mountedActionsData[0]['baris']);
    $halaman
        ->setActionData([
            'baris' => [$kunci => ['order_id' => $o->id, 'nama' => 'Cuci AC Standar', 'deskripsi' => "1 September 2026\nLakopi", 'jumlah' => 4, 'harga' => 80000]],
            'jatuh_tempo' => '2026-10-17',
            'catatan' => 'Catatan baru',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $invoice->refresh();
    expect((float) $invoice->total)->toBe(320000.0)
        ->and($invoice->items()->count())->toBe(1)
        ->and($invoice->jatuh_tempo->toDateString())->toBe('2026-10-17')
        ->and($invoice->catatan)->toBe('Catatan baru');

    // Tandai terkirim → token terbit.
    Livewire::actingAs($this->admin)
        ->test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
        ->callAction('tandaiTerkirim')
        ->assertHasNoActionErrors();
    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Terkirim)->and($invoice->token)->not->toBeNull();

    // Unduh PDF (admin) memuat total terbaru.
    $res = $this->actingAs($this->admin)->get(route('invoice.pdf', $invoice))->assertOk();
    expect(substr($res->getContent(), 0, 4))->toBe('%PDF');
    expect(app(InvoicePdfService::class)->render($invoice, false, ['compress' => 0]))->toContain('320,000.00');

    // Tautan publik: benar 200, salah 404.
    $this->get(route('invoice.publik', [$invoice, $invoice->token]))->assertOk()->assertSee('320,000.00');
    $this->get(route('invoice.publik', [$invoice, 'x'.$invoice->token]))->assertNotFound();

    // Daftar invoice memuat nomor & menandai lewat jatuh tempo.
    Livewire::actingAs($this->admin)->test(ListInvoices::class)->assertCanSeeTableRecords([$invoice]);
});

it('e2e: Buat Invoice Multi-Order dari halaman daftar Invoice', function () {
    $o1 = ($this->buatOrder)(6, 75000, '2026-06-26');
    $o2 = ($this->buatOrder)(2, 75000, '2026-06-27');

    Livewire::actingAs($this->finance)
        ->test(ListInvoices::class)
        ->callAction('buatMultiOrder', data: [
            'customer_id' => $this->customer->id,
            'order_ids' => [$o1->id, $o2->id],
            'tanggal' => '2026-10-03',
            'jatuh_tempo' => '2026-10-03',
        ])
        ->assertHasNoActionErrors();

    $invoice = Invoice::query()->with('orders')->firstOrFail();
    expect($invoice->orders)->toHaveCount(2)
        ->and((float) $invoice->total)->toBe(600000.0)
        ->and(app(InvoiceService::class)->tampilan($invoice)['ketentuan'])->toBe('Jatuh tempo di Kuitansi');
});

it('Info Usaha menyimpan rekening bank & perbarui() lama tidak menghapusnya', function () {
    Livewire::actingAs($this->admin)
        ->test(\App\Filament\Pages\KelolaInfoUsaha::class)
        ->assertSet('bankNama', 'BCA')
        ->set('bankNama', 'Mandiri')
        ->set('bankRekening', '123456')
        ->set('bankAtasNama', 'Budi')
        ->call('simpan');

    $info = app(BusinessInfoService::class)->data();
    expect($info->bank_nama)->toBe('Mandiri')->and($info->bank_rekening)->toBe('123456')->and($info->bank_atas_nama)->toBe('Budi');

    app(BusinessInfoService::class)->perbarui(['nama_usaha' => 'Tanpa Bank'], $this->admin);
    expect(app(BusinessInfoService::class)->data()->bank_nama)->toBe('Mandiri');
});

it('penanda lewat jatuh tempo hanya untuk invoice terkirim yang masih punya saldo', function () {
    $o = ($this->buatOrder)();
    $invoice = ($this->svc)()->buat([$o], $this->admin, ['tanggal' => '2026-01-01', 'jatuh_tempo' => '2026-01-08']);

    expect($invoice->lewatJatuhTempo())->toBeFalse();   // draft

    ($this->svc)()->tandaiTerkirim($invoice, $this->admin);
    expect($invoice->fresh()->lewatJatuhTempo())->toBeTrue();

    ($this->bayar)($o, 150000, PaymentStatus::Lunas);
    ($this->svc)()->segarkanStatus($invoice->fresh());
    expect($invoice->fresh()->lewatJatuhTempo())->toBeFalse();
});
