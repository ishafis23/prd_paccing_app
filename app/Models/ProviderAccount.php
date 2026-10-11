<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun gateway WhatsApp (dev-plan/23 §5.1) — mis. wablasted.com.
 * `iduser` = identitas akun di provider; base_url_* = override endpoint
 * (bila null, WhatsappService memakai config('wa.base_url.*')).
 */
class ProviderAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'iduser',
        'base_url_qr',
        'base_url_send',
        'base_url_group',
        'base_url_document',
    ];

    public function devices(): HasMany
    {
        return $this->hasMany(WaDevice::class);
    }
}
