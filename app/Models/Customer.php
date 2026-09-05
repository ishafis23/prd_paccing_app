<?php

namespace App\Models;

use App\Enums\CustomerArea;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama',
        'no_hp',
        'alamat',
        'area',
        'sumber_lead',
        'status',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'area' => CustomerArea::class,
            'sumber_lead' => LeadSource::class,
            'status' => CustomerStatus::class,
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function serviceReminders(): HasMany
    {
        return $this->hasMany(ServiceReminder::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CustomerStatus::Aktif->value);
    }
}
