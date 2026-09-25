<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, Notifiable;

    /** Role backoffice yang boleh masuk panel Filament (bukan Teknisi — mereka pakai UI mobile terpisah). */
    private const PANEL_ROLES = [
        RoleName::Owner,
        RoleName::Admin,
        RoleName::Finance,
        RoleName::Hr,
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->status !== UserStatus::Aktif) {
            return false; // B20: user nonaktif tidak bisa masuk panel.
        }

        return $this->hasAnyRole(array_map(fn (RoleName $r) => $r->value, self::PANEL_ROLES));
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'status',
        'last_latitude',
        'last_longitude',
        'last_location_at',
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
            'last_latitude' => 'decimal:7',
            'last_longitude' => 'decimal:7',
            'last_location_at' => 'datetime',
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

    public function dailyAttendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }

    public function technicianIncentives(): HasMany
    {
        return $this->hasMany(TechnicianIncentive::class);
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

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin->value);
    }
}
