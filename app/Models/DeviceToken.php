<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Token push (FCM/Expo) perangkat teknisi — dipakai Fase notifikasi. */
class DeviceToken extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'app_version', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
