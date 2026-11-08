<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\AuditLogResource\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleName::Admin->value);

    $catalog = ServiceCatalog::factory()->create(['jenis_layanan' => ServiceType::CuciAc, 'harga' => 170000]);
    $this->order = Order::factory()->create(['service_catalog_id' => $catalog->id, 'jumlah_unit' => 1, 'status' => OrderStatus::Dikerjakan]);
});

it('mencatat pembayaran, koreksi total, sinkron total, dan edit omset dengan sebelum/sesudah + pelaku', function () {
    app(PaymentService::class)->recordPayment($this->order->fresh(), PaymentMethod::Cash, 170000, $this->admin, null, null, null, 170000, 0);
    app(OrderService::class)->koreksiTotal($this->order->fresh(), 185000, 'Ada tambahan', $this->admin);
    app(PaymentService::class)->koreksiSplitOmset($this->order->fresh(), 100000, 85000, $this->admin);

    $aksi = AuditLog::where('order_id', $this->order->id)->pluck('aksi')->all();
    expect($aksi)->toContain(AuditLogService::PAYMENT_CATAT, AuditLogService::ORDER_KOREKSI_TOTAL, AuditLogService::PAYMENT_SINKRON_TOTAL, AuditLogService::PAYMENT_EDIT_OMSET);

    $sinkron = AuditLog::where('aksi', AuditLogService::PAYMENT_SINKRON_TOTAL)->firstOrFail();
    expect($sinkron->user_id)->toBe($this->admin->id)
        ->and($sinkron->sebelum['total_tagihan'])->toEqual(170000)
        ->and($sinkron->sesudah['total_tagihan'])->toEqual(185000);

    $edit = AuditLog::where('aksi', AuditLogService::PAYMENT_EDIT_OMSET)->firstOrFail();
    expect($edit->sebelum['omset_jasa'])->toEqual(185000)->and($edit->sesudah['omset_material'])->toEqual(85000);
});

it('audit log append-only: tidak bisa diubah atau dihapus', function () {
    $log = AuditLogService::catat('test.aksi', $this->admin, null, $this->order->id, ['a' => 1], ['a' => 2]);

    $log->update(['aksi' => 'diubah']);
    $log->delete();

    expect(AuditLog::find($log->id)?->aksi)->toBe('test.aksi');
});

it('halaman Riwayat Perubahan hanya untuk Owner/Admin dan menampilkan baris', function () {
    AuditLogService::catat(AuditLogService::PAYMENT_CATAT, $this->admin, null, $this->order->id, null, ['total_tagihan' => 1000]);

    $this->actingAs($this->admin);
    Livewire::test(ListAuditLogs::class)->assertCanSeeTableRecords(AuditLog::all());

    $teknisi = User::factory()->create();
    $teknisi->assignRole(RoleName::Teknisi->value);
    $this->actingAs($teknisi);
    expect(AuditLogResource::canViewAny())->toBeFalse();
});
