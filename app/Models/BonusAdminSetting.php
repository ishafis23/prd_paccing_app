<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan bonus Admin/HRD/Finance (revisi 17 Nov 2026 poin 24) — baris
 * tunggal. Default: 1% dari omset jasa, minimal omset jasa Rp 50.000.000,
 * tiap penerima mendapat persen itu (`per_orang`).
 */
class BonusAdminSetting extends Model
{
    public const MODE_PER_ORANG = 'per_orang';

    public const MODE_DIBAGI_RATA = 'dibagi_rata';

    protected $fillable = ['aktif', 'persen', 'minimal_omset_jasa', 'mode', 'peran'];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'persen' => 'decimal:2',
            'minimal_omset_jasa' => 'decimal:2',
            'peran' => 'array',
        ];
    }

    public static function saatIni(): self
    {
        return self::query()->first() ?? self::create([
            'aktif' => true,
            'persen' => 1,
            'minimal_omset_jasa' => 50000000,
            'mode' => self::MODE_PER_ORANG,
            'peran' => [RoleName::Admin->value, RoleName::Hr->value, RoleName::Finance->value],
        ]);
    }

    /** @return array<int, string> */
    public function peranPenerima(): array
    {
        return $this->peran ?: [RoleName::Admin->value, RoleName::Hr->value, RoleName::Finance->value];
    }
}
