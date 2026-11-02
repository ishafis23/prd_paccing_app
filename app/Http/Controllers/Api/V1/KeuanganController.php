<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\CashHandover;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\TechnicianAdvance;
use App\Models\TechnicianIncentive;
use App\Services\CashHandoverService;
use App\Services\StorageQuotaService;
use App\Services\SyaratAdministrasiService;
use App\Services\TeknisiKeuanganService;
use App\Support\Api\Presenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Pendapatan jasa, Games 5/6, capaian, insentif, setoran tunai, administrasi, gaji & kasbon (baca). */
class KeuanganController extends Controller
{
    public function __construct(private readonly TeknisiKeuanganService $keuangan) {}

    /** Penghalang "Administrasi belum tuntas" — sumber banner & tombol Berangkat. */
    public function administrasi(Request $request): JsonResponse
    {
        $svc = app(SyaratAdministrasiService::class);
        $penghalang = $svc->penghalang($request->user());

        return response()->json(['data' => [
            'penghalang' => collect($penghalang)->map(fn (array $p): array => $p + [
                'aksi' => match ($p['jenis']) {
                    'setoran' => ['layar' => 'setoran'],
                    'pengeluaran' => ['layar' => 'pengeluaran'],
                    default => ['layar' => 'order', 'order_id' => $p['order_id']],
                },
            ])->values(),
            'tertahan_setoran' => $svc->tertahanSetoran($penghalang),
            'tertahan' => $penghalang !== [],
            'pesan' => $penghalang === [] ? null : $svc->pesanRingkas($penghalang),
        ]]);
    }

    public function pendapatanRingkasan(Request $request): JsonResponse
    {
        $u = $request->user();

        return response()->json(['data' => [
            'hari_ini' => Presenter::rupiah($this->keuangan->pendapatanHariIni($u)),
            'bulan_ini' => Presenter::rupiah($this->keuangan->pendapatanBulanIni($u)),
            'total' => Presenter::rupiah($this->keuangan->pendapatanTotal($u)),
        ]]);
    }

    public function pendapatan(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => ['nullable', 'in:bulanan,harian'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $u = $request->user();
        $mode = $request->input('mode', 'bulanan');
        $tanggal = $request->input('tanggal', today()->format('Y-m-d'));
        $daftar = collect($this->keuangan->daftarPendapatan($u, $mode, $request->input('bulan'), $tanggal))
            ->map(fn (array $b): array => ['total' => Presenter::rupiah($b['total'])] + $b);

        return response()->json(['data' => [
            'mode' => $mode,
            'periode' => $mode === 'harian' ? $tanggal : $request->input('bulan', today()->format('Y-m')),
            'total' => $daftar->sum('total'),
            'daftar' => $daftar->values(),
            'games' => $mode === 'harian' ? $this->gamesPayload($u, $tanggal) : null,
        ]]);
    }

    public function games(Request $request): JsonResponse
    {
        $request->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json(['data' => $this->gamesPayload($request->user(), $request->input('tanggal', today()->format('Y-m-d')))]);
    }

    /** @return array<string, mixed> */
    private function gamesPayload($user, string $tanggal): array
    {
        $g = $this->keuangan->gamesHarian($user, $tanggal);

        return ['tanggal' => $tanggal] + array_map(fn ($v) => is_float($v) ? Presenter::rupiah($v) : $v, $g);
    }

    public function capaian(Request $request): JsonResponse
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        return response()->json(['data' => $this->keuangan->capaian($request->user(), $request->input('bulan'))]);
    }

    /** Ledger insentif/denda sendiri per bulan. */
    public function insentif(Request $request): JsonResponse
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);
        $bulan = $request->input('bulan', today()->format('Y-m'));

        $daftar = TechnicianIncentive::query()
            ->where('user_id', $request->user()->id)
            ->whereYear('tanggal', substr($bulan, 0, 4))
            ->whereMonth('tanggal', substr($bulan, 5, 2))
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => [
            'bulan' => $bulan,
            'daftar' => $daftar->map(fn (TechnicianIncentive $i): array => Presenter::insentif($i))->values(),
        ]]);
    }

    // ---- Setoran tunai ----

    public function setoran(Request $request): JsonResponse
    {
        $u = $request->user();
        $svc = app(CashHandoverService::class);
        $telatIds = $svc->telat($u)->pluck('id')->all();

        return response()->json(['data' => [
            'ringkasan' => $svc->ringkasanTeknisi($u),
            'kewajiban' => $svc->kewajiban($u)->map(fn (Order $o): array => [
                'order_id' => $o->id,
                'customer' => $o->customer?->nama,
                'tanggal_jadwal' => $o->tanggal_jadwal?->toDateString(),
                'total' => Presenter::rupiah($o->total()),
                'telat' => in_array($o->id, $telatIds, true),
            ])->values(),
            'riwayat' => CashHandover::query()->where('teknisi_id', $u->id)->with('order.customer')->latest('id')->limit(20)->get()
                ->map(fn (CashHandover $h): array => Presenter::setoran($h))->values(),
        ]]);
    }

    public function catatSetoran(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'nominal' => ['required', 'numeric', 'min:1000'],
            'diserahkan_ke' => ['required', 'string', 'min:3', 'max:150'],
            'tanggal_setor' => ['required', 'date', 'before_or_equal:today'],
            'foto' => ['required', 'image', 'max:5120'],
            'catatan' => ['nullable', 'string'],
        ]);

        $u = $request->user();
        $order = Order::query()->untukTeknisi($u->id)->findOrFail($data['order_id']);
        $file = $request->file('foto');
        $path = null;

        try {
            app(StorageQuotaService::class)->pastikanCukup((int) $file->getSize());
            $path = $file->store('setoran-tunai', 'public');

            $handover = app(CashHandoverService::class)->catat($order, $u, [
                'nominal' => $data['nominal'],
                'diserahkan_ke' => $data['diserahkan_ke'],
                'tanggal_setor' => $data['tanggal_setor'],
                'catatan' => $data['catatan'] ?? null,
                'foto_path' => $path,
            ]);
            StorageQuotaService::lupakanCache();
        } catch (BusinessRuleException|AuthorizationException $e) {
            if ($path !== null) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return response()->json(['data' => Presenter::setoran($handover->load('order.customer'))], 201);
    }

    // ---- Gaji & kasbon (baca saja) ----

    public function gaji(Request $request): JsonResponse
    {
        $runs = PayrollRun::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('periode_mulai')
            ->limit(24)
            ->get()
            ->filter(fn (PayrollRun $r): bool => $r->slipAktif()) // hanya yang sudah di-ACC
            ->map(fn (PayrollRun $r): array => [
                'id' => $r->id,
                'periode_mulai' => $r->periode_mulai->toDateString(),
                'periode_selesai' => $r->periode_selesai->toDateString(),
                'status' => $r->status->value,
                'gaji_bersih' => Presenter::rupiah($r->gaji_bersih),
                'dibayar_pada' => $r->dibayar_pada?->toDateString(),
            ])->values();

        return response()->json(['data' => $runs]);
    }

    public function slipGaji(Request $request, int $id)
    {
        $run = PayrollRun::query()->where('user_id', $request->user()->id)->findOrFail($id);

        if (! $run->slipAktif()) {
            throw new BusinessRuleException('Slip hanya tersedia setelah gaji di-ACC.');
        }

        $nama = 'Slip-Gaji-'.str($run->user?->name ?? 'teknisi')->slug().'-'.$run->periode_mulai->format('Ymd').'_'.$run->periode_selesai->format('Ymd').'.pdf';

        $pdf = Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'Helvetica',
            'dpi' => 96,
            'chroot' => [base_path(), storage_path()],
        ])->loadView('payroll.slip', ['run' => $run, 'pdf' => true])->setPaper('a4')->output();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
        ]);
    }

    public function kasbon(Request $request): JsonResponse
    {
        $daftar = TechnicianAdvance::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('tanggal')
            ->get()
            ->map(fn (TechnicianAdvance $a): array => [
                'id' => $a->id,
                'tanggal' => $a->tanggal->toDateString(),
                'nominal' => Presenter::rupiah($a->nominal),
                'cicilan_per_periode' => $a->cicilan_per_periode === null ? null : Presenter::rupiah($a->cicilan_per_periode),
                'terbayar' => Presenter::rupiah($a->terbayar()),
                'sisa' => Presenter::rupiah($a->sisa()),
                'status' => $a->status instanceof \BackedEnum ? $a->status->value : (string) $a->status,
                'keterangan' => $a->keterangan,
            ])->values();

        return response()->json(['data' => $daftar]);
    }
}
