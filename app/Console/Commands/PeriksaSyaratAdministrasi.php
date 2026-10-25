<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\SyaratAdministrasiService;
use Illuminate\Console\Command;

/**
 * Periksa dampak gate 3 syarat administrasi (dev-plan/29 C1) SEBELUM/SESUDAH
 * deploy. Read-only kecuali opsi --tandai-terkirim dipakai secara sadar.
 *
 * Alasan: gate "laporan pengerjaan harus sudah dikirim" berlaku untuk order
 * berstatus selesai/butuh_followup yang belum ditutup. Order lama yang selesai
 * sebelum fitur ini dipasang bisa ikut memblokir teknisi pada hari pertama
 * deploy. Perintah ini menunjukkan jumlahnya lebih dulu.
 */
class PeriksaSyaratAdministrasi extends Command
{
    protected $signature = 'administrasi:periksa
        {--teknisi= : hanya satu teknisi (username/email/id)}
        {--tandai-terkirim= : TANDAI laporan terkirim untuk order yang selesai sebelum tanggal ini (YYYY-MM-DD)}';

    protected $description = 'Tampilkan penghalang administrasi per teknisi (read-only) atau tandai laporan order lama sebagai terkirim';

    public function handle(): int
    {
        $service = app(SyaratAdministrasiService::class);

        $query = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'teknisi'));
        if ($this->option('teknisi') !== null) {
            $cari = (string) $this->option('teknisi');
            $query->where(fn ($q) => $q->where('id', $cari)->orWhere('username', $cari)->orWhere('email', $cari));
        }
        $daftar = $query->orderBy('name')->get();

        $tandai = $this->option('tandai-terkirim');
        if ($tandai !== null) {
            return $this->tandaiSebelum((string) $tandai);
        }

        if ($daftar->isEmpty()) {
            $this->warn('Tidak ada teknisi yang cocok.');

            return self::SUCCESS;
        }

        $total = ['setoran' => 0, 'laporan' => 0, 'foto' => 0, 'pengeluaran' => 0];
        $baris = [];

        foreach ($daftar as $teknisi) {
            $penghalang = $service->penghalang($teknisi);
            foreach ($penghalang as $item) {
                $total[$item['jenis']] = ($total[$item['jenis']] ?? 0) + 1;
            }
            $baris[] = [
                $teknisi->id,
                $teknisi->name,
                count($penghalang),
                count(array_filter($penghalang, fn (array $i): bool => $i['jenis'] === 'setoran')),
                count(array_filter($penghalang, fn (array $i): bool => $i['jenis'] === 'laporan')),
                count(array_filter($penghalang, fn (array $i): bool => $i['jenis'] === 'foto')),
                count(array_filter($penghalang, fn (array $i): bool => $i['jenis'] === 'pengeluaran')),
            ];
        }

        $this->table(['ID', 'Teknisi', 'Total', 'setoran', 'laporan', 'foto', 'pengeluaran'], $baris);
        $this->line(sprintf(
            'Total penghalang: setoran %d, laporan %d, foto %d, pengeluaran %d.',
            $total['setoran'], $total['laporan'], $total['foto'], $total['pengeluaran'],
        ));
        $this->line('Bila angka "laporan" besar karena order LAMA (selesai sebelum fitur dipasang), jalankan:');
        $this->line('  php artisan administrasi:periksa --tandai-terkirim=YYYY-MM-DD   # tanggal pasang fitur');

        return self::SUCCESS;
    }

    /** Tandai laporan terkirim untuk order yang selesai sebelum tanggal tertentu (keputusan pemilik). */
    private function tandaiSebelum(string $tanggal): int
    {
        $batas = \Illuminate\Support\Carbon::parse($tanggal)->startOfDay();

        $orders = Order::query()
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->whereNull('ditutup_pada')
            ->whereNull('laporan_terkirim_pada')
            ->where('updated_at', '<', $batas)
            ->orderBy('id')
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Tidak ada order lama yang perlu ditandai.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('Akan menandai %d order (selesai sebelum %s) sebagai "laporan terkirim".', $orders->count(), $batas->toDateString()));
        if (! $this->confirm('Lanjutkan?', false)) {
            $this->line('Dibatalkan.');

            return self::SUCCESS;
        }

        foreach ($orders as $order) {
            // laporan_terkirim_oleh sengaja null: ini penanda administratif, bukan kiriman WA.
            $order->update(['laporan_terkirim_pada' => $order->updated_at ?? now()]);
        }

        $this->info(sprintf('%d order ditandai.', $orders->count()));

        return self::SUCCESS;
    }
}
