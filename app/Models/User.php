<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function technicianOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'teknisi_id');
    }

    public function createdOrders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function workReports(): HasMany
    {
        return $this->hasMany(WorkReport::class, 'teknisi_id');
    }

    public function recordedStockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'dicatat_oleh');
    }

    public function isOwner(): bool
    {
        return $this->hasRole(RoleName::Owner->value);
    }
}
