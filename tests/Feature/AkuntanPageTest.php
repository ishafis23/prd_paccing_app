<?php

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Filament\Pages\Akuntan;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ServiceCatalog;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\AkuntanService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function akUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** Order selesai dgn harga layanan utama tertentu, lunas pada tanggal tsb. */
function akOrderLunas(string $tanggal, int $harga, string $nama = 'Budi'): Order
{
    $order = Order::factory()->create([
        'customer_id' => Customer::factory()->create(['nama' => $nama])->id,
        'service_catalog_id' => ServiceCatalog::factory()->create(['harga' => $harga])->id,
        'status' => OrderStatus::Selesai,
    ]);

    Payment::factory()->lunas()->create([
        'order_id' => $order->id,
        'total_tagihan' => $harga,
        'tanggal_bayar' => $tanggal,
    ]);

    return $order;
}

it('hanya owner/admin/finance yang boleh akses', function (string $role, bool $boleh) {
    $this->actingAs(akUser($role))->get(Akuntan::getUrl())
        ->{$boleh ? 'assertOk' : 'assertForbidden'}();
})->with([
    ['owner', true],
    ['admin', true],
    ['finance', true],
    ['hr', false],
    ['teknisi', false],
]);

it('pendapatan menghitung order selesai termasuk item tambahan, per tanggal bayar', function () {
    $order = akOrderLunas('2026-10-02', 100000);
    OrderItem::factory()->create(['order_id' => $order->id, 'harga' => 50000, 'jumlah' => 2, 'nama_layanan' => 'Ganti Kapasitor']);

    akOrderLunas('2026-10-03', 75000);
    akOrderLunas('2026-09-30', 999000); // bulan lain

    Order::factory()->create(['status' => OrderStatus::Dikerjakan]); // belum selesai

    $rows = app(AkuntanService::class)->pendapatan(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect($rows)->toHaveCount(2)
        ->and($rows->first()['tanggal'])->toBe('2026-10-03') // desc
        ->and($rows->firstWhere('order_id', $order->id)['total'])->toBe(200000.0)
        ->and($rows->firstWhere('order_id', $order->id)['items'])->toHaveCount(2)
        ->and($rows->firstWhere('order_id', $order->id)['items'][1]['tambahan'])->toBeTrue();

    $rekap = app(AkuntanService::class)->rekapHarian($rows);
    expect($rekap->pluck('total', 'tanggal')->all())->toBe(['2026-10-03' => 75000.0, '2026-10-02' => 200000.0]);
});

it('pengeluaran: admin + teknisi approved dihitung, pending terpisah, rejected diabaikan', function () {
    $teknisi = akUser('teknisi');
    Expense::factory()->create(['tanggal' => '2026-10-02', 'nominal' => 100000]);
    $buat = fn (string $status, int $nominal) => TeknisiExpense::create([
        'teknisi_id' => $teknisi->id, 'tanggal_input' => '2026-10-02', 'kategori' => 'bensin',
        'nominal' => $nominal, 'status' => $status,
    ]);
    $buat('approved', 20000);
    $buat('pending', 30000);
    $buat('rejected', 40000);
    Expense::factory()->create(['tanggal' => '2026-11-01', 'nominal' => 5000]);

    $service = app(AkuntanService::class);
    $rows = $service->pengeluaran(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

    expect($rows)->toHaveCount(3)
        ->and($service->totalPengeluaran($rows))->toBe(120000.0);

    $rekap = $service->rekapHarian($rows)->first();
    expect($rekap['total'])->toBe(120000.0)->and($rekap['pending'])->toBe(30000.0);
});

it('kartu hari ini tidak ikut filter bulan, kartu bulan ikut', function () {
    akOrderLunas(now()->toDateString(), 100000);
    akOrderLunas(now()->subMonthNoOverflow()->toDateString(), 50000);

    $service = app(AkuntanService::class);

    $ini = $service->kartu(CarbonImmutable::now());
    expect($ini['pendapatan_hari'])->toBe(100000.0)->and($ini['pendapatan_bulan'])->toBe(100000.0);

    $lalu = $service->kartu(CarbonImmutable::now()->subMonthNoOverflow());
    expect($lalu['pendapatan_hari'])->toBe(100000.0)->and($lalu['pendapatan_bulan'])->toBe(50000.0);
});

it('halaman menampilkan data dan modal detail', function () {
    $admin = akUser(RoleName::Admin->value);
    $hari = now()->toDateString();
    akOrderLunas($hari, 100000, 'Budi Akuntan');

    Livewire::actingAs($admin)->test(Akuntan::class)
        ->assertSee('Rp 100.000')
        ->call('bukaDetail', $hari)
        ->assertSee('Budi Akuntan')
        ->call('setTab', 'semua')
        ->assertSet('detailTanggal', null)
        ->assertSee('Budi Akuntan')
        ->call('setSemua', 'pengeluaran')
        ->assertSee('Belum ada data');
});
