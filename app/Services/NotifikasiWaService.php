<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Models\WaDevice;
use App\Models\WaLog;
use App\Support\Url;
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
            'tautan_resi' => $this->tautanResi($order),
        ]) ?? WhatsappTemplate::laporan(
            (string) ($customer?->nama ?? 'Pelanggan'),
            $this->usaha->namaUsaha(),
            $this->tautanResi($order),
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

    /**
     * Pengingat servis berkala ke customer (dipanggil cron `reminder:kirim`).
     * Melempar BusinessRuleException bila tidak bisa dikirim (WA mati, nomor kosong,
     * device tak ada, template nonaktif); mengembalikan WaLog (sukses/gagal) bila dicoba kirim.
     */
    public function kirimReminderServis(ServiceReminder $reminder): WaLog
    {
        if (! $this->wa->aktif()) {
            throw new BusinessRuleException('Fitur kirim WA sedang nonaktif (WA_AKTIF=false).');
        }

        $reminder->loadMissing(['customer', 'order']);
        $customer = $reminder->customer;
        $target = $this->targetUji((string) ($customer?->no_hp ?? ''));

        if ($target === '') {
            throw new BusinessRuleException('Customer belum punya nomor WhatsApp.');
        }

        $device = $this->deviceNotifikasi();
        if ($device === null) {
            throw new BusinessRuleException('Belum ada device WA yang terhubung.');
        }

        $pesan = $this->template->teks('reminder_servis', [
            'nama' => (string) ($customer->nama ?? 'Pelanggan'),
            'nama_usaha' => $this->usaha->namaUsaha(),
            'layanan' => $reminder->order?->ringkasanLayanan() ?? 'perawatan AC',
            'interval' => (string) ($reminder->interval_bulan ?? 3),
            'tanggal_terakhir' => $reminder->order?->tanggal_jadwal?->format('d M Y') ?? '-',
        ]);

        if ($pesan === null) {
            throw new BusinessRuleException('Template pengingat servis dinonaktifkan admin.');
        }

        return $this->wa->kirimTeksSistem($device, $target, $pesan, $reminder, 'reminder_servis');
    }

    /**
     * Revisi 14 Nov 2026 poin 18 — kirim OTOMATIS setelah order selesai/lunas:
     *  1. laporan pengerjaan (PDF) bila belum pernah terkirim;
     *  2. invoice aktif: belum bayar -> tagihan, sudah bayar -> kuitansi/bukti pembayaran.
     * Idempoten (tiap dokumen sekali sukses). Dipanggil job antrean; kegagalan satu dokumen
     * tidak membatalkan yang lain.
     *
     * @return array{laporan: string, tagihan: string} hasil per dokumen: terkirim|sudah|gagal:<alasan>|dilewati:<alasan>
     */
    public function kirimOtomatisSelesai(Order $order): array
    {
        $hasil = ['laporan' => 'dilewati:nonaktif', 'tagihan' => 'dilewati:nonaktif'];

        if (! config('wa.notif_otomatis_selesai', false) || ! $this->wa->aktif()) {
            return $hasil;
        }

        $order->loadMissing('payments');

        if (! in_array($order->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)) {
            return ['laporan' => 'dilewati:order belum selesai', 'tagihan' => 'dilewati:order belum selesai'];
        }

        $pengirim = $this->pengirimOtomatis();
        if ($pengirim === null) {
            return ['laporan' => 'dilewati:tidak ada pengirim/device', 'tagihan' => 'dilewati:tidak ada pengirim/device'];
        }

        // 1) Laporan pengerjaan.
        if ($this->sudahDikirim($order, 'laporan_pengerjaan') || $order->laporan_terkirim_pada !== null) {
            $hasil['laporan'] = 'sudah';
        } else {
            $hasil['laporan'] = $this->coba(fn () => $this->kirimLaporanPengerjaan($order, $pengirim));
        }

        // 2) Invoice / kuitansi (hanya bila order sudah punya invoice aktif).
        $invoice = app(InvoiceService::class)->invoiceAktifOrder($order);
        if ($invoice === null) {
            $hasil['tagihan'] = 'dilewati:belum ada invoice';

            return $hasil;
        }

        $lunas = $order->payments->contains(fn ($p): bool => $p->status === PaymentStatus::Lunas);
        $jenis = $lunas ? 'pembayaran' : 'tagihan';

        if ($this->sudahDikirim($invoice, $jenis)) {
            $hasil['tagihan'] = 'sudah';
        } else {
            $hasil['tagihan'] = $this->coba(fn () => $lunas
                ? $this->kirimPembayaranLunas($invoice, $pengirim)
                : $this->kirimTagihan($invoice, $pengirim));
        }

        return $hasil;
    }

    /** Invoice belum dibayar + tautan, dikirim sebagai PDF. Jenis kirim `tagihan`. */
    public function kirimTagihan(Invoice $invoice, User $pengirim, ?WaDevice $device = null): WaLog
    {
        $invoice->loadMissing('customer');
        $customer = $invoice->customer;

        $target = $this->targetUji((string) ($customer?->no_hp ?? ''));
        if ($target === '') {
            throw new BusinessRuleException('Customer belum punya nomor WhatsApp.');
        }

        $device ??= $this->deviceUntuk($pengirim);
        if ($device === null) {
            throw new BusinessRuleException('Belum ada device WA yang terhubung.');
        }

        $tautan = app(InvoiceService::class)->urlPublik($invoice);

        $pesan = $this->template->teks('tagihan_pengerjaan', [
            'nama' => (string) ($customer->nama ?? 'Pelanggan'),
            'nama_usaha' => $this->usaha->namaUsaha(),
            'nomor_invoice' => (string) $invoice->nomor,
            'total' => WhatsappTemplate::rupiah((float) $invoice->total),
            'tautan_invoice' => $tautan,
        ]);

        if ($pesan === null) {
            throw new BusinessRuleException('Template tagihan dinonaktifkan admin.');
        }

        $pdf = app(InvoicePdfService::class)->render($invoice, false);
        $namafile = InvoicePdfService::namaFile($invoice, false);
        $path = $this->wa->simpanSnapshot($pdf, $namafile);

        return $this->wa->kirimDokumen($pengirim, $device, $target, $path, $pesan, $namafile, $invoice, 'tagihan');
    }

    /** Tautan resi publik (dokumentasi + pembayaran + form ulasan, poin 8). */
    private function tautanResi(Order $order): string
    {
        return Url::absolute('resi.show', ['order' => $order->id, 'token' => $order->pastikanResiToken()]);
    }

    /** Pengirim atas nama sistem: pemilik device notifikasi, atau Admin pertama. */
    private function pengirimOtomatis(): ?User
    {
        $device = $this->deviceNotifikasi();
        if ($device === null) {
            return null;
        }

        return $device->assignedUser
            ?? User::role(RoleName::Admin->value)->orderBy('id')->first();
    }

    /** @param  callable(): WaLog  $kirim */
    private function coba(callable $kirim): string
    {
        try {
            $log = $kirim();

            return $log->sukses() ? 'terkirim' : 'gagal:'.mb_substr((string) $log->error_message, 0, 120);
        } catch (BusinessRuleException $e) {
            return 'dilewati:'.mb_substr($e->getMessage(), 0, 120);
        }
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
