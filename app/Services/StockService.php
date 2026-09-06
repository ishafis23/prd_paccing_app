<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockService
{
    use RestrictsByRole;

    /**
     * Stok masuk (restock/pembelian) — aksi Admin/Owner.
     * Membuat stock_movements jenis `masuk` dan menambah stok_saat_ini.
     */
    public function masuk(StockItem $item, int $qty, User $by, ?string $keterangan = null, ?string $tanggal = null): StockMovement
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        if ($qty <= 0) {
            throw new BusinessRuleException('Jumlah stok masuk harus lebih dari 0.');
        }

        return $this->catat($item, MovementType::Masuk, $qty, $by, null, $keterangan, $tanggal);
    }

    /**
     * Stok keluar (dipakai laporan teknisi/penjualan) — dipanggil dari
     * alur submit laporan Teknisi, atau manual Admin.
     * Keputusan eksekusi B4: stok BOLEH minus (tidak diblokir).
     */
    public function keluar(StockItem $item, int $qty, User $by, ?string $referensi = null, ?string $keterangan = null): StockMovement
    {
        if ($qty <= 0) {
            throw new BusinessRuleException('Jumlah stok keluar harus lebih dari 0.');
        }

        return $this->catat($item, MovementType::Keluar, $qty, $by, $referensi, $keterangan);
    }

    /**
     * Penyesuaian stok (selisih opname). $delta boleh negatif/positif.
     * Jumlah movement disimpan BERTANDA agar stokDelta() benar.
     */
    public function penyesuaian(StockItem $item, int $delta, User $by, ?string $keterangan = null): StockMovement
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        if ($delta === 0) {
            throw new BusinessRuleException('Delta penyesuaian tidak boleh 0.');
        }

        return $this->catat($item, MovementType::Penyesuaian, $delta, $by, null, $keterangan);
    }

    private function catat(
        StockItem $item,
        MovementType $jenis,
        int $jumlah,
        User $by,
        ?string $referensi,
        ?string $keterangan,
        ?string $tanggal = null
    ): StockMovement {
        // Baris movement + perubahan stok_saat_ini harus atomik.
        return DB::transaction(function () use ($item, $jenis, $jumlah, $by, $referensi, $keterangan, $tanggal): StockMovement {
            $movement = StockMovement::create([
                'stock_item_id' => $item->id,
                'jenis' => $jenis,
                'jumlah' => $jumlah,
                'referensi' => $referensi,
                'keterangan' => $keterangan,
                'dicatat_oleh' => $by->id,
                'tanggal' => $tanggal ?? now()->toDateString(),
            ]);

            // Stok dihitung dari movement tersimpan (keluar mengurangi,
            // penyesuaian ikut tanda jumlah) supaya konsisten di semua jenis.
            $item->stok_saat_ini += $movement->stokDelta();
            $item->save();

            return $movement;
        });
    }
}
