<?php

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\CustomerAcUnit;
use App\Models\CustomerAddress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * dev-plan/16: layanan Customer — sebelumnya CRUD Customer langsung lewat
 * Eloquent di Filament, belum ada service layer. Dipakai jalur cepat
 * "Pelanggan Baru" di form Tambah Order (`create()` sekaligus alamat & unit
 * AC pertamanya, 1 transaksi). `CustomerResource` (menu Customer biasa)
 * TIDAK diubah, tetap lewat Eloquent langsung seperti sebelumnya.
 */
class CustomerService
{
    use RestrictsByRole;

    /**
     * Cari customer dgn no_hp PERSIS SAMA — dipakai warning reaktif di
     * form (dev-plan/16, B60), BUKAN validasi keras yang menolak submit.
     */
    public function cariByNoHp(string $noHp): ?Customer
    {
        $noHp = trim($noHp);

        if ($noHp === '') {
            return null;
        }

        return Customer::query()->where('no_hp', $noHp)->first();
    }

    /**
     * Buat customer baru sekaligus alamat utama & (opsional) unit AC
     * pertamanya, 1 transaksi (dev-plan/16, B58/B58b). Kode unit
     * di-generate otomatis, bukan diisi admin.
     *
     * @param  array{nama?: string, no_hp?: string, jenis?: ?string, area?: ?string, sumber_lead?: ?string, email?: ?string, alamat_pengerjaan?: ?string, kode_ruangan?: ?string, jenis_unit?: ?string, pk?: ?string}  $data
     */
    public function create(array $data, User $by): Customer
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $nama = trim((string) ($data['nama'] ?? ''));
        if ($nama === '') {
            throw new BusinessRuleException('Nama pelanggan wajib diisi.');
        }

        $noHp = trim((string) ($data['no_hp'] ?? ''));
        if ($noHp === '') {
            throw new BusinessRuleException('No. HP pelanggan wajib diisi.');
        }

        return DB::transaction(function () use ($data, $nama, $noHp): Customer {
            // area/sumber_lead/jenis punya default di kolom DB — jangan kirim
            // null eksplisit (itu menimpa default & bisa gagal NOT NULL),
            // cukup lewati key-nya kalau tidak diisi.
            $customerData = array_filter([
                'nama' => $nama,
                'no_hp' => $noHp,
                'jenis' => $data['jenis'] ?? null,
                'area' => $data['area'] ?? null,
                'sumber_lead' => $data['sumber_lead'] ?? null,
                'email' => $data['email'] ?? null,
            ], fn ($value) => filled($value));
            $customerData['status'] = CustomerStatus::Lead->value;

            $customer = Customer::create($customerData);

            $alamatText = trim((string) ($data['alamat_pengerjaan'] ?? ''));

            $alamat = $alamatText !== ''
                ? CustomerAddress::create(['customer_id' => $customer->id, 'alamat' => $alamatText])
                : null;

            $kodeRuangan = trim((string) ($data['kode_ruangan'] ?? ''));

            if ($kodeRuangan !== '' && $alamat !== null) {
                CustomerAcUnit::create([
                    'customer_id' => $customer->id,
                    'customer_address_id' => $alamat->id,
                    'kode_unit' => $this->generateKodeUnit($alamat->id),
                    'kode_ruangan' => $kodeRuangan,
                    'jenis_unit' => $data['jenis_unit'] ?? null,
                    'pk' => filled($data['pk'] ?? null) ? $data['pk'] : null,
                ]);
            }

            return $customer->fresh();
        });
    }

    /**
     * "AC-01", "AC-02", dst — urut per alamat (dev-plan/16, B58b). Admin
     * boleh ubah manual belakangan lewat halaman Customer kalau perlu.
     */
    private function generateKodeUnit(int $alamatId): string
    {
        $urutan = CustomerAcUnit::query()->where('customer_address_id', $alamatId)->count() + 1;

        return sprintf('AC-%02d', $urutan);
    }

    /**
     * Buat customer TANPA alamat/unit apapun (dev-plan/18 — order multi-
     * alamat) — alamat & unit AC-nya sekarang ditangani seragam utk customer
     * baru/lama oleh OrderService::createOrders(). Validasi & pesan error
     * identik dgn create(), tapi method ini disengaja terpisah/duplikat
     * ringan supaya create() (dipakai test lain) tidak tersentuh sama sekali.
     *
     * @param  array{nama?: string, no_hp?: string, jenis?: ?string, area?: ?string, sumber_lead?: ?string, email?: ?string}  $data
     */
    public function createBare(array $data, User $by): Customer
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $nama = trim((string) ($data['nama'] ?? ''));
        if ($nama === '') {
            throw new BusinessRuleException('Nama pelanggan wajib diisi.');
        }

        $noHp = trim((string) ($data['no_hp'] ?? ''));
        if ($noHp === '') {
            throw new BusinessRuleException('No. HP pelanggan wajib diisi.');
        }

        $customerData = array_filter([
            'nama' => $nama,
            'no_hp' => $noHp,
            'jenis' => $data['jenis'] ?? null,
            'area' => $data['area'] ?? null,
            'sumber_lead' => $data['sumber_lead'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($value) => filled($value));
        $customerData['status'] = CustomerStatus::Lead->value;

        return Customer::create($customerData);
    }

    /**
     * Tambah satu alamat ke customer (dev-plan/18). `is_utama` sengaja tidak
     * disentuh di sini — CustomerAddress::booted() otomatis menandai alamat
     * PERTAMA milik customer sbg utama, siapapun pemanggilnya.
     *
     * @param  array{alamat?: string, nama_lokasi?: ?string, maps_link?: ?string, catatan?: ?string}  $data
     */
    public function createAddress(Customer $customer, array $data, User $by): CustomerAddress
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $alamat = trim((string) ($data['alamat'] ?? ''));
        if ($alamat === '') {
            throw new BusinessRuleException('Alamat wajib diisi.');
        }

        return CustomerAddress::create([
            'customer_id' => $customer->id,
            'alamat' => $alamat,
            'nama_lokasi' => filled($data['nama_lokasi'] ?? null) ? $data['nama_lokasi'] : null,
            'maps_link' => filled($data['maps_link'] ?? null) ? $data['maps_link'] : null,
            'catatan' => filled($data['catatan'] ?? null) ? $data['catatan'] : null,
        ]);
    }

    /**
     * Tambah unit AC baru langsung dari form order (dev-plan/18) —
     * kode_unit di-generate otomatis per alamat, PERSIS sama seperti
     * create(). WAJIB dipanggil satu-per-satu berurutan (bukan dibatch)
     * kalau lebih dari satu unit baru ditambahkan ke alamat yang sama dalam
     * satu submission, supaya penomoran AC-01/AC-02/dst tidak bentrok.
     *
     * @param  array{kode_ruangan?: string, jenis_unit?: ?string, pk?: ?string, catatan?: ?string}  $data
     */
    public function createAcUnitInline(CustomerAddress $alamat, array $data, User $by): CustomerAcUnit
    {
        $this->assertRole($by, [RoleName::Admin, RoleName::Owner]);

        $kodeRuangan = trim((string) ($data['kode_ruangan'] ?? ''));
        if ($kodeRuangan === '') {
            throw new BusinessRuleException('Ruangan/lokasi unit AC wajib diisi.');
        }

        return CustomerAcUnit::create([
            'customer_id' => $alamat->customer_id,
            'customer_address_id' => $alamat->id,
            'kode_unit' => $this->generateKodeUnit($alamat->id),
            'kode_ruangan' => $kodeRuangan,
            'jenis_unit' => $data['jenis_unit'] ?? null,
            'pk' => filled($data['pk'] ?? null) ? $data['pk'] : null,
            'catatan' => filled($data['catatan'] ?? null) ? $data['catatan'] : null,
        ]);
    }
}
