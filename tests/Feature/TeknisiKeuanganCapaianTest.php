<?php

namespace Tests\Feature;

use App\Enums\IncomeCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Livewire\Teknisi\Keuangan;
use App\Livewire\Teknisi\LaporanPengeluaran;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeknisiKeuanganCapaianTest extends TestCase
{
    use RefreshDatabase;

    private User $teknisi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();

        Role::create(['name' => RoleName::Teknisi->value, 'guard_name' => 'web']);

        $this->teknisi = User::factory()->withRole('teknisi')->create();
    }

    public function test_capaian_page_menampilkan_kartu_dan_tabel_harian(): void
    {
        $this->actingAs($this->teknisi)
            ->get('/teknisi/capaian')
            ->assertOk()
            ->assertSee('Selesai Hari Ini')
            ->assertSee('Total Selesai')
            ->assertSee('Rincian Harian')
            ->assertSee('Terkendala');
    }

    public function test_keuangan_page_menampilkan_tab_pengeluaran_dan_pendapatan(): void
    {
        $this->actingAs($this->teknisi)
            ->get('/teknisi/keuangan')
            ->assertOk()
            ->assertSee('Pengeluaran')
            ->assertSee('Pendapatan');
    }

    /**
     * Regresi: mengosongkan field angka (Harga/Qty) dulu meng-unset properti
     * bertipe int sehingga updatedHarga() -> recalcNominal() melempar
     * PropertyNotFoundException. Sekarang properti tidak diketik, jadi aman.
     */
    public function test_mengosongkan_field_angka_tidak_error_dan_nominal_jadi_nol(): void
    {
        Livewire::actingAs($this->teknisi)
            ->test(LaporanPengeluaran::class)
            ->set('qty', 2)
            ->set('harga', 5000)
            ->assertSet('nominal', 10000)
            ->set('harga', '')
            ->assertSet('nominal', 0)
            ->set('qty', '')
            ->assertSet('nominal', 0)
            ->set('harga', 3000)
            ->set('nominal', '')
            ->assertOk();
    }

    public function test_pendapatan_teknisi_hanya_menampilkan_omset_jasa(): void
    {
        $order = Order::factory()->create([
            'teknisi_id' => $this->teknisi->id,
            'status' => OrderStatus::Selesai,
        ]);
        $order->orderItems()->delete();
        $order->orderItems()->create(['nama_layanan' => 'Cuci AC', 'kategori' => ServiceType::CuciAc, 'komponen' => IncomeCategory::Jasa, 'harga' => 150000, 'jumlah' => 1]);
        $order->orderItems()->create(['nama_layanan' => 'Isi Freon', 'kategori' => ServiceType::CuciAc, 'komponen' => IncomeCategory::Material, 'harga' => 375000, 'jumlah' => 1]);

        Livewire::actingAs($this->teknisi)
            ->test(Keuangan::class)
            ->assertSet('tab', 'pengeluaran')
            ->call('setTab', 'pendapatan')
            ->assertSee('Rp 150.000')
            ->assertDontSee('525.000');
    }

    public function test_pendapatan_teknisi_mengikuti_omset_jasa_yang_dicatat_admin(): void
    {
        $order = Order::factory()->create([
            'teknisi_id' => $this->teknisi->id,
            'status' => OrderStatus::Selesai,
        ]);
        $order->orderItems()->delete();
        $order->orderItems()->create(['nama_layanan' => 'Isi Freon', 'kategori' => ServiceType::CuciAc, 'komponen' => IncomeCategory::Jasa, 'harga' => 550000, 'jumlah' => 1]);

        Payment::factory()->create([
            'order_id' => $order->id,
            'status' => PaymentStatus::Lunas,
            'total_tagihan' => 550000,
            'jumlah_dibayar' => 550000,
            'omset_jasa' => 150000,
            'omset_material' => 400000,
            'dicatat_oleh' => $this->teknisi->id,
        ]);

        Livewire::actingAs($this->teknisi)
            ->test(Keuangan::class)
            ->call('setTab', 'pendapatan')
            ->assertSee('Rp 150.000')
            ->assertDontSee('550.000');
    }

    public function test_order_tim_tempat_dia_anggota_tampil_di_riwayat_tapi_tidak_masuk_total(): void
    {
        $pic = User::factory()->withRole('teknisi')->create();

        $order = Order::factory()->create([
            'teknisi_id' => $pic->id,
            'status' => OrderStatus::Selesai,
        ]);
        $order->orderItems()->delete();
        $order->orderItems()->create(['nama_layanan' => 'Cuci AC', 'kategori' => ServiceType::CuciAc, 'komponen' => IncomeCategory::Jasa, 'harga' => 200000, 'jumlah' => 1]);

        OrderTechnician::create(['order_id' => $order->id, 'teknisi_id' => $this->teknisi->id]);

        $c = Livewire::actingAs($this->teknisi)
            ->test(Keuangan::class)
            ->call('setTab', 'pendapatan');

        // Ada riwayatnya (sebagai Anggota) ...
        $daftar = $c->get('daftarPendapatan');
        $this->assertCount(1, $daftar);
        $this->assertSame('Anggota', $daftar[0]['peran']);
        // ... tetapi tidak dihitung ke pendapatan pribadi.
        $this->assertSame(0.0, $c->get('totalPendapatanFilter'));

        // PIC melihat order yang sama, dan dihitung.
        $cPic = Livewire::actingAs($pic)->test(Keuangan::class);
        $this->assertSame('PIC', $cPic->get('daftarPendapatan')[0]['peran']);
        $this->assertSame(200000.0, $cPic->get('totalPendapatanFilter'));
    }

    public function test_omset_jasa_terhitung_di_tanggal_jadwal_bukan_tanggal_laporan_selesai(): void
    {
        $order = Order::factory()->create([
            'teknisi_id' => $this->teknisi->id,
            'status' => OrderStatus::Selesai,
            'tanggal_jadwal' => '2026-10-31',
        ]);
        $order->orderItems()->delete();
        $order->orderItems()->create(['nama_layanan' => 'Cuci AC', 'kategori' => ServiceType::CuciAc, 'komponen' => IncomeCategory::Jasa, 'harga' => 115000, 'jumlah' => 1]);
        // Laporan baru diselesaikan keesokan harinya.
        $order->forceFill(['updated_at' => '2026-11-01 09:00:00'])->saveQuietly();

        $hari = fn (string $tgl) => Livewire::actingAs($this->teknisi)
            ->test(Keuangan::class)
            ->call('setTab', 'pendapatan')
            ->set('modePendapatan', 'harian')
            ->set('filterTanggal', $tgl);

        $hari('2026-10-31')->assertSee('Order #'.$order->id)->assertSee('31 Oct 2026');
        $hari('2026-11-01')->assertDontSee('Order #'.$order->id);
    }
}
