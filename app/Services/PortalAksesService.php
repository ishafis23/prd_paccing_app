<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Satu-satunya pintu query Portal Corporate Customer (dev-plan/22 §4.1):
 * semua data dibatasi ke customer yang sedang login, sehingga ID milik
 * customer lain menghasilkan 404 (bukan 403). Komponen Livewire & controller
 * portal WAJIB lewat sini, tidak memanggil Model::query() langsung.
 */
class PortalAksesService
{
    public function customer(): Customer
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }

    public function customerId(): int
    {
        return (int) $this->customer()->getKey();
    }

    /**
     * Order milik customer login (status batal tidak ditampilkan).
     */
    public function orderQuery(): Builder
    {
        return Order::query()
            ->where('customer_id', $this->customerId())
            ->where('status', '!=', OrderStatus::Batal->value);
    }

    /**
     * Invoice milik customer login — hanya yang sudah terkirim/lunas
     * (draft & batal tidak pernah terlihat PIC, dev-plan/22 §9-8).
     */
    public function invoiceQuery(): Builder
    {
        return Invoice::query()
            ->where('customer_id', $this->customerId())
            ->whereIn('status', [InvoiceStatus::Terkirim->value, InvoiceStatus::Lunas->value]);
    }

    /**
     * Resolve order milik customer login atau 404.
     */
    public function order(int $id): Order
    {
        return $this->orderQuery()->whereKey($id)->firstOrFail();
    }

    /**
     * Resolve invoice milik customer login atau 404.
     */
    public function invoice(int $id): Invoice
    {
        return $this->invoiceQuery()->whereKey($id)->firstOrFail();
    }
}
