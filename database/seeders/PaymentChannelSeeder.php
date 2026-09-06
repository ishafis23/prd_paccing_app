<?php

namespace Database\Seeders;

use App\Enums\PaymentChannelType;
use App\Enums\RoleName;
use App\Models\PaymentChannel;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Contoh channel pembayaran (data demo development, bukan produksi).
 * Gambar QRIS dikosongkan; diisi lewat panel admin (upload) saat go-live.
 */
class PaymentChannelSeeder extends Seeder
{
    public function run(): void
    {
        $pencatat = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', RoleName::Owner->value))
            ->value('id');

        PaymentChannel::firstOrCreate(
            ['nama' => 'QRIS Paccing'],
            [
                'jenis' => PaymentChannelType::Qris,
                'atas_nama' => 'Paccing Official',
                'gambar' => null,
                'aktif' => true,
                'dicatat_oleh' => $pencatat,
            ]
        );

        PaymentChannel::firstOrCreate(
            ['nama' => 'BCA — 8123456789'],
            [
                'jenis' => PaymentChannelType::Bank,
                'nama_bank' => 'BCA',
                'nomor_rekening' => '8123456789',
                'atas_nama' => 'Paccing Official',
                'aktif' => true,
                'dicatat_oleh' => $pencatat,
            ]
        );
    }
}
