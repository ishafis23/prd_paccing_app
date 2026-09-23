<?php

namespace App\Services;

use App\Enums\CustomerJenis;
use App\Enums\IncomeCategory;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReminderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Income;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ServiceReminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    use RestrictsByRole;

    /**
     * Catat pembayaran order (Admin/Finance/Owner).
     * - Total tagihan default mengikuti service_catalog (keputusan B6),
     *   tapi bisa disesuaikan admin lewat $totalTagihanOverride
     *   (dev-plan/admin/03, B77 — ongkir/material tambahan tak terduga,
     *   atau diskon). Kalau order ini sudah punya pembayaran DP
     *   sebelumnya dgn total yang sudah disesuaikan, penyesuaian itu
     *   TETAP dipakai di panggilan berikutnya walau $totalTagihanOverride
     *   tidak diisi lagi (tidak balik ke harga katalog begitu saja).
     * - Income & reminder servis berikutnya dibuat OTOMATIS saat lunas
     *   (keputusan B5, PRD alur B2), nominalnya ikut total (yang mungkin
     *   sudah disesuaikan) — bukan selalu harga katalog.
     *
     * @throws BusinessRuleException|AuthorizationException
     */
    public function recordPayment(
        Order $order,
        PaymentMethod $metode,
        float $jumlahDibayar,
        User $by,
        ?string $tanggalBayar = null,
        ?float $totalTagihanOverride = null,
        ?string $catatan = null
    ): Payment {
        $this->assertRole($by, [RoleName::Admin, RoleName::Finance, RoleName::Owner]);

        if ($jumlahDibayar <= 0) {
            throw new BusinessRuleException('Jumlah bayar harus lebih dari 0.');
        }

        $payment = $order->payments()->first();

        if ($payment && $payment->status === PaymentStatus::Lunas) {
            throw new BusinessRuleException('Order ini sudah berstatus lunas.');
        }

        // Baseline: total yang berlaku SEBELUM panggilan ini — total
        // pembayaran sebelumnya (kalau sudah pernah disesuaikan) atau
        // harga katalog kalau belum pernah ada pembayaran sama sekali.
        $baseline = (float) ($payment?->total_tagihan ?? $order->total());
        $total = $totalTagihanOverride ?? $baseline;

        if ($total <= 0) {
            throw new BusinessRuleException('Total tagihan harus lebih dari 0.');
        }

        // Wajib catatan HANYA saat total BERUBAH dari baseline (bukan
        // sekadar beda dari harga katalog — panggilan lanjutan yg
        // melanjutkan penyesuaian sebelumnya tanpa mengubahnya lagi tidak
        // perlu catatan baru, catatan lama sudah tersimpan di baris
        // payment yg sama).
        if (abs($total - $baseline) > 0.009 && blank($catatan)) {
            throw new BusinessRuleException('Total tagihan disesuaikan — wajib isi alasan penyesuaian.');
        }

        $sudahDibayar = (float) ($payment?->jumlah_dibayar ?? 0);
        $sisa = $total - $sudahDibayar;

        if ($jumlahDibayar > $sisa + 0.009) {
            throw new BusinessRuleException('Jumlah bayar melebihi sisa tagihan.');
        }

        // §3.11: pelunasan (yg memicu income/reminder/status selesai) ditahan
        // sampai laporan pengerjaan terbaru diverifikasi admin — kalau order
        // belum ada laporan sama sekali, tidak ada yg perlu diverifikasi.
        $akanLunas = ($sudahDibayar + $jumlahDibayar) >= $total - 0.009;
        if ($akanLunas) {
            $laporanTerakhir = $order->workReports()->latest('id')->first();
            if ($laporanTerakhir !== null && ! $laporanTerakhir->sudahDiverifikasi()) {
                throw new BusinessRuleException('Verifikasi laporan pengerjaan dulu sebelum mencatat pelunasan.');
            }
        }

        // Semua efek (payment, income, reminder, status order) satu transaksi.
        return DB::transaction(function () use ($order, $metode, $jumlahDibayar, $by, $tanggalBayar, $payment, $total, $sudahDibayar, $catatan): Payment {
            $baruDibayar = $sudahDibayar + $jumlahDibayar;

            $payment ??= new Payment(['order_id' => $order->id]);
            $payment->metode = $metode;
            $payment->total_tagihan = $total;
            $payment->jumlah_dibayar = $baruDibayar;
            $payment->dicatat_oleh = $by->id;

            if ($catatan !== null) {
                $payment->catatan = $catatan;
            }

            if ($baruDibayar >= $total - 0.009) {
                $payment->status = PaymentStatus::Lunas;
                $payment->tanggal_bayar = $tanggalBayar ?? now()->toDateString();
            } else {
                $payment->status = PaymentStatus::Dp;
            }

            $payment->save();

            if ($payment->status === PaymentStatus::Lunas) {
                $this->finalizeLunas($order, $payment);
            }

            return $payment->fresh();
        });
    }

    /**
     * Saat lunas: catat income + buat service_reminder (idempotent),
     * dan kunci order menjadi `selesai`.
     */
    private function finalizeLunas(Order $order, Payment $payment): void
    {
        $this->catatIncome($order, $payment);

        if (! in_array($order->status, [OrderStatus::Selesai, OrderStatus::Batal], true)) {
            $order->status = OrderStatus::Selesai;
            $order->save();
        }

        // Order yang baru lunas tanpa lewat laporan teknisi tetap butuh token resi.
        if ($order->status === OrderStatus::Selesai) {
            $order->pastikanResiToken();
        }

        $this->buatReminder($order, $payment);
    }

    private function catatIncome(Order $order, Payment $payment): void
    {
        $jenisLayanan = $order->serviceCatalog?->jenis_layanan;
        $kategori = in_array($jenisLayanan, [ServiceType::CuciAc, ServiceType::ServiceAc], true)
            ? IncomeCategory::Jasa
            : IncomeCategory::Material;

        $exists = Income::where('order_id', $order->id)->where('kategori', $kategori->value)->exists();

        if (! $exists) {
            Income::create([
                'order_id' => $order->id,
                'kategori' => $kategori,
                'nominal' => $payment->total_tagihan,
                'tanggal' => $payment->tanggal_bayar ?? now()->toDateString(),
                'keterangan' => 'Otomatis dari pembayaran lunas order #'.$order->id,
            ]);
        }
    }

    private function buatReminder(Order $order, Payment $payment): void
    {
        if ($order->serviceCatalog?->interval_bulan === null) {
            return; // layanan tanpa servis berkala (service/pengadaan).
        }

        $already = ServiceReminder::where('order_id', $order->id)->exists();

        if ($already) {
            return;
        }

        // dev-plan/12 §3.6: interval ikut kategori customer, bukan angka
        // tetap dari katalog — rumahan/perorangan 3 bulan, company
        // (sekolah/kantor dll) 1 bulan (lebih sering butuh servis berkala).
        $interval = $order->customer?->jenis === CustomerJenis::Company ? 1 : 3;

        $tanggalBayar = CarbonImmutable::parse($payment->tanggal_bayar ?? now());

        ServiceReminder::create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'interval_bulan' => $interval,
            'tanggal_servis_berikutnya' => $tanggalBayar->addMonthsNoOverflow($interval)->toDateString(),
            'status_notice' => ReminderStatus::BelumJatuhTempo,
        ]);
    }

    /**
     * Admin menandai reminder sudah dihubungi (notice dashboard, Fase 1).
     */
    public function tandaiSudahDihubungi(ServiceReminder $reminder, User $by): ServiceReminder
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        if ($reminder->status_notice !== ReminderStatus::Selesai) {
            $reminder->status_notice = ReminderStatus::SudahDihubungi;
            $reminder->save();
        }

        return $reminder->fresh();
    }

    /**
     * B35: Admin/Owner membuat notice servis berikutnya MANUAL dari panel —
     * untuk koreksi/susulan (mis. order lama yang belum sempat dibuatkan
     * reminder otomatis). Wajib menunjuk order acuan milik customer.
     *
     * @param  int|null  $intervalBulan  override interval; null = pakai interval service_catalog order
     * @param  string|null  $tanggalServis  override tanggal; null = hari ini + interval
     *
     * @throws BusinessRuleException|AuthorizationException
     */
    public function buatReminderManual(
        Order $order,
        User $by,
        ?int $intervalBulan = null,
        ?string $tanggalServis = null
    ): ServiceReminder {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $interval = $intervalBulan ?? $order->serviceCatalog?->interval_bulan;

        if (! $interval || $interval < 1) {
            throw new BusinessRuleException(
                'Layanan order ini tidak punya interval servis berkala (interval_bulan kosong). Isi interval_bulan di katalog layanan, atau tentukan interval manual.'
            );
        }

        $sudahAda = ServiceReminder::where('order_id', $order->id)->exists();

        if ($sudahAda) {
            throw new BusinessRuleException('Order ini sudah punya notice servis berikutnya — hapus/ubah yang lama dulu bila perlu koreksi.');
        }

        $tanggal = $tanggalServis
            ? CarbonImmutable::parse($tanggalServis)
            : CarbonImmutable::now()->addMonthsNoOverflow($interval);

        return ServiceReminder::create([
            'customer_id' => $order->customer_id,
            'order_id' => $order->id,
            'interval_bulan' => $interval,
            'tanggal_servis_berikutnya' => $tanggal->toDateString(),
            'status_notice' => ReminderStatus::BelumJatuhTempo,
        ]);
    }
}
