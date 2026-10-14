<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Device WhatsApp = satu nomor WA tersambung (dev-plan/23 §5.1).
 *
 * `connection_status` disinkron dari pre-flight get-qr; `assigned_user_id`
 * (pemakai device) dijaga konsisten dengan `users.wa_device_id` lewat event
 * saved/deleted di bawah.
 */
class WaDevice extends Model
{
    use HasFactory;

    public const STATUS_TERSEDIA = 'tersedia';

    public const STATUS_TERPAKAI = 'terpakai';

    public const STATUS_TERPUTUS = 'terputus';

    public const KONEKSI_TERHUBUNG = 'terhubung';

    public const KONEKSI_BELUM = 'belum_terhubung';

    protected $fillable = [
        'provider_account_id',
        'device_name',
        'nomor_wa',
        'status',
        'connection_status',
        'notif_default',
        'assigned_user_id',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
            'notif_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Jaga konsistensi users.wa_device_id dengan assigned_user_id.
        static::saved(function (WaDevice $device): void {
            if ($device->assigned_user_id) {
                User::query()->whereKey($device->assigned_user_id)
                    ->update(['wa_device_id' => $device->id]);
            }

            // User lain yang masih menunjuk device ini tapi bukan pemakainya.
            User::query()
                ->where('wa_device_id', $device->id)
                ->when($device->assigned_user_id, fn ($q) => $q->whereKeyNot($device->assigned_user_id))
                ->update(['wa_device_id' => null]);

            // Hanya satu device yang boleh jadi pengirim notifikasi default.
            if ($device->notif_default) {
                static::query()->whereKeyNot($device->id)->update(['notif_default' => false]);
            }
        });

        static::deleted(function (WaDevice $device): void {
            User::query()->where('wa_device_id', $device->id)
                ->update(['wa_device_id' => null]);
        });
    }

    public function providerAccount(): BelongsTo
    {
        return $this->belongsTo(ProviderAccount::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function terhubung(): bool
    {
        return $this->connection_status === self::KONEKSI_TERHUBUNG;
    }
}
