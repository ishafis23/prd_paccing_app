<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        \Livewire\Livewire::actingAs($this->teknisi)
            ->test(\App\Livewire\Teknisi\LaporanPengeluaran::class)
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
}
