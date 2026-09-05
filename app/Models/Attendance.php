<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'lokasi',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'tanggal' => 'date:Y-m-d',
            'jam_masuk' => 'datetime',
            'jam_keluar' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
