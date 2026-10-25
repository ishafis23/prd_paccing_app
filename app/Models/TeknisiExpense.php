<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknisiExpense extends Model
{
    use HasFactory;

    /** Katalog kategori pengeluaran teknisi — satu sumber, dipakai form teknisi & admin (tambah manual). */
    public const KATEGORI = ['bensin', 'makan', 'material', 'perawatan', 'gaji_honor', 'bonus', 'lainnya'];

    protected $fillable = [
        'teknisi_id',
        'order_id',
        'tanggal_input',
        'kategori',
        'nominal',
        'qty',
        'harga',
        'keterangan',
        'status',
        'approved_by',
        'catatan_approval',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_input' => 'date',
            'qty' => 'integer',
            'harga' => 'integer',
        ];
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByTeknisi(Builder $query, User|int $teknisi): Builder
    {
        $teknisiId = is_int($teknisi) ? $teknisi : $teknisi->id;
        return $query->where('teknisi_id', $teknisiId);
    }

    public function scopeByDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('tanggal_input', $date);
    }

    public function scopeByMonth(Builder $query, string $month): Builder
    {
        return $query->whereMonth('tanggal_input', substr($month, 5, 2))
                    ->whereYear('tanggal_input', substr($month, 0, 4));
    }

    public static function kategoriLabel(string $kategori): string
    {
        return match ($kategori) {
            'bensin' => '⛽ Bensin',
            'makan' => '🍜 Makan',
            'material' => '🔧 Material',
            'perawatan' => '⚙️ Perawatan',
            'gaji_honor' => '💵 Gaji/Honor',
            'bonus' => '🎁 Bonus',
            'lainnya' => '📦 Lainnya',
            default => $kategori,
        };
    }

    /** @return array<string, string> kategori => label, untuk select/filter. */
    public static function kategoriOpsi(): array
    {
        return collect(self::KATEGORI)
            ->mapWithKeys(fn (string $k): array => [$k => self::kategoriLabel($k)])
            ->all();
    }

    /**
     * Kategori yang TIDAK masuk Operasional Rutin pada Rekap Bulanan Dana.
     * Keduanya digabung ke kolom Gaji/Honor (bersama payroll_runs) supaya
     * uangnya terhitung sekali — bukan dobel, bukan hilang.
     *
     * @return array<int, string>
     */
    public static function tidakDihitungRekap(): array
    {
        return ['gaji_honor', 'bonus'];
    }
}
