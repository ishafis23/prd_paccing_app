<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\WaDevice;
use App\Services\WhatsappService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Menu WhatsApp → "Cek ID Group WA" — fetch daftar grup WA yang diikuti
 * nomor device terpilih (bridge getgroupwa.php), supaya admin bisa salin
 * JID grup lalu tempel ke field "ID Grup WA" pada data Customer
 * (Customer::wa_group_id, dipakai saat kirim resi ke grup — lihat
 * OrderResource action `kirimResi`).
 */
class WhatsappGroup extends Page
{
    protected static string $view = 'filament.pages.whatsapp-group';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Cek ID Group WA';

    protected static ?string $title = 'Cek ID Group WA';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $slug = 'whatsapp-group';

    /** Ringkasan device untuk ditampilkan (array agar mudah diserialisasi). */
    public array $devices = [];

    public ?int $deviceId = null;

    /** Hasil fetch terakhir: list ['id' => JID, 'subject' => nama grup]. */
    public array $groups = [];

    public ?string $pesanStatus = null;

    public bool $sudahDicek = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->muatDevices();
        $this->deviceId = $this->devices[0]['id'] ?? null;
    }

    public function muatDevices(): void
    {
        $this->devices = WaDevice::query()
            ->with('providerAccount')
            ->orderBy('device_name')
            ->get()
            ->map(fn (WaDevice $d): array => [
                'id' => $d->id,
                'device_name' => $d->device_name,
                'nomor_wa' => $d->nomor_wa,
            ])
            ->all();
    }

    /** Fetch daftar grup WA dari gateway untuk device terpilih. */
    public function cekGroup(): void
    {
        $this->validate(['deviceId' => ['required', 'integer']]);

        $device = WaDevice::find($this->deviceId);
        if (! $device) {
            Notification::make()->title('Device tidak ditemukan')->danger()->send();

            return;
        }

        $hasil = app(WhatsappService::class)->daftarGroup($device);

        $this->groups = $hasil['groups'];
        $this->sudahDicek = true;
        $this->pesanStatus = $hasil['ok']
            ? ($hasil['groups'] === [] ? 'Device ini belum tergabung di grup WA apa pun.' : count($hasil['groups']).' grup ditemukan.')
            : (string) $hasil['error'];

        Notification::make()
            ->title($hasil['ok'] ? 'Daftar grup diperbarui' : 'Gagal mengambil daftar grup')
            ->body($hasil['ok'] ? null : $hasil['error'])
            ->status($hasil['ok'] ? 'success' : 'danger')
            ->send();
    }
}
