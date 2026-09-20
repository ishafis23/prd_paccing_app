<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Enums\ServiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTechnician;
use App\Models\ServiceCatalog;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Support\Facades\DB;

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

        $alamat = $this->resolveAlamat($data['customer_address_id'] ?? null, $customer);
        $acUnit = $this->resolveAcUnit($data['customer_ac_unit_id'] ?? null, $customer, $alamat);

        $order = new Order([
            'customer_id' => $customer->id,
            'customer_address_id' => $alamat?->id,
            'service_catalog_id' => $catalog->id,
            'customer_ac_unit_id' => $acUnit?->id,
            'teknisi_id' => $teknisi?->id,
            'jumlah_unit' => $jumlahUnit,
            'alamat_pengerjaan' => $data['alamat_pengerjaan'] ?? $alamat?->alamat ?? $customer->alamat,
            'jenis_pelanggan' => $data['jenis_pelanggan'] ?? $customer->jenis?->value,
            'tanggal_jadwal' => $data['tanggal_jadwal'] ?? null,
            'jam_jadwal' => $data['jam_jadwal'] ?? null,
            'status' => $teknisi ? OrderStatus::Terjadwal : OrderStatus::Baru,
            'catatan_admin' => $data['catatan_admin'] ?? null,
            'is_klaim' => $data['is_klaim'] ?? false,
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
     * Buat order dari unit AC yang dicentang (dev-plan/14 — pengganti
     * "Order Massal via Excel"). Satu order = satu kunjungan: seluruh unit
     * terpilih harus berada di SATU alamat yg sama (filter alamat dulu di
     * tab Unit AC). Unit terpilih wajib milik customer tsb. Metode
     * pembuatan = pilih katalog + harga default + jadwal + tim (opsional),
     * lalu tiap unit menjadi satu `order_items` (backward-compatible dgn
     * alur order multi-item yg sudah ada, dev-plan/13).
     */
    public function createOrderDariUnits(Customer $customer, array $unitIds, array $data, User $creator): Order
    {
        $this->assertRole($creator, [RoleName::Admin, RoleName::Owner]);

        $catalog = ServiceCatalog::find($data['service_catalog_id'] ?? null);
        if ($catalog === null) {
            throw new BusinessRuleException('Jenis layanan wajib dipilih.');
        }

        if (! $catalog->aktif) {
            throw new BusinessRuleException('Jenis layanan sedang nonaktif.');
        }

        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        if ($unitIds === []) {
            throw new BusinessRuleException('Pilih minimal satu unit AC.');
        }

        $units = CustomerAcUnit::query()
            ->where('customer_id', $customer->id)
            ->whereIn('id', $unitIds)
            ->get();

        if ($units->count() !== count($unitIds)) {
            throw new BusinessRuleException('Ada unit yang dipilih bukan milik customer ini.');
        }

        $alamatIds = $units->pluck('customer_address_id')->filter()->unique();
        if ($alamatIds->count() > 1) {
            throw new BusinessRuleException('Pilih unit dari SATU alamat utk satu order/kunjungan — filter per alamat lalu centang ulang.');
        }

        $alamat = $alamatIds->first() !== null ? CustomerAddress::find($alamatIds->first()) : null;

        $hargaDefault = filled($data['harga'] ?? null) ? (float) $data['harga'] : (float) $catalog->harga;
        if ($hargaDefault < 0) {
            throw new BusinessRuleException('Harga default tidak valid.');
        }

        $namaLayanan = str($catalog->jenis_layanan->value)->headline()->toString();
        $kategori = $catalog->jenis_layanan;

        return DB::transaction(function () use ($customer, $alamat, $catalog, $units, $hargaDefault, $namaLayanan, $kategori, $data, $creator): Order {
            $order = new Order([
                'customer_id' => $customer->id,
                'customer_address_id' => $alamat?->id,
                'service_catalog_id' => $catalog->id,
                'jumlah_unit' => 1,
                'alamat_pengerjaan' => $alamat?->alamat ?? $customer->alamat,
                'jenis_pelanggan' => $customer->jenis?->value,
                'tanggal_jadwal' => $data['tanggal_jadwal'] ?? null,
                'jam_jadwal' => $data['jam_jadwal'] ?? null,
                'status' => OrderStatus::Baru,
                'catatan_admin' => filled($data['catatan_admin'] ?? null) ? $data['catatan_admin'] : null,
                'created_by' => $creator->id,
            ]);
            $order->save();

            // Order::booted() otomatis bikin 1 order_item stub — baris unit
            // pertama dipakai utk mengisi stub, sisanya di-create.
            $stub = $order->orderItems()->first();
            $stub->update([
                'customer_ac_unit_id' => $units->first()->id,
                'harga' => $hargaDefault,
            ]);

            foreach ($units->slice(1) as $unit) {
                $order->orderItems()->create([
                    'service_catalog_id' => $catalog->id,
                    'customer_ac_unit_id' => $unit->id,
                    'nama_layanan' => $namaLayanan,
                    'kategori' => $kategori,
                    'harga' => $hargaDefault,
                    'jumlah' => 1,
                ]);
            }

            if (filled($data['team_id'] ?? null)) {
                $this->assignTeam($order, Team::findOrFail($data['team_id']), $creator);
            }

            return $order->fresh();
        });
    }

    /**
     * Buat order dari wizard "Create Order" multi-alamat (dev-plan/18).
     * SATU submission bisa mencakup beberapa alamat (titik kunjungan);
     * krn aturan "1 order = 1 kunjungan = 1 alamat" tetap berlaku, hasilnya
     * SATU Order per blok alamat, tiap Order bisa punya beberapa
     * order_items (unit AC + layanan campuran, boleh beda katalog/harga per
     * baris) — 1 transaksi utk semua alamat sekaligus (all-or-nothing).
     *
     * TIDAK mengubah createOrder()/createOrderDariUnits() sama sekali —
     * keduanya tetap dipakai test/kode lain apa adanya.
     *
     * @return array<int, Order>
     */
    public function createOrders(array $data, User $creator): array
    {
        $this->assertRole($creator, [RoleName::Admin, RoleName::Owner]);

        $alamatList = array_values($data['alamat'] ?? []);
        if ($alamatList === []) {
            throw new BusinessRuleException('Minimal satu alamat wajib diisi.');
        }

        return DB::transaction(function () use ($data, $alamatList, $creator): array {
            $customerService = app(CustomerService::class);
            $modePelanggan = $data['mode_pelanggan'] ?? 'terdaftar';

            if ($modePelanggan === 'baru') {
                $customer = $customerService->createBare($data['pelanggan_baru'] ?? [], $creator);
            } else {
                $customer = Customer::find($data['customer_id'] ?? null);
                if ($customer === null) {
                    throw new BusinessRuleException('Customer wajib dipilih.');
                }
            }

            $orders = [];

            foreach ($alamatList as $i => $blok) {
                $label = 'Alamat ke-'.($i + 1);

                if (($blok['mode'] ?? 'baru') === 'existing') {
                    // Blank = "pakai alamat utama customer" (sama seperti
                    // createOrder() saat customer_address_id tidak diisi) —
                    // ini sekaligus yg bikin titik pertama otomatis "sama
                    // dgn data awal" kalau admin tidak pilih alamat lain.
                    $alamatId = filled($blok['customer_address_id'] ?? null) ? (int) $blok['customer_address_id'] : null;
                    // $alamat boleh null (customer belum punya alamat
                    // tersimpan sama sekali) — sama seperti createOrder(),
                    // order tetap dibuat, fallback ke $customer->alamat.
                    $alamat = $this->resolveAlamat($alamatId, $customer);
                } else {
                    $alamatBaru = $blok['alamat_baru'] ?? [];

                    // Titik pertama, mode pelanggan baru: kalau alamat tidak
                    // diisi ulang di sini, jatuh ke alamat awal dari Step 1
                    // (dev-plan/18 — "alamat pertama = sama dgn data awal").
                    if ($i === 0 && $modePelanggan === 'baru' && blank($alamatBaru['alamat'] ?? null)) {
                        $alamatBaru = array_merge($alamatBaru, [
                            'alamat' => $data['pelanggan_baru']['alamat'] ?? null,
                            'maps_link' => $alamatBaru['maps_link'] ?? ($data['pelanggan_baru']['maps_link'] ?? null),
                        ]);
                    }

                    $alamat = $customerService->createAddress($customer, $alamatBaru, $creator);
                }

                $teknisi = null;
                if (! empty($blok['teknisi_id'])) {
                    $teknisi = User::findOrFail($blok['teknisi_id']);
                    $this->assertRole($teknisi, [RoleName::Teknisi]);
                }

                $items = array_values($blok['items'] ?? []);
                if ($items === []) {
                    throw new BusinessRuleException("{$label} wajib punya minimal satu baris unit/layanan.");
                }

                $resolved = [];
                foreach ($items as $j => $item) {
                    $itemLabel = "{$label}, baris ke-".($j + 1);

                    $catalog = ServiceCatalog::find($item['service_catalog_id'] ?? null);
                    if ($catalog === null) {
                        throw new BusinessRuleException("{$itemLabel}: jenis layanan wajib dipilih.");
                    }
                    if (! $catalog->aktif) {
                        throw new BusinessRuleException("{$itemLabel}: jenis layanan sedang nonaktif.");
                    }

                    $unitMode = $item['unit_mode'] ?? 'tidak_ada';
                    $acUnit = match ($unitMode) {
                        'existing' => $this->resolveAcUnit($item['customer_ac_unit_id'] ?? null, $customer, $alamat),
                        'baru' => $alamat !== null
                            ? $customerService->createAcUnitInline($alamat, $item['unit_baru'] ?? [], $creator)
                            : throw new BusinessRuleException("{$itemLabel}: butuh alamat tersimpan utk menambah unit AC baru."),
                        default => null,
                    };

                    $harga = filled($item['harga'] ?? null) ? (float) $item['harga'] : (float) $catalog->harga;
                    if ($harga < 0) {
                        throw new BusinessRuleException("{$itemLabel}: harga tidak valid.");
                    }

                    $resolved[] = [
                        'service_catalog_id' => $catalog->id,
                        'customer_ac_unit_id' => $acUnit?->id,
                        'nama_layanan' => str($catalog->jenis_layanan->value)->headline()->toString(),
                        'kategori' => $catalog->jenis_layanan,
                        'harga' => $harga,
                        'jumlah' => max(1, (int) ($item['jumlah'] ?? 1)),
                        'catatan' => filled($item['catatan'] ?? null) ? $item['catatan'] : null,
                    ];
                }

                $first = $resolved[0];

                $order = new Order([
                    'customer_id' => $customer->id,
                    'customer_address_id' => $alamat?->id,
                    'service_catalog_id' => $first['service_catalog_id'],
                    'customer_ac_unit_id' => $first['customer_ac_unit_id'],
                    'teknisi_id' => $teknisi?->id,
                    'jumlah_unit' => 1,
                    'alamat_pengerjaan' => $alamat?->alamat ?? $customer->alamat,
                    'jenis_pelanggan' => $data['jenis_pelanggan'] ?? $customer->jenis?->value,
                    'tanggal_jadwal' => $blok['tanggal_jadwal'] ?? null,
                    'jam_jadwal' => $blok['jam_jadwal'] ?? null,
                    'status' => $teknisi ? OrderStatus::Terjadwal : OrderStatus::Baru,
                    'catatan_admin' => filled($blok['catatan_admin'] ?? null) ? $blok['catatan_admin'] : null,
                    'is_klaim' => $blok['is_klaim'] ?? false,
                    'created_by' => $creator->id,
                ]);
                $order->save();

                // Order::booted() otomatis bikin 1 order_item stub dari baris
                // pertama — update dgn data sebenarnya (hook selalu pakai
                // harga katalog, blm tau override per-baris), sisanya create().
                $stub = $order->orderItems()->first();
                $stub->update($first);

                foreach (array_slice($resolved, 1) as $it) {
                    $order->orderItems()->create($it);
                }

                if ($teknisi !== null) {
                    OrderTechnician::create(['order_id' => $order->id, 'teknisi_id' => $teknisi->id]);
                }

                $orders[] = $order->fresh();
            }

            return $orders;
        });
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
     * Assign tim baku ke order (dev-plan/12 §3.13) — alternatif dari
     * assignTechnician/tambahTeknisi manual satu-satu. Seluruh anggota tim
     * (PIC + lainnya) langsung tercatat sbg anggota order_technicians;
     * `orders.team_id` cuma jejak traceability, bukan sumber kebenaran.
     */
    public function assignTeam(Order $order, Team $team, User $actor): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (! $team->aktif) {
            throw new BusinessRuleException('Tim ini sudah nonaktif.');
        }

        $anggota = $team->members;
        if ($anggota->isEmpty() || $team->pic_teknisi_id === null) {
            throw new BusinessRuleException('Tim ini belum punya anggota.');
        }

        return DB::transaction(function () use ($order, $team, $actor, $anggota): Order {
            $order = $this->assignTechnician($order, $team->picTeknisi, $actor);

            foreach ($anggota as $teknisi) {
                if ((int) $teknisi->id === (int) $team->pic_teknisi_id) {
                    continue;
                }

                try {
                    $this->tambahTeknisi($order, $teknisi, $actor);
                } catch (BusinessRuleException) {
                    // sudah anggota (idempotent) -> lewati diam-diam.
                }
            }

            $order->team_id = $team->id;
            $order->save();

            return $order->fresh();
        });
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
     * Ganti PIC (penanggung jawab) order yang sudah berjalan (dev-plan/12
     * §3.5) — mis. teknisi berhalangan mendadak di hari-H. BEDA dari
     * assignTechnician: status order TIDAK direset ke terjadwal (order
     * boleh sudah menuju_lokasi/dikerjakan), PIC lama otomatis lepas dari
     * tim & attendance terbukanya (kalau ada) ditutup.
     */
    public function gantiPic(Order $order, User $teknisiBaru, User $actor, ?string $alasan = null): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);
        $this->assertRole($teknisiBaru, [RoleName::Teknisi]);

        if (in_array($order->status, [OrderStatus::Selesai, OrderStatus::Batal], true)) {
            throw new BusinessRuleException('Order selesai/batal tidak bisa ganti PIC.');
        }

        if ($order->teknisi_id === null) {
            throw new BusinessRuleException('Order belum punya PIC — gunakan aksi Assign Teknisi.');
        }

        if ((int) $order->teknisi_id === (int) $teknisiBaru->id) {
            throw new BusinessRuleException('Teknisi ini sudah menjadi PIC order ini.');
        }

        return DB::transaction(function () use ($order, $teknisiBaru, $alasan): Order {
            $picLama = $order->teknisi;
            $picLamaId = $order->teknisi_id;

            // Attendance terbuka milik PIC lama (kalau sempat check-in) ikut ditutup.
            $order->attendances()
                ->where('user_id', $picLamaId)
                ->whereNull('jam_keluar')
                ->update(['jam_keluar' => now()]);

            OrderTechnician::where('order_id', $order->id)
                ->where('teknisi_id', $picLamaId)
                ->delete();

            $order->teknisi_id = $teknisiBaru->id;
            $order->catatan_admin = trim(($order->catatan_admin ?? '')
                ."\n[GANTI PIC] {$picLama?->name} -> {$teknisiBaru->name}".(filled($alasan) ? ': '.trim($alasan) : ''));
            $order->save();

            OrderTechnician::firstOrCreate([
                'order_id' => $order->id,
                'teknisi_id' => $teknisiBaru->id,
            ]);

            return $order->fresh();
        });
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
        $acUnit = $this->resolveAcUnit($data['customer_ac_unit_id'] ?? null, $order->customer);

        return $order->orderItems()->create([
            'customer_ac_unit_id' => $acUnit?->id,
            'nama_layanan' => $namaLayanan,
            'kategori' => $kategori,
            'harga' => $harga,
            'jumlah' => $jumlah,
            'catatan' => filled($data['catatan'] ?? null) ? $data['catatan'] : null,
            'ditambahkan_oleh' => $actor->id,
        ]);
    }

    /**
     * Resolve alamat utk createOrder (dev-plan/14) — kalau tidak diisi,
     * fallback ke alamat utama / alamat pertama customer. Kalau diisi,
     * wajib milik customer tsb.
     */
    private function resolveAlamat(?int $alamatId, Customer $customer): ?CustomerAddress
    {
        if ($alamatId === null) {
            return $customer->alamatUtama() ?? $customer->alamatPertama();
        }

        $alamat = CustomerAddress::find($alamatId);
        if ($alamat === null || (int) $alamat->customer_id !== (int) $customer->id) {
            throw new BusinessRuleException('Alamat tidak ditemukan atau bukan milik customer ini.');
        }

        return $alamat;
    }

    /**
     * Validasi Unit AC (dev-plan/12 §3.10 lanjutan, dev-plan/14) — kalau
     * diisi, harus milik customer yg sama dgn order/customer terkait; jika
     * alamat order juga diisi dan unit punya alamat, harus sama. `null` =
     * tidak ditautkan ke unit manapun (opsional, backward-compatible).
     */
    private function resolveAcUnit(?int $acUnitId, Customer $customer, ?CustomerAddress $alamat = null): ?CustomerAcUnit
    {
        if ($acUnitId === null) {
            return null;
        }

        $unit = CustomerAcUnit::find($acUnitId);
        if ($unit === null || (int) $unit->customer_id !== (int) $customer->id) {
            throw new BusinessRuleException('Unit AC tidak ditemukan atau bukan milik customer ini.');
        }

        if ($alamat !== null && $unit->customer_address_id !== null && (int) $unit->customer_address_id !== (int) $alamat->id) {
            throw new BusinessRuleException('Unit AC yang dipilih tidak berada di alamat pilihan order.');
        }

        return $unit;
    }

    /**
     * Customer setuju atas laporan "Ada Perbaikan" (dev-plan/13 §2 langkah
     * 4) — admin tambah baris order_items baru (harga hasil deal) sekaligus
     * membersihkan flag "menunggu konfirmasi".
     */
    public function setujuiPerbaikan(Order $order, array $data, User $actor): OrderItem
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (! $order->perbaikan_menunggu_konfirmasi) {
            throw new BusinessRuleException('Tidak ada laporan perbaikan yang menunggu konfirmasi.');
        }

        return DB::transaction(function () use ($order, $data, $actor): OrderItem {
            $item = $this->tambahLayanan($order, $data, $actor);

            $order->perbaikan_menunggu_konfirmasi = false;
            $order->perbaikan_catatan = null;
            $order->perbaikan_estimasi_harga = null;
            $order->perbaikan_dilaporkan_oleh = null;
            $order->perbaikan_dilaporkan_pada = null;
            $order->save();

            return $item;
        });
    }

    /**
     * Customer tidak setuju atas laporan "Ada Perbaikan" (dev-plan/13 §2
     * langkah 4) — flag hilang tanpa baris order_items baru, teknisi lanjut
     * kerja sesuai order awal saja. Catatan penolakan (kalau ada) ikut
     * dicatat di catatan_admin sbg riwayat.
     */
    public function tolakPerbaikan(Order $order, User $actor, ?string $catatanPenolakan = null): Order
    {
        $this->assertRole($actor, [RoleName::Admin, RoleName::Owner]);

        if (! $order->perbaikan_menunggu_konfirmasi) {
            throw new BusinessRuleException('Tidak ada laporan perbaikan yang menunggu konfirmasi.');
        }

        $riwayat = '[PERBAIKAN DITOLAK] '.$order->perbaikan_catatan;
        if (filled($catatanPenolakan)) {
            $riwayat .= ' — '.trim($catatanPenolakan);
        }

        $order->catatan_admin = trim(($order->catatan_admin ?? '')."\n".$riwayat);
        $order->perbaikan_menunggu_konfirmasi = false;
        $order->perbaikan_catatan = null;
        $order->perbaikan_estimasi_harga = null;
        $order->perbaikan_dilaporkan_oleh = null;
        $order->perbaikan_dilaporkan_pada = null;
        $order->save();

        return $order->fresh();
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
        $order->catatan_admin = trim(($order->catatan_admin ?? '')."\n[BATAL] ".($alasan ?? 'dibatalkan admin'));
        $order->save();

        return $order->fresh();
    }
}
