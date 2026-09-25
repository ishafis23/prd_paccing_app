<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game2Setting extends Model
{
    protected $table = 'game2_settings';

    protected $fillable = [
        'deadline_time',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public static function getDeadlineTime(): string
    {
        $setting = self::first();
        return $setting?->deadline_time ?? '08:30:00';
    }

    public static function isActive(): bool
    {
        $setting = self::first();
        return $setting?->active ?? true;
    }
}
