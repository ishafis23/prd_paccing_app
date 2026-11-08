<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TeknisiExpense;
use App\Services\TeknisiExpenseService;
use App\Support\Api\Presenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Pengeluaran harian teknisi (catat/ubah/hapus, partner, filter bulan/hari). */
class PengeluaranController extends Controller
{
    public function __construct(private readonly TeknisiExpenseService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => ['nullable', 'in:bulanan,harian'],
            'bulan' => ['nullable', 'date_format:Y-m'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'kategori' => ['nullable', 'in:'.implode(',', TeknisiExpense::KATEGORI)],
            'status' => ['nullable', 'in:pending,approved,rejected'],
        ]);

        $user = $request->user();
        $mode = $request->input('mode', 'bulanan');
        $bulan = $request->input('bulan', today()->format('Y-m'));
        $tanggal = $request->input('tanggal', today()->format('Y-m-d'));

        $hasil = $this->service->riwayat($user, [
            'mode' => $mode,
            'bulan' => $bulan,
            'tanggal' => $tanggal,
            'kategori' => $request->input('kategori'),
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'data' => $hasil->getCollection()->map(fn (TeknisiExpense $e): array => Presenter::pengeluaran($e, $user->id))->values(),
            'meta' => [
                'page' => $hasil->currentPage(),
                'per_page' => $hasil->perPage(),
                'total' => $hasil->total(),
                'mode' => $mode,
                'periode' => $mode === 'harian' ? $tanggal : $bulan,
            ],
            'ringkasan' => [
                'hari_ini' => $this->service->ringkasanHariIni($user),
                'bulan_ini' => $this->service->ringkasanBulan($user, $bulan),
            ],
            'breakdown' => $this->service->breakdownKategori($user, $mode, $bulan, $tanggal),
        ]);
    }

    /** Opsi form: kategori, order terkait, calon partner. */
    public function opsi(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'kategori' => collect(TeknisiExpense::kategoriOpsi())->map(fn (string $label, string $kode): array => ['kode' => $kode, 'label' => $label])->values(),
            'order' => collect($this->service->opsiOrder($user))->map(fn (string $label, int $id): array => ['id' => $id, 'label' => $label])->values(),
            'partner' => collect($this->service->opsiPartner($user))->map(fn (string $nama, int $id): array => ['id' => $id, 'name' => $nama])->values(),
            'batas' => ['nominal_min' => 1000, 'nominal_maks' => 5000000],
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $uuid = $request->validate(['client_uuid' => ['nullable', 'uuid']])['client_uuid'] ?? null;

        // Kirim ulang dari antrean offline: kembalikan yang sudah tercatat, jangan gandakan.
        if ($uuid !== null) {
            $ada = TeknisiExpense::query()->where('teknisi_id', $user->id)->where('client_uuid', $uuid)->first();
            if ($ada !== null) {
                return response()->json(['data' => Presenter::pengeluaran($ada->load(['partner', 'teknisi', 'order.customer']), $user->id)]);
            }
        }

        $data = $this->service->validasi($user, $request->all());

        $e = $this->service->catat($user, $data);
        if ($uuid !== null) {
            $e->forceFill(['client_uuid' => $uuid])->save();
        }
        $e->load(['partner', 'teknisi', 'order.customer']);

        return response()->json(['data' => Presenter::pengeluaran($e, $user->id)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        // Hanya pencatat; selain itu 404 (tidak membocorkan keberadaan data).
        $expense = TeknisiExpense::query()->where('teknisi_id', $user->id)->findOrFail($id);

        $hasil = $this->service->ubah($user, $expense, $this->service->validasi($user, $request->all()));

        return response()->json(['data' => Presenter::pengeluaran($hasil['expense'], $user->id) + ['perlu_acc_ulang' => $hasil['perlu_acc_ulang']]]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $expense = TeknisiExpense::query()->where('teknisi_id', $user->id)->findOrFail($id);

        $this->service->hapus($user, $expense);

        return response()->json(['data' => ['ok' => true]]);
    }
}
