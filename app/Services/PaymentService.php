<?php

namespace App\Services;

use App\Enums\CustomerJenis;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReminderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Income;
use App\Models\Order;
use App\Models\OrderTotalCorrection;
use App\Models\Payment;
use App\Models\ServiceReminder;
use App\Models\User;
use App\Support\ServiceTypeResolver;
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
        ?string $catatan = null,
        ?float $omsetJasa = null,
        ?float $omsetMaterial = null
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

        // Split omset jasa/material (dev-plan/24) — acuan final laporan omset.
        $split = $this->validasiSplit($omsetJasa, $omsetMaterial, $total, $payment);

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
        $totalDiubahEksplisit = $totalTagihanOverride !== null && abs($total - $baseline) > 0.009;

        return DB::transaction(function () use ($order, $metode, $jumlahDibayar, $by, $tanggalBayar, $payment, $total, $sudahDibayar, $catatan, $split, $totalDiubahEksplisit): Payment {
            $baruDibayar = $sudahDibayar + $jumlahDibayar;

            $payment ??= new Payment(['order_id' => $order->id]);
            $payment->metode = $metode;
            $payment->total_tagihan = $total;
            $payment->jumlah_dibayar = $baruDibayar;
            $payment->dicatat_oleh = $by->id;

            if ($split !== null) {
                $payment->omset_jasa = $split['jasa'];
                $payment->omset_material = $split['material'];
            }

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

            // Admin menetapkan total tagihan secara eksplisit di sini → koreksi total yang belum
            // tersinkron dianggap sudah diperhitungkan (jangan ditambahkan lagi nanti).
            if ($totalDiubahEksplisit) {
                OrderTotalCorrection::query()
                    ->where('order_id', $order->id)
                    ->whereNull('disinkron_ke_pembayaran_pada')
                    ->update(['disinkron_ke_pembayaran_pada' => now()]);
            }

            if ($payment->status === PaymentStatus::Lunas) {
                $this->finalizeLunas($order, $payment);
            }

            return $payment->fresh();
        });
    }

    /**
     * Koreksi split omset jasa/material pada pembayaran yang sudah tercatat
     * (termasuk yang sudah lunas). Tidak menyentuh nominal bayar, status,
     * income, atau reminder — hanya pembagian jasa/material; Σ harus = total.
     *
     * @throws BusinessRuleException|AuthorizationException
     */
    public function koreksiSplitOmset(Order $order, float $omsetJasa, float $omsetMaterial, User $by): Payment
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Finance, RoleName::Owner]);

        $payment = $order->payments()->first();

        if (! $payment) {
            throw new BusinessRuleException('Order ini belum punya pembayaran tercatat.');
        }

        // Total order bisa berubah setelah pembayaran dicatat (Koreksi Total, layanan tambahan).
        // Selaraskan dulu agar acuan Jasa+Material adalah tagihan TERKINI, bukan tagihan awal.
        $payment = $this->sinkronkanTotalDenganOrder($order, $by) ?? $payment;

        $split = $this->validasiSplit($omsetJasa, $omsetMaterial, (float) $payment->total_tagihan, $payment);

        $payment->omset_jasa = $split['jasa'];
        $payment->omset_material = $split['material'];
        $payment->save();

        return $payment->fresh();
    }

    /**
     * Selaraskan pembayaran dengan "Koreksi Total" yang dibuat SETELAH pembayaran dicatat.
     * `payments.total_tagihan` adalah salinan saat bayar dicatat; tanpa ini sistem tetap
     * membaca tagihan awal (mis. 170rb padahal tagihan kini 185rb) sehingga Edit Omset /
     * Catat Pembayaran menuntut tagihan awal.
     *
     * Hanya selisih koreksi yang belum tersinkron yang ditambahkan ke total tagihan —
     * penyesuaian sengaja admin (diskon/ongkir lewat Catat Pembayaran) tidak ditimpa.
     *
     * - Lunas: selisih DIANGGAP SUDAH DIBAYAR (jumlah dibayar ikut total baru, tetap lunas),
     *   income otomatis & split omset ikut disesuaikan.
     * - DP: hanya total tagihan yang berubah (sisa bertambah/berkurang); total baru tidak
     *   boleh di bawah jumlah yang sudah dibayar.
     *
     * Idempotent: koreksi yang sudah disinkronkan ditandai dan tidak dihitung lagi.
     * Mengembalikan null bila order belum punya pembayaran.
     *
     * @throws BusinessRuleException|AuthorizationException
     */
    public function sinkronkanTotalDenganOrder(Order $order, User $by): ?Payment
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Finance, RoleName::Owner]);

        $payment = $order->payments()->first();

        if (! $payment) {
            return null;
        }

        $pending = OrderTotalCorrection::query()
            ->where('order_id', $order->id)
            ->whereNull('disinkron_ke_pembayaran_pada')
            ->where('created_at', '>=', $payment->created_at)
            ->get();

        if ($pending->isEmpty()) {
            return $payment;
        }

        $selisih = (float) $pending->sum(fn (OrderTotalCorrection $k): float => (float) $k->total_terkoreksi - (float) $k->total_original);
        $totalLama = (float) $payment->total_tagihan;
        $totalBaru = $totalLama + $selisih;

        if ($totalBaru <= 0) {
            throw new BusinessRuleException('Order ini sudah punya pembayaran — total tagihan harus lebih dari 0.');
        }

        $lunas = $payment->status === PaymentStatus::Lunas;
        $dibayar = (float) $payment->jumlah_dibayar;

        if (! $lunas && $totalBaru <= $dibayar + 0.009) {
            throw new BusinessRuleException(sprintf(
                'Total baru (Rp %s) tidak boleh di bawah jumlah yang sudah dibayar (Rp %s).',
                number_format($totalBaru, 0, ',', '.'),
                number_format($dibayar, 0, ',', '.'),
            ));
        }

        $split = $payment->splitOmset();
        $jasa = $split['jasa'] ?? null;
        $material = $split['material'] ?? null;

        if ($split !== null) {
            // Penyesuaian total dicatat sebagai komponen jasa (lihat OrderService::koreksiTotal).
            $jasa += $selisih;
            if ($jasa < 0) {
                $material += $jasa;
                $jasa = 0.0;
            }
            if ($material < 0) {
                throw new BusinessRuleException('Total baru terlalu kecil untuk Omset Jasa & Material yang tercatat — edit omset dulu.');
            }
        }

        return DB::transaction(function () use ($order, $payment, $pending, $totalLama, $totalBaru, $lunas, $jasa, $material, $split): Payment {
            $payment->total_tagihan = $totalBaru;

            if ($lunas) {
                $payment->jumlah_dibayar = $totalBaru;
            }

            if ($split !== null) {
                $payment->omset_jasa = round($jasa, 2);
                $payment->omset_material = round($material, 2);
            }

            $catatan = sprintf(
                'Total tagihan disesuaikan Rp %s → Rp %s (%s).',
                number_format($totalLama, 0, ',', '.'),
                number_format($totalBaru, 0, ',', '.'),
                $lunas ? 'selisih dianggap sudah dibayar' : 'sisa tagihan ikut berubah',
            );
            $payment->catatan = trim(($payment->catatan ? $payment->catatan."\n" : '').$catatan);
            $payment->save();

            if ($lunas) {
                // Income otomatis dari pelunasan = total tagihan; ikut total baru.
                Income::query()
                    ->where('order_id', $order->id)
                    ->where('keterangan', 'like', 'Otomatis dari pembayaran lunas order #%')
                    ->update(['nominal' => $totalBaru]);
            }

            OrderTotalCorrection::query()
                ->whereIn('id', $pending->pluck('id'))
                ->update(['disinkron_ke_pembayaran_pada' => now()]);

            return $payment->fresh();
        });
    }

    /**
     * Validasi & normalisasi split omset jasa/material (dev-plan/24).
     * - Bila salah satu diisi: keduanya wajib > 0 (tidak negatif) dan
     *   Σ harus sama dengan Total Tagihan.
     * - Bila keduanya kosong: pakai split lama (kalau ada), tapi tolak bila
     *   Total Tagihan sudah berubah sehingga split lama tak lagi cocok.
     *
     * @return array{jasa: float, material: float}|null
     */
    private function validasiSplit(?float $jasa, ?float $material, float $total, ?Payment $payment): ?array
    {
        if ($jasa === null && $material === null) {
            $lama = $payment?->splitOmset();

            if ($lama !== null && abs(($lama['jasa'] + $lama['material']) - $total) > 0.009) {
                throw new BusinessRuleException('Total tagihan berubah — isi ulang Omset Jasa & Omset Material.');
            }

            return $lama;
        }

        $j = (float) ($jasa ?? 0);
        $m = (float) ($material ?? 0);

        if ($j < 0 || $m < 0) {
            throw new BusinessRuleException('Omset Jasa & Omset Material tidak boleh negatif.');
        }

        if (abs(($j + $m) - $total) > 0.009) {
            throw new BusinessRuleException('Omset Jasa + Omset Material harus sama dengan Total Tagihan.');
        }

        return ['jasa' => round($j, 2), 'material' => round($m, 2)];
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
        $kategori = ServiceTypeResolver::komponen($order->serviceCatalog?->jenis_layanan);

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
