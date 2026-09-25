<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'type',
        'unit_number',
        'photo_position',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'unit_number' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
