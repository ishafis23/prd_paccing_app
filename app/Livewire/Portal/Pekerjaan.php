<?php

namespace App\Livewire\Portal;

use App\Services\PortalAksesService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman "Pekerjaan" Portal Corporate (dev-plan/22 §4.2) — daftar order
 * customer login dengan filter bulan/cabang/jenis.
 */
class Pekerjaan extends Component
{
    use WithPagination;

    #[Url]
    public string $bulan = '';

    #[Url]
    public string $cabang = '';

    #[Url]
    public string $jenis = '';

    public function updating(string $field): void
    {
        if (in_array($field, ['bulan', 'cabang', 'jenis'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $akses = app(PortalAksesService::class);
        $customer = $akses->customer();

        $query = $akses->orderQuery()
            ->with(['customerAddress', 'orderItems', 'serviceCatalog', 'teknisi', 'timTeknisi']);

        if (preg_match('/^\d{4}-\d{2}$/', $this->bulan) === 1) {
            $awal = $this->bulan.'-01';
            $query->whereBetween('tanggal_jadwal', [$awal, date('Y-m-t', strtotime($awal))]);
        }

        if ($this->cabang !== '') {
            $query->where('customer_address_id', (int) $this->cabang);
        }

        if ($this->jenis !== '') {
            $query->whereHas('orderItems', fn ($q) => $q->where('nama_layanan', $this->jenis));
        }

        $orders = $query->orderByDesc('tanggal_jadwal')->orderByDesc('id')->paginate(15);

        $nomorInvoice = $this->nomorInvoicePerOrder($orders->pluck('id')->all());
        $jenisOptions = $this->jenisOptions();

        return view('livewire.portal.pekerjaan', [
            'customer' => $customer,
            'orders' => $orders,
            'nomorInvoice' => $nomorInvoice,
            'alamatOptions' => $customer->addresses()->orderBy('id')->get(),
            'jenisOptions' => $jenisOptions,
        ])->layout('layouts.portal', ['title' => 'Pekerjaan']);
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return array<int, string>
     */
    private function nomorInvoicePerOrder(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return DB::table('invoice_orders')
            ->join('invoices', 'invoices.id', '=', 'invoice_orders.invoice_id')
            ->whereIn('invoice_orders.order_id', $orderIds)
            ->where('invoices.status', '!=', 'batal')
            ->pluck('invoices.nomor', 'invoice_orders.order_id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function jenisOptions(): array
    {
        return app(PortalAksesService::class)->orderQuery()
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->whereNotNull('order_items.nama_layanan')
            ->distinct()
            ->orderBy('order_items.nama_layanan')
            ->pluck('order_items.nama_layanan', 'order_items.nama_layanan')
            ->all();
    }
}
