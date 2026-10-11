<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\WaDevice;
use App\Services\BusinessInfoService;
use App\Services\WhatsappService;
use App\Support\WhatsappTemplate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * dev-plan/23 fase 23-A: menu WhatsApp → "Perangkat & Tes WA".
 * Sambungkan device (ambil QR/barcode), cek status koneksi nyata, dan
 * putuskan/reset sesi (scan ulang). Uji kirim teks/PDF menyusul di 23-B.
 */
class WhatsappDevice extends Page
{
    protected static string $view = 'filament.pages.whatsapp-device';

    protected static ?string $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Perangkat & Tes WA';

    protected static ?string $title = 'Perangkat & Tes WA';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $slug = 'whatsapp-device';

    /** Ringkasan device untuk ditampilkan (array agar mudah diserialisasi). */
    public array $devices = [];

    public ?int $deviceId = null;

    public ?string $qrImage = null;

    public ?string $qrMessage = null;

    public ?string $statusMessage = null;

    public bool $gatewayAktif = false;

    // --- Uji kirim teks (fase 23-B) ---
    public ?int $tesDeviceId = null;

    public string $tesTarget = '';

    public string $tesPesan = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->gatewayAktif = app(WhatsappService::class)->aktif();
        $this->tesPesan = WhatsappTemplate::tes(app(BusinessInfoService::class)->namaUsaha());
        $this->muatDevices();

        // Default device uji = device pertama.
        $this->tesDeviceId = $this->devices[0]['id'] ?? null;
        if ($this->tesDeviceId) {
            $this->tesTarget = (string) (WaDevice::find($this->tesDeviceId)?->nomor_wa ?? '');
        }
    }

    public function muatDevices(): void
    {
        $this->devices = WaDevice::query()
            ->with(['providerAccount', 'assignedUser'])
            ->orderBy('device_name')
            ->get()
            ->map(fn (WaDevice $d): array => [
                'id' => $d->id,
                'device_name' => $d->device_name,
                'nomor_wa' => $d->nomor_wa,
                'provider' => $d->providerAccount?->label,
                'koneksi' => $d->connection_status,
                'terhubung' => $d->terhubung(),
                'pemakai' => $d->assignedUser?->name,
                'last_checked' => optional($d->last_checked_at)->diffForHumans(),
            ])
            ->all();
    }

    /** Ambil QR / status koneksi device — tampilkan gambar QR bila belum terhubung. */
    public function ambilQr(int $id): void
    {
        $device = WaDevice::find($id);
        if (! $device) {
            return;
        }

        $this->deviceId = $id;
        $this->qrImage = null;
        $this->statusMessage = null;

        $hasil = app(WhatsappService::class)->qr($device);

        $this->qrImage = $hasil['image'] ?? null;
        $this->qrMessage = $this->pesanQr($hasil);
        $this->muatDevices();
    }

    /** Pre-flight: paksa cek status koneksi nyata & sinkronkan kolom device. */
    public function cekStatus(int $id): void
    {
        $device = WaDevice::find($id);
        if (! $device) {
            return;
        }

        $this->deviceId = $id;
        $this->qrImage = null;

        $hasil = app(WhatsappService::class)->status($device);

        $this->qrMessage = $this->pesanQr($hasil);
        $this->statusMessage = $hasil['terhubung']
            ? 'Device '.$device->device_name.' terhubung.'
            : 'Device '.$device->device_name.' BELUM terhubung.';

        Notification::make()
            ->title($hasil['terhubung'] ? 'Device terhubung' : 'Device belum terhubung')
            ->status($hasil['terhubung'] ? 'success' : 'warning')
            ->send();

        $this->muatDevices();
    }

    /** Putuskan/reset sesi (scan ulang), lalu cek status nyata sesudahnya. */
    public function putuskan(int $id): void
    {
        $device = WaDevice::find($id);
        if (! $device) {
            return;
        }

        $this->deviceId = $id;
        $this->qrImage = null;

        $service = app(WhatsappService::class);
        $reset = $service->resetSession($device);

        // Sumber kebenaran tetap get-qr sesudah reset (konsep arm_app).
        $hasil = $service->status($device);

        if ($hasil['terhubung']) {
            $this->statusMessage = 'Reset dikirim, tetapi device masih terhubung. Coba lagi, atau pastikan sesi benar-benar logout.';
        } else {
            $this->statusMessage = 'Sesi device diputus. Ambil QR untuk menyambungkan ulang.';
        }

        $this->qrMessage = $this->pesanQr($hasil);

        Notification::make()
            ->title(($reset['ok'] ?? false) ? 'Reset dikirim' : 'Reset gagal')
            ->body($reset['error'] ?? $reset['message'] ?? null)
            ->status(($reset['ok'] ?? false) ? 'success' : 'danger')
            ->send();

        $this->muatDevices();
    }

    /** Uji kirim pesan teks ke nomor yang diketik bebas (default nomor device). */
    public function kirimTes(): void
    {
        $this->validate([
            'tesDeviceId' => ['required', 'integer'],
            'tesTarget' => ['required', 'string'],
            'tesPesan' => ['required', 'string', 'max:4000'],
        ]);

        $device = WaDevice::find($this->tesDeviceId);
        if (! $device) {
            Notification::make()->title('Device tidak ditemukan')->danger()->send();

            return;
        }

        try {
            $log = app(WhatsappService::class)->kirimTeks(
                auth()->user(),
                $device,
                $this->tesTarget,
                $this->tesPesan,
                null,
                'tes',
            );

            Notification::make()
                ->title($log->sukses() ? 'Pesan tes terkirim' : 'Gagal mengirim')
                ->body($log->sukses()
                    ? 'Ke '.$log->target.' — tercatat di Riwayat Kirim WA.'
                    : (string) $log->error_message)
                ->status($log->sukses() ? 'success' : 'danger')
                ->send();
        } catch (BusinessRuleException $e) {
            Notification::make()->title('Tidak bisa kirim')->body($e->getMessage())->danger()->send();
        }

        $this->muatDevices();
    }

    private function pesanQr(array $hasil): string
    {
        if (empty($hasil['ok'])) {
            // Tampilkan pesan asli gateway dulu (mis. "Parameter device dan
            // iduser wajib diisi.") agar penyebabnya tidak tertutup pesan umum.
            $pesan = trim((string) ($hasil['error'] ?? ''));
            if ($pesan === '') {
                $pesan = trim((string) ($hasil['message'] ?? ''));
            }
            if ($pesan === '') {
                $pesan = 'Gagal menghubungi gateway (HTTP '.(int) ($hasil['status_code'] ?? 0).').';
            }

            return $pesan;
        }

        if (! empty($hasil['has_qr'])) {
            return 'Scan QR di WhatsApp HP: Perangkat Tertaut → Tautkan Perangkat. QR berlaku sebentar — ambil ulang bila kedaluwarsa.';
        }

        return 'Device sudah terhubung (tidak ada QR dikembalikan).';
    }
}
