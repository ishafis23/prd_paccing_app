<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\TeknisiExpense;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pengeluaran harian teknisi (dev-plan/30, revisi client 1 Nov): catat, ubah,
 * hapus, partner/pendamping, ringkasan & filter periode. Dipakai portal web
 * (Livewire LaporanPengeluaran) dan API mobile — satu sumber aturan.
 */
class TeknisiExpenseService
{
    /** @return array<string, string> id => nama teknisi lain (calon partner). */
    public function opsiPartner(User $teknisi): array
    {
        return User::role(RoleName::Teknisi->value)
            ->where('users.id', '!=', $teknisi->id)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> order_id => label, order milik/tim teknisi (≤100 terbaru). */
    public function opsiOrder(User $teknisi): array
    {
        return Order::query()
            ->untukTeknisi($teknisi->id)
            ->with('customer')
            ->latest('id')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (Order $o): array => [$o->id => "#{$o->id} — ".($o->customer?->nama ?? 'Tanpa nama')])
            ->all();
    }

    /**
     * Validasi isian pengeluaran (sama untuk catat & ubah).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> data bersih siap simpan
     *
     * @throws ValidationException
     */
    public function validasi(User $teknisi, array $input): array
    {
        $data = Validator::make($input, [
            'tanggal_input' => ['required', 'date', 'before_or_equal:today'],
            'kategori' => ['required', Rule::in(TeknisiExpense::KATEGORI)],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'harga' => ['required', 'integer', 'min:0', 'max:5000000'],
            'nominal' => ['required', 'integer', 'min:1000', 'max:5000000'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'order_id' => ['nullable', 'integer', Rule::in(array_keys($this->opsiOrder($teknisi)))],
            'partner_id' => ['nullable', 'integer', Rule::in(array_keys($this->opsiPartner($teknisi)))],
        ], [
            'partner_id.in' => 'Partner harus teknisi lain.',
            'order_id.in' => 'Order tidak ditemukan atau bukan tugas Anda.',
        ])->validate();

        return [
            'tanggal_input' => $data['tanggal_input'],
            'kategori' => $data['kategori'],
            'qty' => (int) $data['qty'],
            'harga' => (int) $data['harga'],
            'nominal' => (int) $data['nominal'],
            'keterangan' => $data['keterangan'] ?? null,
            'order_id' => filled($data['order_id'] ?? null) ? (int) $data['order_id'] : null,
            'partner_id' => filled($data['partner_id'] ?? null) ? (int) $data['partner_id'] : null,
        ];
    }

    /**
     * Catat pengeluaran baru (status Menunggu).
     *
     * @param  array<string, mixed>  $data hasil validasi()
     */
    public function catat(User $teknisi, array $data): TeknisiExpense
    {
        return TeknisiExpense::create($data + ['teknisi_id' => $teknisi->id, 'status' => 'pending']);
    }

    /**
     * Ubah pengeluaran milik pencatat. Ubah tanggal/kategori/qty/harga/nominal
     * pada yang sudah Disetujui/Ditolak → kembali Menunggu; ubah partner,
     * keterangan, atau order saja → status tetap.
     *
     * @param  array<string, mixed>  $data hasil validasi()
     * @return array{expense: TeknisiExpense, perlu_acc_ulang: bool}
     */
    public function ubah(User $teknisi, TeknisiExpense $expense, array $data): array
    {
        if ((int) $expense->teknisi_id !== (int) $teknisi->id) {
            throw new BusinessRuleException('Hanya pencatat yang bisa mengubah pengeluaran ini.');
        }

        $uangBerubah = $expense->tanggal_input->format('Y-m-d') !== $data['tanggal_input']
            || $expense->kategori !== $data['kategori']
            || (int) $expense->qty !== (int) $data['qty']
            || (int) $expense->harga !== (int) $data['harga']
            || (int) $expense->nominal !== (int) $data['nominal'];

        $kembaliMenunggu = $uangBerubah && $expense->status !== 'pending';

        if ($kembaliMenunggu) {
            $data += ['status' => 'pending', 'approved_by' => null, 'catatan_approval' => null];
        }

        $expense->update($data);

        return ['expense' => $expense->fresh(['partner', 'teknisi', 'order.customer']), 'perlu_acc_ulang' => $kembaliMenunggu];
    }

    /** Hapus pengeluaran: hanya pencatat & status Menunggu. */
    public function hapus(User $teknisi, TeknisiExpense $expense): void
    {
        if ((int) $expense->teknisi_id !== (int) $teknisi->id) {
            throw new BusinessRuleException('Hanya pencatat yang bisa menghapus pengeluaran ini.');
        }

        if ($expense->status !== 'pending') {
            throw new BusinessRuleException('Hanya pengeluaran pending yang bisa dihapus.');
        }

        $expense->delete();
    }

    /** Batasi query ke satu hari (harian) atau satu bulan (bulanan). */
    public function terapkanPeriode(Builder $query, string $mode, ?string $bulan, ?string $tanggal): Builder
    {
        if ($mode === 'harian') {
            return filled($tanggal) ? $query->byDate((string) $tanggal) : $query;
        }

        return filled($bulan) ? $query->byMonth((string) $bulan) : $query;
    }

    /**
     * Riwayat (termasuk entri rekan yang menautkan saya sebagai partner).
     *
     * @param  array{mode?: string, bulan?: ?string, tanggal?: ?string, kategori?: ?string, status?: ?string}  $filter
     */
    public function riwayat(User $teknisi, array $filter, int $perPage = 15): LengthAwarePaginator
    {
        $query = TeknisiExpense::query()
            ->tercatatUntuk($teknisi->id)
            ->with(['teknisi', 'partner', 'order.customer']);

        $this->terapkanPeriode($query, $filter['mode'] ?? 'bulanan', $filter['bulan'] ?? null, $filter['tanggal'] ?? null);

        if (filled($filter['kategori'] ?? null)) {
            $query->where('kategori', $filter['kategori']);
        }

        if (filled($filter['status'] ?? null)) {
            $query->where('status', $filter['status']);
        }

        return $query->orderByDesc('tanggal_input')->orderByDesc('id')->paginate($perPage);
    }

    /** @return array{total_pending: int, total_approved: int, total_rejected: int, total_items: int} */
    public function ringkasanBulan(User $teknisi, string $bulan): array
    {
        $expenses = TeknisiExpense::query()->byTeknisi($teknisi->id)->byMonth($bulan)->get();

        return [
            'total_pending' => (int) $expenses->where('status', 'pending')->sum('nominal'),
            'total_approved' => (int) $expenses->where('status', 'approved')->sum('nominal'),
            'total_rejected' => (int) $expenses->where('status', 'rejected')->sum('nominal'),
            'total_items' => $expenses->count(),
        ];
    }

    /** @return array{total_pending: int, total_approved: int, total_items: int} */
    public function ringkasanHariIni(User $teknisi): array
    {
        $expenses = TeknisiExpense::query()->byTeknisi($teknisi->id)->byDate(today()->format('Y-m-d'))->get();

        return [
            'total_pending' => (int) $expenses->where('status', 'pending')->sum('nominal'),
            'total_approved' => (int) $expenses->where('status', 'approved')->sum('nominal'),
            'total_items' => $expenses->count(),
        ];
    }

    /** @return array<string, array{pending: int, approved: int, total: int, count: int}> per kategori pada periode. */
    public function breakdownKategori(User $teknisi, string $mode, ?string $bulan, ?string $tanggal): array
    {
        $expenses = $this->terapkanPeriode(TeknisiExpense::query()->byTeknisi($teknisi->id), $mode, $bulan, $tanggal)->get();

        $hasil = [];
        foreach (TeknisiExpense::KATEGORI as $kat) {
            $f = $expenses->where('kategori', $kat);
            $hasil[$kat] = [
                'pending' => (int) $f->where('status', 'pending')->sum('nominal'),
                'approved' => (int) $f->where('status', 'approved')->sum('nominal'),
                'total' => (int) $f->sum('nominal'),
                'count' => $f->count(),
            ];
        }

        return $hasil;
    }
}
