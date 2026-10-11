<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Services\PayrollService;
use App\Support\Url;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Slip gaji teknisi (dev-plan/hrd/02) — hanya bisa dibuka Admin/HR/Owner
 * dan hanya setelah gaji di-ACC (status disetujui/dibayar).
 */
class PayrollSlipController extends Controller
{
    public function show(PayrollRun $run)
    {
        $this->izinkan($run);

        return view('payroll.slip', [
            'run' => $run,
            'urlPdf' => Url::absolute('payroll.slip.pdf', ['run' => $run->id]),
        ]);
    }

    public function pdf(PayrollRun $run)
    {
        $this->izinkan($run);

        $nama = 'Slip-Gaji-'.str($run->user?->name ?? 'teknisi')->slug().'-'
            .$run->periode_mulai->format('Ymd').'_'.$run->periode_selesai->format('Ymd').'.pdf';

        $pdf = Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'Helvetica',
            'dpi' => 96,
            'chroot' => [base_path(), storage_path()],
        ])->loadView('payroll.slip', ['run' => $run, 'pdf' => true])
            ->setPaper('a4')
            ->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }

    private function izinkan(PayrollRun $run): void
    {
        abort_unless(PayrollService::boleh(auth()->user()), 403);
        abort_unless($run->slipAktif(), 404, 'Slip hanya tersedia setelah gaji di-ACC.');
    }
}
