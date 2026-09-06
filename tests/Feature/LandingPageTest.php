<?php

use App\Models\BusinessInfo;
use App\Models\ServiceCatalog;

it('halaman utama menampilkan landing page Paccing (bukan welcome Laravel)', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Paccing')
        ->assertSee('Cuci AC')
        ->assertDontSee('Laravel');
});

it('landing page menampilkan layanan aktif dari katalog', function () {
    $katalog = ServiceCatalog::factory()->create([
        'harga' => 150000,
        'aktif' => true,
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('Rp 150.000');
});

it('landing page tidak mengarahkan pengunjung publik ke login internal (teknisi/admin)', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee(route('login'))
        ->assertDontSee('Masuk ke Sistem');
});

it('landing page menampilkan CTA publik (WhatsApp / kontak) saat info usaha lengkap', function () {
    BusinessInfo::factory()->create([
        'kontak_wa' => '6281234567890',
    ]);

    $this->get('/')
        ->assertSuccessful()
        ->assertSee('wa.me/6281234567890')
        ->assertSee('Pesan via WhatsApp')
        ->assertDontSee(route('login'));
});

it('halaman login internal tetap bisa diakses staf (regresi)', function () {
    $this->get('/login')->assertSuccessful();
});
