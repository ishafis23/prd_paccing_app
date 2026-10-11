<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/23: riwayat/audit SEMUA percobaan kirim WA (sukses & gagal).
 * Baris log sengaja menyimpan `pesan` final + `dokumen_path` (snapshot PDF)
 * supaya bisa DIKIRIM ULANG tanpa menyusun pesan / render PDF lagi
 * (dev-plan/23 §5.1 & §5.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wa_logs')) {
            Schema::create('wa_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('wa_device_id')->nullable()->constrained('wa_devices')->nullOnDelete();
                $table->string('action', 30); // send-teks | send-document | send-massal
                $table->string('target');
                $table->string('jenis_kirim', 30)->nullable(); // teks|invoice|laporan|laporan-bulanan|resi|surat-jalan|reminder|tes

                // Payload agar bisa kirim ulang tanpa render/susun ulang.
                $table->text('pesan')->nullable();
                $table->string('dokumen_path')->nullable();
                $table->string('dokumen_namafile')->nullable();
                $table->string('dokumen_url')->nullable();

                // Jejak dokumen sumber (morph).
                $table->string('related_type')->nullable();
                $table->unsignedBigInteger('related_id')->nullable();

                $table->string('status', 20)->default('gagal'); // sukses | gagal
                $table->text('error_message')->nullable();
                $table->timestamp('dikirim_pada')->nullable();
                $table->unsignedInteger('percobaan')->default(1);
                $table->foreignId('log_asal_id')->nullable()->constrained('wa_logs')->nullOnDelete();

                $table->longText('request_payload')->nullable();
                $table->longText('response_payload')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['related_type', 'related_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_logs');
    }
};
