<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penautan multi-alamat (dev-plan/14):
 * - `customer_ac_units.customer_address_id` — unit AC kini milik SATU alamat
 *   customer (bukan langsung customer). `customer_id` TETAP ada utk
 *   kompatibilitas/filter cepat.
 * - `orders.customer_address_id` — alamat mana yg dikerjakan order tsb
 *   (`alamat_pengerjaan` tetap snapshot teks utk surat jalan/teknisi).
 * - Unique guard unit digeser dari per-customer ke per-alamat: kode_unit
 *   boleh sama di alamat berbeda (gedung A & B boleh sama-sama "Ruang Guru"),
 *   tapi tidak boleh kembar dalam satu alamat.
 *
 * Backfill: customer yg sudah punya alamat/koordinat -> dibuatkan satu
 * `customer_addresses` "Alamat Utama" (is_utama = true); seluruh unit AC &
 * order lama customer tsb ditautkan ke alamat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_ac_units', function (Blueprint $table) {
            $table->foreignId('customer_address_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_address_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        DB::table('customers')->select('id', 'alamat', 'latitude', 'longitude')->orderBy('id')->chunkById(200, function ($customers): void {
            $sekarang = now();

            foreach ($customers as $customer) {
                $punyaAlamat = filled($customer->alamat)
                    || $customer->latitude !== null
                    || $customer->longitude !== null;

                if (! $punyaAlamat) {
                    continue;
                }

                $alamatId = DB::table('customer_addresses')->insertGetId([
                    'customer_id' => $customer->id,
                    'nama_lokasi' => 'Alamat Utama',
                    'alamat' => $customer->alamat,
                    'latitude' => $customer->latitude,
                    'longitude' => $customer->longitude,
                    'is_utama' => true,
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ]);

                DB::table('customer_ac_units')
                    ->where('customer_id', $customer->id)
                    ->whereNull('customer_address_id')
                    ->update(['customer_address_id' => $alamatId]);

                DB::table('orders')
                    ->where('customer_id', $customer->id)
                    ->whereNull('customer_address_id')
                    ->update(['customer_address_id' => $alamatId]);
            }
        });

        Schema::table('customer_ac_units', function (Blueprint $table) {
            $table->dropUnique('customer_ac_units_customer_id_kode_unit_unique');
            $table->unique(['customer_address_id', 'kode_unit']);
        });
    }

    public function down(): void
    {
        DB::table('customer_addresses')->delete();

        Schema::table('customer_ac_units', function (Blueprint $table) {
            $table->dropUnique('customer_ac_units_customer_address_id_kode_unit_unique');
            $table->unique(['customer_id', 'kode_unit']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_address_id');
        });

        Schema::table('customer_ac_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_address_id');
        });
    }
};