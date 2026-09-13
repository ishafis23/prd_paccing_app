<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTechnician;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Models\WorkReport;

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
            'jenis_pelanggan' => $data['jenis_pelanggan'] ?? $customer->jenis?->value,
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
     * Jadwalkan ulang order yang terkendala (Admin/Owner) — respons dari
     * teknisi menandai "Terkendala/Gagal" di lapangan. Order kembali ke
     * status `terjadwal` dgn jadwal baru; alasan kendala lama dibersihkan
     * (riwayatnya tetap tercatat di `catatan_admin`).
     */
    public function reschedule(Order $order, User $actor, string $tanggalJadwal, ?string $jamJadwal = null): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if ($order->status !== OrderStatus::Terkendala) {
            throw new BusinessRuleException('Hanya order berstatus terkendala yang bisa dijadwalkan ulang.');
        }

        $order->tanggal_jadwal = $tanggalJadwal;
        $order->jam_jadwal = $jamJadwal;
        $order->status = OrderStatus::Terjadwal;
        $order->catatan_admin = trim(($order->catatan_admin ?? '')."\n[JADWAL ULANG] ".$tanggalJadwal.($jamJadwal ? " {$jamJadwal}" : ''));
        $order->alasan_kendala = null;
        $order->save();

        return $order->fresh();
    }

    /**
     * Admin menambah baris layanan ke order yg sedang jalan (dev-plan/13
     * §1/§2 "Ada Perbaikan") — mis. sparepart pengganti yg disepakati
     * dgn customer setelah teknisi lapor kebutuhan perbaikan. Harga
     * SELALU diisi manual (hasil nego per kasus), bukan dari katalog.
     */
    public function tambahLayanan(Order $order, array $data, User $actor): OrderItem
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (in_array($order->status, [OrderStatus::Selesai, OrderStatus::Batal], true)) {
            throw new BusinessRuleException('Order selesai/batal tidak bisa ditambah layanan.');
        }

        $namaLayanan = trim((string) ($data['nama_layanan'] ?? ''));
        if ($namaLayanan === '') {
            throw new BusinessRuleException('Nama layanan wajib diisi.');
        }

        $harga = (float) ($data['harga'] ?? -1);
        if ($harga < 0) {
            throw new BusinessRuleException('Harga tidak valid.');
        }

        $jumlah = max(1, (int) ($data['jumlah'] ?? 1));
        $kategori = filled($data['kategori'] ?? null) ? ServiceType::from($data['kategori']) : null;

        return $order->orderItems()->create([
            'nama_layanan' => $namaLayanan,
            'kategori' => $kategori,
            'harga' => $harga,
            'jumlah' => $jumlah,
            'catatan' => filled($data['catatan'] ?? null) ? $data['catatan'] : null,
            'ditambahkan_oleh' => $actor->id,
        ]);
    }

    /**
     * Admin/Owner "acc" laporan pengerjaan teknisi (§3.11) — supaya admin
     * tidak terus menagih "mana laporan". Pelunasan pembayaran order
     * ditahan sampai laporan terbaru diverifikasi (lihat
     * PaymentService::recordPayment).
     */
    public function verifikasiLaporan(WorkReport $laporan, User $actor): WorkReport
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if ($laporan->sudahDiverifikasi()) {
            throw new BusinessRuleException('Laporan ini sudah diverifikasi.');
        }

        $laporan->diverifikasi_pada = now();
        $laporan->diverifikasi_oleh = $actor->id;
        $laporan->save();

        return $laporan->fresh();
    }

    /**
     * Batalkan order (Admin/Owner) — hanya dari status `baru`/`terjadwal`.
     */
    public function cancel(Order $order, User $actor, ?string $alasan = null): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (! in_array($order->status, [OrderStatus::Baru, OrderStatus::Terjadwal, OrderStatus::Terkendala], true)) {
            throw new BusinessRuleException('Hanya order berstatus baru/terjadwal/terkendala yang bisa dibatalkan.');
        }

        $order->status = OrderStatus::Batal;
        $order->catatan_admin = trim(($order->catatan_admin ?? '') . "\n[BATAL] " . ($alasan ?? 'dibatalkan admin'));
        $order->save();

        return $order->fresh();
    }
}
