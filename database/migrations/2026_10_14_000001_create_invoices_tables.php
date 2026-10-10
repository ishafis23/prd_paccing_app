<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * dev-plan/21 §7 (Fase 6): Invoice per order (boleh banyak order per invoice)
 * + kolom bank pada Info Usaha. Bank di invoice adalah SNAPSHOT saat dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('nomor', 30)->unique(); // INV-YYYYMM-0001
                $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
                $table->date('tanggal');
                $table->date('jatuh_tempo')->nullable();
                $table->string('status', 20)->default('draft'); // draft | terkirim | lunas | batal
                $table->text('catatan')->nullable();
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->decimal('total', 14, 2)->default(0);
                $table->string('bank_nama')->nullable();
                $table->string('bank_rekening')->nullable();
                $table->string('bank_atas_nama')->nullable();
                $table->string('token', 64)->nullable()->unique();
                $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['status', 'tanggal']);
            });
        }

        if (! Schema::hasTable('invoice_orders')) {
            Schema::create('invoice_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['invoice_id', 'order_id']);
            });
        }

        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
                $table->string('nama');
                $table->text('deskripsi')->nullable();
                $table->decimal('jumlah', 10, 2)->default(1);
                $table->decimal('harga', 14, 2)->default(0);
                $table->decimal('subtotal', 14, 2)->default(0);
                $table->unsignedInteger('urutan')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('business_infos', function (Blueprint $table) {
            if (! Schema::hasColumn('business_infos', 'bank_nama')) {
                $table->string('bank_nama')->nullable();
            }
            if (! Schema::hasColumn('business_infos', 'bank_rekening')) {
                $table->string('bank_rekening')->nullable();
            }
            if (! Schema::hasColumn('business_infos', 'bank_atas_nama')) {
                $table->string('bank_atas_nama')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_infos', function (Blueprint $table) {
            foreach (['bank_nama', 'bank_rekening', 'bank_atas_nama'] as $kolom) {
                if (Schema::hasColumn('business_infos', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoice_orders');
        Schema::dropIfExists('invoices');
    }
};
