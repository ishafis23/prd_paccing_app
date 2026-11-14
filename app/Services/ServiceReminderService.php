<?php

namespace App\Services;

use App\Enums\CustomerJenis;
use App\Enums\ReminderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\ServiceReminder;
use App\Models\WaLog;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reminder servis berkala (dev-plan/revisi 14 nov 2026, poin 2a/2b):
 *  - interval dari pilihan teknisi (ditanyakan ke customer) atau default;
 *  - pengiriman WA oleh cron `reminder:kirim`, retry bila gagal, lalu tiap bulan.
 */
class ServiceReminderService
{
    public const INTERVAL_MIN = 1;

    public const INTERVAL_MAX = 12;

    public function __construct(private readonly NotifikasiWaService $notif) {}

    /**
     * Interval reminder untuk order: pilihan teknisi bila ada, selain itu default
     * (company 1 bulan, lainnya 3 bulan — dev-plan/12 §3.6).
     *
     * @return array{0: int, 1: string} [interval_bulan, sumber_interval]
     */
    public function intervalUntuk(Order $order): array
    {
        if ($order->interval_servis_bulan !== null) {
            return [(int) $order->interval_servis_bulan, 'teknisi'];
        }

        return [$order->customer?->jenis === CustomerJenis::Company ? 1 : 3, 'default'];
    }

    /**
     * Teknisi mengisi pilihan customer. Bila reminder order ini sudah ada dan belum
     * pernah terkirim, interval + tanggalnya dihitung ulang dari tanggal bayar.
     */
    public function setIntervalServis(Order $order, int $bulan): Order
    {
        if ($bulan < self::INTERVAL_MIN || $bulan > self::INTERVAL_MAX) {
            throw new BusinessRuleException(sprintf('Interval harus %d–%d bulan.', self::INTERVAL_MIN, self::INTERVAL_MAX));
        }

        $order->update(['interval_servis_bulan' => $bulan]);

        $reminder = ServiceReminder::query()->where('order_id', $order->id)->first();

        if ($reminder !== null && $reminder->jumlah_terkirim === 0) {
            $basis = CarbonImmutable::parse($order->latestPayment?->tanggal_bayar ?? $reminder->created_at);

            $reminder->update([
                'interval_bulan' => $bulan,
                'sumber_interval' => 'teknisi',
                'tanggal_servis_berikutnya' => $basis->addMonthsNoOverflow($bulan)->toDateString(),
                'status_kirim' => ServiceReminder::KIRIM_MENUNGGU,
                'percobaan' => 0,
                'error_terakhir' => null,
            ]);
        }

        return $order->fresh();
    }

    /**
     * Proses semua reminder yang jatuh tempo (dipanggil cron).
     *
     * @return array{terkirim: int, gagal: int, dilewati: int, ditutup: int}
     */
    public function prosesAntrean(): array
    {
        $hasil = ['terkirim' => 0, 'gagal' => 0, 'dilewati' => 0, 'ditutup' => 0];

        ServiceReminder::query()->antreanKirim()->orderBy('id')->each(function (ServiceReminder $r) use (&$hasil): void {
            $hasil[$this->proses($r)]++;
        });

        return $hasil;
    }

    /** @return 'terkirim'|'gagal'|'dilewati'|'ditutup' */
    public function proses(ServiceReminder $reminder): string
    {
        // Customer sudah punya reminder lebih baru (order lunas lagi) -> yang lama ditutup.
        $adaYangBaru = ServiceReminder::query()
            ->where('customer_id', $reminder->customer_id)
            ->where('id', '>', $reminder->id)
            ->exists();

        if ($adaYangBaru) {
            $reminder->update(['status_notice' => ReminderStatus::Selesai]);

            return 'ditutup';
        }

        try {
            $log = $this->notif->kirimReminderServis($reminder);
        } catch (BusinessRuleException $e) {
            // Prasyarat belum terpenuhi (WA mati, nomor/device kosong): bukan kegagalan kirim,
            // percobaan tidak dihitung; dicoba lagi saat cron berikutnya.
            $reminder->update([
                'terakhir_dicoba_at' => now(),
                'error_terakhir' => mb_substr($e->getMessage(), 0, 255),
            ]);

            return 'dilewati';
        } catch (Throwable $e) {
            return $this->catatGagal($reminder, $e->getMessage());
        }

        if ($log->status === WaLog::STATUS_SUKSES) {
            $reminder->update([
                'status_kirim' => ServiceReminder::KIRIM_TERKIRIM,
                'percobaan' => 0,
                'jumlah_terkirim' => $reminder->jumlah_terkirim + 1,
                'terakhir_dicoba_at' => now(),
                'terkirim_at' => now(),
                'error_terakhir' => null,
                'tahap' => 'bulanan',
                // Belum order lagi -> ingatkan lagi bulan depan.
                'tanggal_servis_berikutnya' => CarbonImmutable::today()->addMonthsNoOverflow(1)->toDateString(),
            ]);

            return 'terkirim';
        }

        return $this->catatGagal($reminder, (string) ($log->error_message ?: 'Gateway WA gagal mengirim.'));
    }

    private function catatGagal(ServiceReminder $reminder, string $pesan): string
    {
        $reminder->update([
            'status_kirim' => ServiceReminder::KIRIM_GAGAL,
            'percobaan' => $reminder->percobaan + 1,
            'terakhir_dicoba_at' => now(),
            'error_terakhir' => mb_substr($pesan, 0, 255),
        ]);

        return 'gagal';
    }
}
