<?php

namespace App\Console\Commands;

use App\Services\CustomerAddressSyncService;
use Illuminate\Console\Command;

/**
 * Backfill sekali-jalan (dev-plan/admin/03, B76) — customer lama (incl.
 * hasil Import Excel yang pakai bulk insert, tidak memicu Eloquent event)
 * dgn "Alamat Utama" terisi tapi belum punya `customer_addresses` sama
 * sekali. Idempoten, aman dijalankan berulang kali.
 */
class SyncAlamatUtama extends Command
{
    protected $signature = 'customers:sync-alamat-utama';

    protected $description = 'Backfill customer_addresses dari customers.alamat utk customer yang belum punya alamat tersimpan';

    public function handle(CustomerAddressSyncService $service): int
    {
        $dibuat = $service->backfillMissing();

        $this->info("Selesai — {$dibuat} alamat utama dibuat dari data customer lama.");

        return self::SUCCESS;
    }
}
