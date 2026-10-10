<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/21 §5 (Fase 4): keterangan ringkas per UNIT yang dikerjakan pada
 * satu order — "CK mana", posisi, suhu/RPM, kondisi — melengkapi foto
 * laporan. 1 baris = 1 unit. `unit_no` = urutan unit pada ORDER (1..N,
 * mengembang dari Σ jumlah baris layanan), jadi unique(order_id, unit_no);
 * order_item_id tetap disimpan agar foto/slot per kategori bisa ditautkan.
 * `kondisi` nullable: NULL = belum diisi teknisi (baris baru hanya prefill).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_unit_reports')) {
            return;
        }

        Schema::create('order_unit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('unit_no');
            $table->foreignId('customer_ac_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lokasi_label')->nullable();
            $table->string('posisi')->nullable();
            $table->string('jenis_pekerjaan')->nullable();
            $table->decimal('suhu', 5, 1)->nullable();
            $table->decimal('rpm', 8, 0)->nullable();
            $table->string('kondisi')->nullable(); // normal | tidak_normal
            $table->text('catatan_kondisi')->nullable();
            $table->string('bagian')->default('indoor'); // indoor | outdoor
            $table->timestamps();

            $table->unique(['order_id', 'unit_no']);
            $table->index('order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_unit_reports');
    }
};
