<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\LaporanBulanan;
use App\Models\Order;
use App\Models\User;
use App\Services\LaporanPdfService;
use App\Services\LaporanPengerjaanService;
use App\Support\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Preview HTML & unduh PDF Laporan Pengerjaan (dev-plan/21 §6). Seluruh
 * isinya disusun LaporanPengerjaanService — controller hanya mengizinkan &
 * merender. Akses: Owner/Admin/Finance = semua order; Teknisi = hanya order
 * yang ditugaskan kepadanya.
 */
class LaporanController extends Controller
{
    public function __construct(
        private readonly LaporanPengerjaanService $laporan,
        private readonly LaporanPdfService $pdf,
    ) {}

    public function preview(Request $request, Order $order)
    {
        $this->izinkan($request->user(), $order);

        $adalahTeknisi = ! $this->admin($request->user());

        return view('laporan.preview', [
            'dokumen' => $this->laporan->dokumenOrder($order),
            'urlPdf' => Url::absolute('laporan.pdf', ['order' => $order->id]),
            'urlKembali' => $adalahTeknisi ? Url::absolute('teknisi.lengkapi-laporan') : null,
        ]);
    }

    public function pdf(Request $request, Order $order)
    {
        $this->izinkan($request->user(), $order);

        $dokumen = $this->laporan->dokumenOrder($order);
        $nama = 'Laporan-'.Str::slug($order->customer?->nama ?? 'customer')
            .'-'.($order->tanggal_jadwal?->format('Ymd') ?? 'order').'-'.$order->id.'.pdf';

        return response($this->pdf->render($dokumen), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }

    public function unduhBulanan(Request $request, LaporanBulanan $laporan)
    {
        abort_unless($this->admin($request->user()), 403);
        abort_unless($laporan->selesai(), 404, 'Laporan belum selesai dibuat.');

        $disk = Storage::disk((string) config('penyimpanan.disk_laporan', 'local'));
        abort_unless($disk->exists($laporan->path), 404, 'Berkas laporan sudah tidak ada — buat ulang.');

        return $disk->download($laporan->path, 'Laporan-Bulanan-'.Str::slug($laporan->customer?->nama ?? 'customer').'-'.$laporan->bulan.'.pdf');
    }

    private function izinkan(?User $user, Order $order): void
    {
        abort_if($user === null, 403);

        if ($this->admin($user)) {
            return;
        }

        abort_unless($user->hasRole(RoleName::Teknisi->value) && $order->diassignkanKe($user), 403);
    }

    private function admin(?User $user): bool
    {
        return $user?->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value]) ?? false;
    }
}
