<?php

use App\Enums\IncentiveKategori;
use App\Enums\IncomeCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PayrollStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Models\CashHandover;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\PayrollRun;
use App\Models\TechnicianIncentive;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->mk = fn (): User => tap(User::factory()->create(), fn ($u) => $u->assignRole(RoleName::Teknisi->value));
    $this->teknisi = ($this->mk)();
    $this->order = function (User $pic, ?User $rekan, int $harga, int $unit, string $tgl, OrderStatus $status = OrderStatus::Selesai) {
        $o = Order::factory()->create(['teknisi_id' => $pic->id, 'status' => $status, 'tanggal_jadwal' => $tgl, 'is_klaim' => false]);
        $o->orderItems()->delete();
        $o->orderItems()->create(['nama_layanan' => 'Cuci AC', 'kategori' => ServiceType::CuciAc, 'komponen' => IncomeCategory::Jasa, 'harga' => $harga, 'jumlah' => $unit]);
        if ($rekan) {
            OrderTechnician::create(['order_id' => $o->id, 'teknisi_id' => $rekan->id]);
        }

        return $o->fresh();
    };
});

it('pendapatan mengikuti tanggal jadwal, hanya PIC, jasa saja (bukan material)', function () {
    $rekan = ($this->mk)();
    ($this->order)($this->teknisi, null, 100000, 2, '2026-10-31');
    $o2 = ($this->order)($this->teknisi, null, 50000, 1, '2026-11-01');
    $o2->orderItems()->create(['nama_layanan' => 'Freon', 'kategori' => ServiceType::CuciAc, 'komponen' => IncomeCategory::Material, 'harga' => 200000, 'jumlah' => 1]);
    ($this->order)($rekan, $this->teknisi, 999000, 1, '2026-10-31'); // order tim: bukan miliknya

    Sanctum::actingAs($this->teknisi);

    $res = $this->getJson('/api/v1/pendapatan?mode=harian&tanggal=2026-10-31')->assertOk();
    expect($res->json('data.total'))->toBe(200000)->and($res->json('data.daftar'))->toHaveCount(1);

    $res = $this->getJson('/api/v1/pendapatan?mode=harian&tanggal=2026-11-01')->assertOk();
    expect($res->json('data.total'))->toBe(50000);

    $res = $this->getJson('/api/v1/pendapatan?mode=bulanan&bulan=2026-10')->assertOk();
    expect($res->json('data.total'))->toBe(200000)->and($res->json('data.games'))->toBeNull();
});

it('kasus 26 Okt: Game 5 tercapai (1.075.000), Game 6 tidak (7 unit < 12), untuk kedua teknisi', function () {
    $b = ($this->mk)();
    ($this->order)($this->teknisi, $b, 70000, 1, '2026-10-26');
    ($this->order)($this->teknisi, $b, 100000, 2, '2026-10-26');
    ($this->order)($this->teknisi, $b, 85000, 3, '2026-10-26');
    ($this->order)($this->teknisi, $b, 550000, 1, '2026-10-26');

    Sanctum::actingAs($b);
    $res = $this->getJson('/api/v1/games/harian?tanggal=2026-10-26')->assertOk();
    expect($res->json('data.omset'))->toBe(1075000)->and($res->json('data.unit'))->toBe(7)
        ->and($res->json('data.dapat_games5'))->toBeTrue()->and($res->json('data.dapat_games6'))->toBeFalse()
        ->and($res->json('data.mode'))->toBe('berdua');

    $res = $this->getJson('/api/v1/pendapatan?mode=harian&tanggal=2026-10-26')->assertOk();
    expect($res->json('data.games.dapat_games5'))->toBeTrue();
});

it('capaian: kartu hari ini/bulan ini & deret harian; order tim ikut dihitung', function () {
    $rekan = ($this->mk)();
    ($this->order)($this->teknisi, null, 50000, 1, today()->toDateString());
    ($this->order)($rekan, $this->teknisi, 50000, 1, today()->toDateString());
    ($this->order)($this->teknisi, null, 50000, 1, today()->toDateString(), OrderStatus::Terkendala);

    Sanctum::actingAs($this->teknisi);
    $res = $this->getJson('/api/v1/capaian')->assertOk();

    expect($res->json('data.hari_ini'))->toBe(2)->and($res->json('data.total_selesai'))->toBe(2)
        ->and($res->json('data.harian'))->toHaveCount(today()->daysInMonth);
    $hari = collect($res->json('data.harian'))->firstWhere('tanggal', today()->toDateString());
    expect($hari['selesai'])->toBe(2)->and($hari['terkendala'])->toBe(1);
});

it('insentif: ledger sendiri per bulan dengan label kategori', function () {
    $lain = ($this->mk)();
    TechnicianIncentive::create(['user_id' => $this->teknisi->id, 'tanggal' => '2026-10-26', 'kategori' => IncentiveKategori::Games5OmsetTim->value, 'tipe' => 'bonus', 'nominal' => 40000, 'sumber' => 'otomatis']);
    TechnicianIncentive::create(['user_id' => $lain->id, 'tanggal' => '2026-10-26', 'kategori' => IncentiveKategori::Games6Unit->value, 'tipe' => 'bonus', 'nominal' => 50000, 'sumber' => 'otomatis']);

    Sanctum::actingAs($this->teknisi);
    $res = $this->getJson('/api/v1/insentif?bulan=2026-10')->assertOk();

    expect($res->json('data.daftar'))->toHaveCount(1)
        ->and($res->json('data.daftar.0.nominal'))->toBe(40000)
        ->and($res->json('data.daftar.0.kategori_label'))->toBe('Bonus Game 5 (Omset Tim)');
});

it('setoran: kewajiban tunai, catat dengan foto, lalu kewajiban hilang', function () {
    $o = ($this->order)($this->teknisi, null, 100000, 1, today()->toDateString());
    $o->update(['metode_dipilih' => PaymentMethod::Cash, 'metode_dipilih_pada' => now()]);
    $orang = ($this->order)(($this->mk)(), null, 100000, 1, today()->toDateString());
    $orang->update(['metode_dipilih' => PaymentMethod::Cash, 'metode_dipilih_pada' => now()]);

    Sanctum::actingAs($this->teknisi);
    $res = $this->getJson('/api/v1/setoran')->assertOk();
    expect(collect($res->json('data.kewajiban'))->pluck('order_id')->all())->toBe([$o->id]);

    $body = ['order_id' => $o->id, 'nominal' => 100000, 'diserahkan_ke' => 'Bu Admin', 'tanggal_setor' => today()->toDateString(), 'foto' => UploadedFile::fake()->image('b.jpg', 200, 200)];
    $this->post('/api/v1/setoran', $body, ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'menunggu');

    $this->getJson('/api/v1/setoran')->assertJsonPath('data.ringkasan.kewajiban', 0)->assertJsonCount(1, 'data.riwayat');
    expect(CashHandover::count())->toBe(1);

    // Setoran untuk order tim lain => 404.
    $this->post('/api/v1/setoran', ['order_id' => $orang->id] + $body + ['foto' => UploadedFile::fake()->image('c.jpg')], ['Accept' => 'application/json'])->assertStatus(404);
});

it('gaji: hanya periode yang sudah di-ACC milik sendiri; draf tidak tampil', function () {
    $lain = ($this->mk)();
    $buat = fn (User $u, PayrollStatus $s, string $mulai) => PayrollRun::create([
        'user_id' => $u->id, 'periode_mulai' => $mulai, 'periode_selesai' => date('Y-m-d', strtotime($mulai.' +29 days')),
        'gaji_pokok' => 3000000, 'gaji_bersih' => 3100000, 'status' => $s->value,
    ]);
    $acc = $buat($this->teknisi, PayrollStatus::Disetujui, '2026-09-01');
    $buat($this->teknisi, PayrollStatus::Draft, '2026-10-01');
    $punyaLain = $buat($lain, PayrollStatus::Disetujui, '2026-09-01');

    Sanctum::actingAs($this->teknisi);
    $res = $this->getJson('/api/v1/gaji')->assertOk();
    expect($res->json('data'))->toHaveCount(1)->and($res->json('data.0.id'))->toBe($acc->id)->and($res->json('data.0.gaji_bersih'))->toBe(3100000);

    $this->get("/api/v1/gaji/{$punyaLain->id}/slip", ['Accept' => 'application/json'])->assertStatus(404);

    $slip = $this->get("/api/v1/gaji/{$acc->id}/slip")->assertOk();
    expect($slip->headers->get('Content-Type'))->toBe('application/pdf');
});

it('administrasi: setoran telat menandai tertahan_setoran dengan aksi ke layar setoran', function () {
    $o = ($this->order)($this->teknisi, null, 100000, 1, today()->subDays(3)->toDateString());
    $o->update(['metode_dipilih' => PaymentMethod::Cash, 'metode_dipilih_pada' => now()->subDays(3)]);

    Sanctum::actingAs($this->teknisi);
    $this->getJson('/api/v1/administrasi')->assertOk()
        ->assertJsonPath('data.tertahan_setoran', true)->assertJsonPath('data.penghalang.0.jenis', 'setoran')
        ->assertJsonPath('data.penghalang.0.aksi.layar', 'setoran');
});
