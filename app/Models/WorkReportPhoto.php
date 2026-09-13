<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu foto laporan yg terkait ke slot tertentu pada order_item (dev-plan/13
 * §3) — mis. "outdoor_proses" utk order_item berkategori Cuci AC.
 */
class WorkReportPhoto extends Model
{
    protected $fillable = [
        'work_report_id',
        'order_item_id',
        'slot',
        'path',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function workReport(): BelongsTo
    {
        return $this->belongsTo(WorkReport::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
