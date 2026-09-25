<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeknisExpense extends Model
{
    use HasFactory;

    protected $table = 'teknis_expenses';

    protected $fillable = [
        'teknisi_id',
        'kategori',
        'nominal',
        'keterangan',
        'bukti_file',
        'status',
        'approved_by',
        'catatan_approval',
        'tanggal_input',
        'tanggal_approve',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'tanggal_input' => 'date:Y-m-d',
            'tanggal_approve' => 'datetime',
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

    public function scopeByTeknisi(Builder $query, int $teknisiId): Builder
    {
        return $query->where('teknisi_id', $teknisiId);
    }

    public function scopeByDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('tanggal_input', $date);
    }

    public function scopeByMonth(Builder $query, string $month): Builder
    {
        return $query->whereMonth('tanggal_input', date('m', strtotime($month)))
            ->whereYear('tanggal_input', date('Y', strtotime($month)));
    }
}
