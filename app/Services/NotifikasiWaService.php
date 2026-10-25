<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Support\WhatsappTemplate;
use Illuminate\Database\Eloquent\Model;

/**
 * Orkestrasi notifikasi WA otomatis ke CUSTOMER (dev-plan/27):
 *  - `kirimBerangkat()`        — saat teknisi mulai berangkat ke lokasi.
 *  - `kirimPembayaranLunas()`  — notifikasi pembayaran diterima + invoice (dipicu admin).
 *
 * Semua teks diambil dari template yang dikelola admin (WaTemplateService);
 * pengiriman melewati WhatsAppService (pre-flight + rate limit + wa_logs).
 */
class NotifikasiWaService
{
    public function __construct(
        private readonly WhatsappService $wa,
        private readonly WaTemplateService $template,
        private readonly BusinessInfoService $usaha,
    ) {}

    /** Device pengirim notifikasi: yang ditandai, atau device terhubung pertama. */
    public function deviceNotifikasi(): ?WaDevice
    {
        return WaDevice::query()->where('notif_default', true)->orderBy('id')->first()
            ?? WaDevice::query()->where('connection_status', WaDevice::KONEKSI_TERHUBUNG)->orderBy('id')->first();
    }

    /**
     * Notifikasi "teknisi berangkat" ke customer. Idempoten (sekali sukses per
     * order). Mengembalikan null bila dilewati (toggle mati, nomor kosong,
     * device tak ada, template nonaktif).
     */
    public function kirimBerangkat(Order $order, User $teknisi): ?WaLog
    {
        if (! config('wa.notif_berangkat', true) || ! $this->wa->aktif()) {
            return null;
        }

        if ($this->sudahDikirim($order, 'berangkat')) {
            return null;
        }

        $order->loadMissing(['customer', 'customerAddress']);
        $customer = $order->customer;
        $target = $this->targetUji((string) ($customer?->no_hp ?? ''));

        if ($target === '') {
            return null;
        }

        $device = $this->deviceNotifikasi();
        if ($device === null) {
            return null;
        }

        $pesan = $this->template->teks('berangkat', [
            'nama' => (string) ($customer->nama ?? 'Pelanggan'),
            'nama_usaha' => $this->usaha->namaUsaha(),
            'layanan' => $order->ringkasanLayanan(),
            'alamat' => (string) ($order->alamat_pengerjaan ?: $order->customerAddress?->labelTampil()),
            'teknisi' => $teknisi->name,
            'jadwal' => trim(($order->tanggal_jadwal?->format('d M Y') ?? '').' '.($order->jam_jadwal ?? '')),
        ]);

        if ($pesan === null) {
            return null; // template dinonaktifkan admin
        }

        return $this->wa->kirimTeksSistem(
            $device,
            $target,
            $pesan,
            $order,
            'berangkat',
            $device->assignedUser ?? $teknisi,
        );
    }

    /**
     * Notifikasi "pembayaran diterima" + PDF invoice, dipicu ADMIN dari
     * halaman Invoice. Melempar BusinessRuleException bila tidak bisa kirim.
     */
    public function kirimPembayaranLunas(Invoice $invoice, User $admin, ?WaDevice $device = null): WaLog
    {
        $invoice->loadMissing('customer');
        $customer = $invoice->customer;

        $target = $this->targetUji((string) ($customer?->no_hp ?? ''));
        if ($target === '') {
            throw new BusinessRuleException('Customer belum punya nomor WhatsApp.');
        }

        $device ??= $this->deviceUntuk($admin);
        if ($device === null) {
            throw new BusinessRuleException('Belum ada device WA. Sambungkan device atau tandai "Device Notifikasi Otomatis" dulu.');
        }

        $tautan = app(InvoiceService::class)->urlPublik($invoice);

        $pesan = $this->template->teks('pembayaran_lunas', [
            'nama' => (string) ($customer->nama ?? 'Pelanggan'),
            'nama_usaha' => $this->usaha->namaUsaha(),
            'nomor_invoice' => (string) $invoice->nomor,
            'total' => WhatsappTemplate::rupiah((float) $invoice->total),
            'tautan_invoice' => $tautan,
        ]) ?? WhatsappTemplate::invoice($invoice, $this->usaha->namaUsaha(), $tautan);

        $pdf = app(InvoicePdfService::class)->render($invoice, false);
        $namafile = InvoicePdfService::namaFile($invoice, false);
        $path = $this->wa->simpanSnapshot($pdf, $namafile);

        return $this->wa->kirimDokumen($admin, $device, $target, $path, $pesan, $namafile, $invoice, 'pembayaran');
    }

    /**
     * Kirim laporan pengerjaan PDF ke customer dan catat pengiriman yang
     * benar-benar berhasil. Aksi ini dipicu manual oleh Admin/Finance dari
     * halaman order, sehingga kegagalan pengiriman tidak boleh menandai
     * laporan sebagai telah terkirim.
     */
    public function kirimLaporanPengerjaan(Order $order, User $admin): WaLog
    {
        if (! config('wa.notif_laporan', true) || ! $this->wa->aktif()) {
            throw new BusinessRuleException('Notifikasi WhatsApp laporan tidak aktif.');
        }

        $order->loadMissing('customer');
        $customer = $order->customer;
        $target = $this->targetUji((string) ($customer?->no_hp ?? ''));

        if ($target === '') {
            throw new BusinessRuleException('Customer belum punya nomor WhatsApp.');
        }

        $device = $this->deviceUntuk($admin);
        if ($device === null) {
            throw new BusinessRuleException('Belum ada device WA. Sambungkan device atau tandai "Device Notifikasi Otomatis" dulu.');
        }

        $dokumen = app(LaporanPengerjaanService::class)->dokumenOrder($order);
        $pdf = app(LaporanPdfService::class)->render($dokumen);
        $namafile = 'Laporan-Pengerjaan-Order-'.$order->id.'.pdf';
        $path = $this->wa->simpanSnapshot($pdf, $namafile);

        $pesan = $this->template->teks('laporan_pengerjaan', [
            'nama' => (string) ($customer?->nama ?? 'Pelanggan'),
            'nama_usaha' => $this->usaha->namaUsaha(),
            'layanan' => $order->ringkasanLayanan(),
            'nomor_order' => '#'.$order->id,
        ]) ?? WhatsappTemplate::laporan(
            (string) ($customer?->nama ?? 'Pelanggan'),
            $this->usaha->namaUsaha(),
        );

        $log = $this->wa->kirimDokumen($admin, $device, $target, $path, $pesan, $namafile, $order, 'laporan_pengerjaan');

        if ($log->sukses()) {
            $order->update([
                'laporan_terkirim_pada' => now(),
                'laporan_terkirim_oleh' => $admin->id,
            ]);
        }

        return $log;
    }

    /** Device milik user (bila terhubung), fallback ke device notifikasi. */
    private function deviceUntuk(?User $user): ?WaDevice
    {
        $milik = $user?->waDevice;

        if ($milik !== null && $milik->terhubung()) {
            return $milik;
        }

        return $this->deviceNotifikasi();
    }

    /**
     * MODE UJI: bila `wa.test_target` diisi, SEMUA notifikasi otomatis
     * diarahkan ke nomor itu (mengabaikan nomor customer). Kosong = nomor asli.
     */
    private function targetUji(string $nomorCustomer): string
    {
        $uji = trim((string) config('wa.test_target'));

        return $uji !== '' ? $uji : trim($nomorCustomer);
    }

    /** Sudah pernah dikirim SUKSES untuk dokumen + jenis ini? (idempoten) */
    public function sudahDikirim(Model $related, string $jenis): bool
    {
        return WaLog::query()
            ->where('related_type', $related::class)
            ->where('related_id', $related->getKey())
            ->where('jenis_kirim', $jenis)
            ->where('status', WaLog::STATUS_SUKSES)
            ->exists();
    }
}
