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
        if (! Schema::hasColumn('customer_ac_units', 'customer_address_id')) {
            Schema::table('customer_ac_units', function (Blueprint $table) {
                $table->foreignId('customer_address_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('orders', 'customer_address_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('customer_address_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            });
        }

        DB::table('customers')->select('id', 'alamat', 'latitude', 'longitude')->orderBy('id')->chunkById(200, function ($customers): void {
            $sekarang = now();

            foreach ($customers as $customer) {
                $punyaAlamat = filled($customer->alamat)
                    || $customer->latitude !== null
                    || $customer->longitude !== null;

                if (! $punyaAlamat) {
                    continue;
                }

                // Idempoten: pakai alamat yg sudah ada kalau migrasi pernah
                // jalan sebagian sebelum gagal (jangan bikin alamat dobel).
                $alamatId = DB::table('customer_addresses')
                    ->where('customer_id', $customer->id)
                    ->orderByDesc('is_utama')
                    ->orderBy('id')
                    ->value('id');

                if ($alamatId === null) {
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
                }

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

        // MySQL: FK `customer_id` butuh index pendukung — index unique
        // komposit lama kebetulan dipakai utk itu, jadi tambah index biasa
        // dulu supaya unique lama boleh dilepas.
        if (! Schema::hasIndex('customer_ac_units', 'customer_ac_units_customer_id_index')) {
            Schema::table('customer_ac_units', function (Blueprint $table) {
                $table->index('customer_id');
            });
        }

        if (Schema::hasIndex('customer_ac_units', 'customer_ac_units_customer_id_kode_unit_unique')) {
            Schema::table('customer_ac_units', function (Blueprint $table) {
                $table->dropUnique('customer_ac_units_customer_id_kode_unit_unique');
            });
        }

        if (! Schema::hasIndex('customer_ac_units', 'customer_ac_units_customer_address_id_kode_unit_unique')) {
            Schema::table('customer_ac_units', function (Blueprint $table) {
                $table->unique(['customer_address_id', 'kode_unit']);
            });
        }
    }

    public function down(): void
    {
        DB::table('customer_addresses')->delete();

        if (Schema::hasIndex('customer_ac_units', 'customer_ac_units_customer_address_id_kode_unit_unique')) {
            Schema::table('customer_ac_units', function (Blueprint $table) {
                $table->dropUnique('customer_ac_units_customer_address_id_kode_unit_unique');
            });
        }

        if (! Schema::hasIndex('customer_ac_units', 'customer_ac_units_customer_id_kode_unit_unique')) {
            Schema::table('customer_ac_units', function (Blueprint $table) {
                $table->unique(['customer_id', 'kode_unit']);
            });
        }

        if (Schema::hasColumn('orders', 'customer_address_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_address_id');
            });
        }

        if (Schema::hasColumn('customer_ac_units', 'customer_address_id')) {
            Schema::table('customer_ac_units', function (Blueprint $table) {
                $table->dropConstrainedForeignId('customer_address_id');
            });
        }
    }
};