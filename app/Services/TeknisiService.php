<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Order;
use App\Models\StockItem;
use App\Models\User;
use App\Models\WorkReport;
use App\Models\WorkReportMaterial;
use Illuminate\Auth\Access\AuthorizationException;

class TeknisiService
{
    use RestrictsByRole;

    public function __construct(private readonly StockService $stockService)
    {
    }

    /**
     * Slider "mulai berangkat ke lokasi" (gaya ojek online).
     * Order: terjadwal -> menuju_lokasi.
     */
    public function berangkat(Order $order, User $teknisi): Order
    {
        $this->pastikanPemilik($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->status !== OrderStatus::Terjadwal) {
            throw new BusinessRuleException('Order harus berstatus terjadwal sebelum berangkat.');
        }

        $order->status = OrderStatus::MenujuLokasi;
        $order->save();

        return $order->fresh();
    }

    /**
     * Check-in di lokasi customer: mencatat attendance dan mengunci status
     * order menjadi `dikerjakan`.
     */
    public function checkIn(Order $order, User $teknisi, ?string $lokasi = null): Attendance
    {
        $this->pastikanPemilik($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if ($order->status !== OrderStatus::MenujuLokasi) {
            throw new BusinessRuleException('Check-in hanya bisa setelah status menuju_lokasi.');
        }

        $attendance = Attendance::create([
            'user_id' => $teknisi->id,
            'order_id' => $order->id,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => now(),
            'jam_keluar' => null,
            'lokasi' => $lokasi,
            'status' => AttendanceStatus::Hadir,
        ]);

        $order->status = OrderStatus::Dikerjakan;
        $order->save();

        return $attendance;
    }

    /**
     * Submit laporan pengerjaan + material terpakai.
     * Efek otomatis (PRD alur 5): stok keluar per material, status order
     * menjadi `selesai` atau `butuh_followup`, check-out attendance.
     *
     * @param  array{catatan: string, materials: array<int, array{stock_item_id: int, jumlah: int}>, foto_sebelum?: ?string, foto_sesudah?: ?string, butuh_followup?: bool}  $payload
     */
    public function submitLaporan(Order $order, User $teknisi, array $payload): WorkReport
    {
        $this->pastikanPemilik($order, $teknisi);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if (! in_array($order->status, [OrderStatus::Dikerjakan, OrderStatus::ButuhFollowup], true)) {
            throw new BusinessRuleException('Laporan hanya bisa disubmit saat order dikerjakan/ditindaklanjuti.');
        }

        $catatan = trim($payload['catatan'] ?? '');
        if ($catatan === '') {
            throw new BusinessRuleException('Catatan pengerjaan wajib diisi.');
        }

        $attendance = $order->attendances()
            ->where('user_id', $teknisi->id)
            ->whereNull('jam_keluar')
            ->latest('id')
            ->first();

        $waktuMulai = $attendance?->jam_masuk ?? now();

        $report = WorkReport::create([
            'order_id' => $order->id,
            'teknisi_id' => $teknisi->id,
            'catatan_pengerjaan' => $catatan,
            'foto_sebelum' => $payload['foto_sebelum'] ?? null,
            'foto_sesudah' => $payload['foto_sesudah'] ?? null,
            'waktu_mulai' => $waktuMulai,
            'waktu_selesai' => now(),
        ]);

        $this->catatMaterial($report, $teknisi, $payload['materials'] ?? []);

        $order->status = ! empty($payload['butuh_followup'])
            ? OrderStatus::ButuhFollowup
            : OrderStatus::Selesai;
        $order->save();

        $attendance?->update(['jam_keluar' => now()]);

        return $report->fresh();
    }

    private function catatMaterial(WorkReport $report, User $teknisi, array $materials): void
    {
        foreach ($materials as $baris) {
            $item = StockItem::findOrFail($baris['stock_item_id']);
            $jumlah = (int) ($baris['jumlah'] ?? 0);

            if ($jumlah <= 0) {
                throw new BusinessRuleException('Jumlah material harus lebih dari 0.');
            }

            WorkReportMaterial::create([
                'work_report_id' => $report->id,
                'stock_item_id' => $item->id,
                'jumlah' => $jumlah,
            ]);

            // Stok keluar otomatis; keputusan B4: boleh minus.
            $this->stockService->keluar(
                $item,
                $jumlah,
                $teknisi,
                'work_report:' . $report->id,
                'Material laporan order #' . $report->order_id
            );
        }
    }

    private function pastikanPemilik(Order $order, User $teknisi): void
    {
        if ((int) $order->teknisi_id !== (int) $teknisi->id) {
            throw new AuthorizationException('Order ini bukan tugas teknisi Anda.');
        }
    }
}
