<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Riwayat kirim WA (dev-plan/23 §5.1). Menyimpan isi pesan final,
 * snapshot PDF (dokumen_path), status, dan error supaya bisa DIKIRIM ULANG
 * tanpa render/susun ulang (WhatsappService::kirimUlang — fase 23-B).
 */
class WaLog extends Model
{
    use HasFactory;

    public const STATUS_SUKSES = 'sukses';

    public const STATUS_GAGAL = 'gagal';

    protected $fillable = [
        'user_id',
        'wa_device_id',
        'action',
        'target',
        'jenis_kirim',
        'pesan',
        'dokumen_path',
        'dokumen_namafile',
        'dokumen_url',
        'related_type',
        'related_id',
        'status',
        'error_message',
        'dikirim_pada',
        'percobaan',
        'log_asal_id',
        'request_payload',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'dikirim_pada' => 'datetime',
            'percobaan' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(WaDevice::class, 'wa_device_id');
    }

    public function logAsal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'log_asal_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function sukses(): bool
    {
        return $this->status === self::STATUS_SUKSES;
    }

    /** Baris ini bisa dikirim ulang? (punya teks dokumen/pesan tersimpan). */
    public function bisaDikirimUlang(): bool
    {
        return filled($this->pesan)
            && in_array($this->action, ['send-teks', 'send-grup', 'send-document', 'send-massal'], true);
    }
}
