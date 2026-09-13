<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentChannelType;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Models\Order;
use App\Models\PaymentChannel;
use App\Models\User;
use App\Models\WorkReport;
use App\Services\PaymentChannelService;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

/*
 * Helper dibuat sebagai closure di beforeEach (bukan fungsi global) supaya
 * tidak bentrok antar file test saat full suite dijalankan.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->buatUser = function (string $role): User {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    };

    // Order selesai + token resi + laporan pengerjaan berfoto (string path, tanpa file nyata).
    $this->orderSelesai = function (User $teknisi): Order {
        $order = Order::factory()->create([
            'teknisi_id' => $teknisi->id,
            'status' => OrderStatus::Selesai,
            'metode_dipilih' => PaymentMethod::Qris,
        ]);
        $order->pastikanResiToken();

        WorkReport::factory()->create([
            'order_id' => $order->id,
            'teknisi_id' => $teknisi->id,
            'catatan_pengerjaan' => 'Pengerjaan selesai sesuai SOP.',
            'foto_sebelum' => 'work-reports/uji-sebelum.jpg',
            'foto_sesudah' => 'work-reports/uji-sesudah.jpg',
            'waktu_selesai' => now(),
        ]);

        return $order;
    };

    $this->buatChannel = function (User $by, array $overrides = []): PaymentChannel {
        return app(PaymentChannelService::class)->create(array_merge([
            'nama' => 'QRIS Resi Uji',
            'jenis' => PaymentChannelType::Qris->value,
            'atas_nama' => 'Paccing Official Uji',
            'gambar' => 'payment-channels/qris-uji.png',
        ], $overrides), $by);
    };
});

it('resi publik menampilkan detail order yang sudah selesai', function () {
    $teknisi = ($this->buatUser)(RoleName::Teknisi->value);
    $order = ($this->orderSelesai)($teknisi);

    $this->get(route('resi.show', [$order->id, $order->resi_token]))
        ->assertOk()
        ->assertSee('Resi Pengerjaan')
        ->assertSee('#' . $order->id)
        ->assertSee($order->customer->nama)
        ->assertSee($order->customer->alamat)
        ->assertSee('Qris'); // metode_dipilih ditampilkan versi label
});

it('resi belum lunas menampilkan total, status belum bayar, dan channel aktif', function () {
    $admin = ($this->buatUser)(RoleName::Admin->value);
    $teknisi = ($this->buatUser)(RoleName::Teknisi->value);
    $order = ($this->orderSelesai)($teknisi);

    ($this->buatChannel)($admin);
    app(PaymentChannelService::class)->create([
        'nama' => 'BCA Resi Uji',
        'jenis' => PaymentChannelType::Bank->value,
        'nama_bank' => 'BCA',
        'nomor_rekening' => '8123456789',
        'atas_nama' => 'Paccing Official Uji',
    ], $admin);

    $this->get(route('resi.show', [$order->id, $order->resi_token]))
        ->assertOk()
        ->assertSee('Belum bayar')
        ->assertSee('Rp' . number_format($order->total(), 0, ',', '.'))
        ->assertSee('QRIS Resi Uji')
        ->assertSee('BCA Resi Uji')
        ->assertSee('8123456789')
        ->assertSee('Tunai dapat dibayarkan langsung ke teknisi')
        ->assertSee('storage/payment-channels/qris-uji.png')
        ->assertSee('storage/work-reports/uji-sebelum.jpg')
        ->assertSee('storage/work-reports/uji-sesudah.jpg');
});

it('resi order lunas menampilkan badge lunas dan menyembunyikan daftar channel', function () {
    $admin = ($this->buatUser)(RoleName::Admin->value);
    $teknisi = ($this->buatUser)(RoleName::Teknisi->value);
    $order = ($this->orderSelesai)($teknisi);
    ($this->buatChannel)($admin);

    // §3.11: pelunasan ditahan sampai laporan diverifikasi admin.
    $laporan = WorkReport::where('order_id', $order->id)->latest('id')->first();
    app(\App\Services\OrderService::class)->verifikasiLaporan($laporan, $admin);

    app(PaymentService::class)->recordPayment($order, PaymentMethod::Qris, $order->total(), $admin);
    $order->refresh();

    $this->get(route('resi.show', [$order->id, $order->resi_token]))
        ->assertOk()
        ->assertSee('Lunas')
        ->assertDontSee('QRIS Resi Uji')
        ->assertDontSee('Tunai dapat dibayarkan');
});

it('resi dengan token salah -> 404', function () {
    $teknisi = ($this->buatUser)(RoleName::Teknisi->value);
    $order = ($this->orderSelesai)($teknisi);

    $this->get(route('resi.show', [$order->id, Str::random(40)]))
        ->assertNotFound();
});

it('resi order tanpa token -> 404', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Selesai, 'resi_token' => null]);

    $this->get(route('resi.show', [$order->id, Str::random(40)]))
        ->assertNotFound();
});
