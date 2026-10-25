<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\TeknisiExpense;
use App\Models\User;
use App\Services\CashHandoverService;
use App\Services\SyaratAdministrasiService;
use App\Services\StockService;
use App\Services\TeknisiService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

function administrasiTeknisi(): User
{
    $user = User::factory()->create();
    $user->assignRole(RoleName::Teknisi->value);
    return $user;
}

function administrasiOrder(User $teknisi, array $atribut = []): Order
{
    return Order::factory()->create(array_merge([
        'teknisi_id' => $teknisi->id,
        'status' => OrderStatus::Selesai,
    ], $atribut));
}

it('mengembalikan penghalang foto dan laporan pada order selesai', function () {
    $teknisi = administrasiTeknisi();
    $order = administrasiOrder($teknisi);

    $penghalang = app(SyaratAdministrasiService::class)->penghalang($teknisi);

    expect(collect($penghalang)->where('order_id', $order->id)->pluck('jenis')->all())
        ->toContain('foto')->toContain('laporan');
});

it('mengembalikan penghalang pengeluaran pending dan hilang saat disetujui', function () {
    $teknisi = administrasiTeknisi();
    $order = administrasiOrder($teknisi, ['laporan_terkirim_pada' => now()]);
    $expense = TeknisiExpense::create(['teknisi_id' => $teknisi->id, 'order_id' => $order->id, 'tanggal_input' => today(), 'kategori' => 'bensin', 'qty' => 1, 'harga' => 10000, 'nominal' => 10000, 'status' => 'pending']);

    expect(collect(app(SyaratAdministrasiService::class)->penghalang($teknisi))->pluck('jenis')->all())->toContain('pengeluaran');
    $expense->update(['status' => 'approved']);
    expect(collect(app(SyaratAdministrasiService::class)->penghalang($teknisi))->where('jenis', 'pengeluaran'))->toBeEmpty();
});

it('setoran tunai kemarin menghalangi, tetapi yang ditandai hari ini tidak', function () {
    $teknisi = administrasiTeknisi();
    $kemarin = administrasiOrder($teknisi, ['metode_dipilih' => PaymentMethod::Cash, 'metode_dipilih_pada' => now()->subDay(), 'laporan_terkirim_pada' => now()]);
    $hariIni = administrasiOrder($teknisi, ['metode_dipilih' => PaymentMethod::Cash, 'metode_dipilih_pada' => now(), 'laporan_terkirim_pada' => now()]);

    expect(app(CashHandoverService::class)->telat($teknisi)->pluck('id')->all())->toContain($kemarin->id)->not->toContain($hariIni->id);
    expect(collect(app(SyaratAdministrasiService::class)->penghalang($teknisi))->where('jenis', 'setoran')->pluck('order_id')->all())->toContain($kemarin->id);

    Storage::disk('public')->put('setoran-tunai/kemarin.jpg', 'bukti');
    app(CashHandoverService::class)->catat($kemarin, $teknisi, ['nominal' => 10000, 'diserahkan_ke' => 'Admin Kantor', 'tanggal_setor' => today(), 'foto_path' => 'setoran-tunai/kemarin.jpg']);
    expect(collect(app(SyaratAdministrasiService::class)->penghalang($teknisi))->where('jenis', 'setoran')->pluck('order_id')->all())->not->toContain($kemarin->id);
});

it('berangkat dan check-in sama-sama menolak bila administrasi belum tuntas', function () {
    $teknisi = administrasiTeknisi();
    administrasiOrder($teknisi);
    $berangkat = administrasiOrder($teknisi, ['status' => OrderStatus::Terjadwal]);
    $checkIn = administrasiOrder($teknisi, ['status' => OrderStatus::MenujuLokasi]);
    $service = new TeknisiService(app(StockService::class));

    expect(fn () => $service->berangkat($berangkat, $teknisi))->toThrow(BusinessRuleException::class);
    expect(fn () => $service->checkIn($checkIn, $teknisi))->toThrow(BusinessRuleException::class);
});
