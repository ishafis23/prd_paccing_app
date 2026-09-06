<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderTechnician;
use App\Models\ServiceCatalog;
use App\Models\User;

class OrderService
{
    use RestrictsByRole;

    /**
     * Buat order baru (aksi Admin/Owner).
     * Jika teknisi diisi sekaligus -> status `terjadwal`; jika tidak -> `baru`.
     */
    public function createOrder(array $data, User $creator): Order
    {
        $this->assertRole($creator, [RoleName::Admin, RoleName::Owner]);

        $customer = Customer::findOrFail($data['customer_id']);
        $catalog = ServiceCatalog::findOrFail($data['service_catalog_id']);

        if (! $catalog->aktif) {
            throw new BusinessRuleException('Jenis layanan sedang nonaktif.');
        }

        $jumlahUnit = (int) ($data['jumlah_unit'] ?? 1);
        if ($jumlahUnit < 1) {
            throw new BusinessRuleException('Jumlah unit minimal 1.');
        }

        $teknisi = null;
        if (! empty($data['teknisi_id'])) {
            $teknisi = User::findOrFail($data['teknisi_id']);
            $this->assertRole($teknisi, [RoleName::Teknisi]);
        }

        $order = new Order([
            'customer_id' => $customer->id,
            'service_catalog_id' => $catalog->id,
            'teknisi_id' => $teknisi?->id,
            'jumlah_unit' => $jumlahUnit,
            'alamat_pengerjaan' => $data['alamat_pengerjaan'] ?? $customer->alamat,
            'tanggal_jadwal' => $data['tanggal_jadwal'] ?? null,
            'jam_jadwal' => $data['jam_jadwal'] ?? null,
            'status' => $teknisi ? OrderStatus::Terjadwal : OrderStatus::Baru,
            'catatan_admin' => $data['catatan_admin'] ?? null,
            'created_by' => $creator->id,
        ]);
        $order->save();

        // B21: PIC pertama otomatis menjadi anggota tim.
        if ($teknisi !== null) {
            OrderTechnician::create(['order_id' => $order->id, 'teknisi_id' => $teknisi->id]);
        }

        return $order->fresh();
    }

    /**
     * Assign teknisi ke order (Admin/Owner). Order berstatus `baru`
     * atau belum-selesai yang belum punya teknisi.
     */
    public function assignTechnician(Order $order, User $teknisi, User $actor): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if (in_array($order->status, [OrderStatus::Selesai, OrderStatus::Batal], true)) {
            throw new BusinessRuleException('Order selesai/batal tidak bisa di-assign ulang.');
        }

        $order->teknisi_id = $teknisi->id;
        $order->status = OrderStatus::Terjadwal;
        $order->save();

        // B21: PIC juga dicatat sebagai anggota tim (idempotent).
        OrderTechnician::firstOrCreate([
            'order_id' => $order->id,
            'teknisi_id' => $teknisi->id,
        ]);

        return $order->fresh();
    }

    /**
     * Tambah anggota tim pengerjaan (B21) — Admin/Owner.
     * Order harus belum selesai/batal; teknisi belum menjadi anggota.
     */
    public function tambahTeknisi(Order $order, User $teknisi, User $actor): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);
        $this->assertRole($teknisi, [RoleName::Teknisi]);

        if (in_array($order->status, [OrderStatus::Selesai, OrderStatus::Batal], true)) {
            throw new BusinessRuleException('Order selesai/batal tidak bisa ditambah anggota tim.');
        }

        $sudahAnggota = $order->orderTechnicians()
            ->where('teknisi_id', $teknisi->id)
            ->exists()
            || (int) $order->teknisi_id === (int) $teknisi->id;

        if ($sudahAnggota) {
            throw new BusinessRuleException('Teknisi sudah menjadi anggota tim order ini.');
        }

        OrderTechnician::create([
            'order_id' => $order->id,
            'teknisi_id' => $teknisi->id,
        ]);

        return $order->fresh();
    }

    /**
     * Batalkan order (Admin/Owner) — hanya dari status `baru`/`terjadwal`.
     */
    public function cancel(Order $order, User $actor, ?string $alasan = null): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (! in_array($order->status, [OrderStatus::Baru, OrderStatus::Terjadwal], true)) {
            throw new BusinessRuleException('Hanya order berstatus baru/terjadwal yang bisa dibatalkan.');
        }

        $order->status = OrderStatus::Batal;
        $order->catatan_admin = trim(($order->catatan_admin ?? '') . "\n[BATAL] " . ($alasan ?? 'dibatalkan admin'));
        $order->save();

        return $order->fresh();
    }
}
