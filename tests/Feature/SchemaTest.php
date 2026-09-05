<?php

use Illuminate\Support\Facades\Schema;

it('membuat semua tabel fase 1 sesuai database-schema.md', function () {
    $tables = [
        'users',
        'customers',
        'service_catalogs',
        'orders',
        'work_reports',
        'work_report_materials',
        'stock_items',
        'stock_movements',
        'attendances',
        'payments',
        'service_reminders',
        'incomes',
        'expenses',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("tabel {$table} harus ada");
    }
});

it('kolom kunci pada orders sesuai skema', function () {
    expect(Schema::hasColumns('orders', [
        'customer_id', 'service_catalog_id', 'teknisi_id', 'jumlah_unit',
        'alamat_pengerjaan', 'tanggal_jadwal', 'jam_jadwal', 'status',
        'catatan_admin', 'created_by', 'deleted_at',
    ]))->toBeTrue();
});

it('kolom kunci pada payments sesuai skema', function () {
    expect(Schema::hasColumns('payments', [
        'order_id', 'metode', 'status', 'total_tagihan',
        'jumlah_dibayar', 'tanggal_bayar', 'dicatat_oleh', 'deleted_at',
    ]))->toBeTrue();
});

it('orders mendukung soft deletes', function () {
    $order = \App\Models\Order::factory()->create();
    $order->delete();

    expect(\App\Models\Order::find($order->id))->toBeNull()
        ->and(\App\Models\Order::withTrashed()->find($order->id))->not->toBeNull();
});
