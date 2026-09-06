<?php

namespace Database\Seeders;

use App\Models\BerandaSetting;
use App\Models\BusinessInfo;
use App\Models\ServiceCatalog;
use Illuminate\Database\Seeder;

class LandingPageSeeder extends Seeder
{
    /**
     * Konten landing page (B38) untuk development lokal — Info Usaha,
     * Pengaturan Beranda, & kurasi Layanan Beranda dari katalog yang sudah
     * dibuat ServiceCatalogSeeder.
     *
     * Data usaha di sini masih contoh untuk kebutuhan tampilan; wajib
     * diganti data asli lewat menu Manajemen → "Info Usaha" sebelum go-live.
     */
    public function run(): void
    {
        BusinessInfo::updateOrCreate(['id' => 1], [
            'nama_usaha' => 'Paccing Official',
            'alamat' => 'Jl. Sultan Alauddin No. 88, Makassar, Sulawesi Selatan',
            'kontak_wa' => '6281234567890',
            'email' => 'info@paccingofficial.id',
            'nama_pemilik' => 'Andi Paccing',
        ]);

        BerandaSetting::updateOrCreate(['id' => 1], [
            'jam_operasional' => 'Senin–Sabtu, 08.00–17.00',
            'sosmed_instagram' => 'https://instagram.com/paccingofficial',
            'sosmed_facebook' => null,
            'tampil_layanan' => true,
            'tampil_cara_kerja' => true,
            'tampil_area' => true,
            'tampil_peta' => true,
        ]);

        $deskripsi = [
            ['cuci_ac', 'split', '1 PK', 'Cuci steam menyeluruh unit indoor & outdoor, cek freon ringan.', 1],
            ['cuci_ac', 'split', '1.5-2 PK', 'Cuci steam untuk AC ruang lebih luas, hasil dingin maksimal.', 2],
            ['cuci_ac', 'standing', 's/d 3 PK', 'Perawatan rutin AC standing/floor, bersih dari jamur & debu.', 3],
            ['service_ac', 'split', '1 PK', 'Cek kompresor, tekanan freon, dan kebocoran — AC dingin lagi.', 4],
            ['service_ac', 'split', '1.5-2 PK', 'Service menyeluruh untuk AC yang kurang dingin atau berisik.', 5],
            ['pengadaan_ac', 'split', '1 PK', 'Unit baru bergaransi resmi, termasuk pemasangan standar.', 6],
            ['pengadaan_ac', 'split', '1.5 PK', 'Pilihan hemat energi untuk ruang kerja atau kamar utama.', 7],
            ['pengadaan_ac', 'standing', '2 PK', 'AC standing untuk ruang tamu/aula, termasuk instalasi.', 8],
        ];

        foreach ($deskripsi as [$jenis, $unit, $pk, $teks, $urutan]) {
            ServiceCatalog::query()
                ->where('jenis_layanan', $jenis)
                ->where('jenis_unit', $unit)
                ->where('pk', $pk)
                ->update([
                    'deskripsi' => $teks,
                    'tampil_beranda' => true,
                    'urutan_beranda' => $urutan,
                ]);
        }
    }
}
