<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknisiExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'teknisi_id',
        'tanggal_input',
        'kategori',
        'nominal',
        'keterangan',
        'status',
        'approved_by',
        'catatan_approval',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_input' => 'date',
        ];
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
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
}
