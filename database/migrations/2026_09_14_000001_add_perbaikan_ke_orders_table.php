<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag "menunggu konfirmasi perbaikan" (dev-plan/13 §2): teknisi lapor lewat
 * tombol "Ada Perbaikan" tanpa menghentikan alur order (beda dari
 * alasan_kendala yg mengubah status jadi terkendala). Admin lihat notice ini
 * di Orderan Harian & detail order, lalu setujui (tambah order_items baru)
 * atau tolak (flag hilang tanpa baris baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('perbaikan_menunggu_konfirmasi')->default(false)->after('alasan_kendala');
            $table->text('perbaikan_catatan')->nullable()->after('perbaikan_menunggu_konfirmasi');
            $table->decimal('perbaikan_estimasi_harga', 12, 2)->nullable()->after('perbaikan_catatan');
            $table->foreignId('perbaikan_dilaporkan_oleh')->nullable()->after('perbaikan_estimasi_harga')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('perbaikan_dilaporkan_pada')->nullable()->after('perbaikan_dilaporkan_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('perbaikan_dilaporkan_oleh');
            $table->dropColumn([
                'perbaikan_menunggu_konfirmasi',
                'perbaikan_catatan',
                'perbaikan_estimasi_harga',
                'perbaikan_dilaporkan_pada',
            ]);
        });
    }
};
