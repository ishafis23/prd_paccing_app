<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Jobs\BuatLaporanBulananJob;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\LaporanBulanan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Laporan Bulanan per Customer (dev-plan/21 §6): satu PDF berisi semua
 * kunjungan (order) customer pada satu bulan, terurut tanggal, opsional
 * dibatasi satu cabang. Jalur: diantrekan (job) ATAU sinkron — keduanya
 * memanggil kerjakan() yang sama sehingga hasilnya identik.
 */
class LaporanBulananService
{
    use RestrictsByRole;

    private const ADMIN_ROLES = [RoleName::Owner, RoleName::Admin, RoleName::Finance];

    public function __construct(
        private readonly LaporanPengerjaanService $laporan,
        private readonly LaporanPdfService $pdf,
        private readonly StorageQuotaService $kuota,
    ) {}

    /**
     * Catat permintaan, lalu kerjakan sekarang (sinkron) atau antrekan job.
     *
     * @throws BusinessRuleException|\Illuminate\Auth\Access\AuthorizationException
     */
    public function ajukan(Customer $customer, string $bulan, ?int $alamatId, User $oleh, bool $sinkron = false): LaporanBulanan
    {
        $this->assertRole($oleh, self::ADMIN_ROLES);

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
            throw new BusinessRuleException('Bulan tidak valid (format: tahun-bulan, mis. 2026-09).');
        }

        if ($alamatId !== null && ! CustomerAddress::query()->whereKey($alamatId)->where('customer_id', $customer->id)->exists()) {
            throw new BusinessRuleException('Cabang bukan milik customer ini.');
        }

        $baris = LaporanBulanan::create([
            'customer_id' => $customer->id,
            'bulan' => $bulan,
            'customer_address_id' => $alamatId,
            'status' => LaporanBulanan::MENUNGGU,
            'dibuat_oleh' => $oleh->id,
        ]);

        if ($sinkron) {
            return $this->kerjakan($baris);
        }

        BuatLaporanBulananJob::dispatch($baris->id);

        return $baris;
    }

    /**
     * Order yang masuk laporan: customer + bulan (+ cabang), bukan batal,
     * terurut tanggal jadwal lalu id.
     *
     * @return Collection<int, Order>
     */
    public function orderPeriode(LaporanBulanan $permintaan): Collection
    {
        $awal = Carbon::createFromFormat('!Y-m', $permintaan->bulan);

        return Order::query()
            ->where('customer_id', $permintaan->customer_id)
            ->whereDate('tanggal_jadwal', '>=', $awal->copy()->startOfMonth()->toDateString())
            ->whereDate('tanggal_jadwal', '<=', $awal->copy()->endOfMonth()->toDateString())
            ->where('status', '!=', OrderStatus::Batal->value)
            ->when($permintaan->customer_address_id, fn ($q, $id) => $q->where('customer_address_id', $id))
            ->with(['customer', 'customerAddress', 'serviceCatalog', 'orderItems', 'workReports.photos'])
            ->orderBy('tanggal_jadwal')
            ->orderBy('id')
            ->get();
    }

    /**
     * Susun + render + simpan PDF. Status & pesan error dicatat di baris
     * permintaan; error ASLI disimpan (bukan sekadar "gagal"). Tidak
     * melempar — aman dipanggil dari job maupun tombol sinkron.
     */
    public function kerjakan(LaporanBulanan $permintaan): LaporanBulanan
    {
        $permintaan->update(['status' => LaporanBulanan::DIPROSES, 'pesan_error' => null]);

        try {
            $orders = $this->orderPeriode($permintaan);

            if ($orders->isEmpty()) {
                throw new BusinessRuleException('Tidak ada order pada periode/cabang ini — tidak ada yang bisa dilaporkan.');
            }

            $customer = Customer::withTrashed()->findOrFail($permintaan->customer_id);
            $periode = Carbon::createFromFormat('!Y-m', $permintaan->bulan)->locale('id')->translatedFormat('F Y');
            $subjudul = 'Periode '.$periode.($permintaan->alamat ? ' · '.$permintaan->alamat->nama_lokasi : '');

            $isi = $this->pdf->render($this->laporan->dokumen($orders, $customer->nama, $subjudul));

            $this->kuota->pastikanCukup(strlen($isi));

            $disk = Storage::disk((string) config('penyimpanan.disk_laporan', 'local'));
            $path = trim((string) config('penyimpanan.folder_laporan', 'laporan'), '/').'/bulanan/'
                .$permintaan->id.'-'.Str::slug($customer->nama).'-'.$permintaan->bulan.'.pdf';

            if ($permintaan->path && $permintaan->path !== $path) {
                $disk->delete($permintaan->path);
            }
            $disk->put($path, $isi);
            StorageQuotaService::lupakanCache();

            $permintaan->update([
                'status' => LaporanBulanan::SELESAI,
                'path' => $path,
                'jumlah_order' => $orders->count(),
                'ukuran_bytes' => strlen($isi),
                'selesai_pada' => now(),
                'pesan_error' => null,
            ]);
        } catch (Throwable $e) {
            $this->catatGagal($permintaan, $e);
        }

        return $permintaan->fresh();
    }

    public function catatGagal(LaporanBulanan $permintaan, Throwable $e): void
    {
        $pesan = $e instanceof BusinessRuleException
            ? $e->getMessage()
            : class_basename($e).': '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')';

        $permintaan->update([
            'status' => LaporanBulanan::GAGAL,
            'pesan_error' => Str::limit($pesan, 2000, '…'),
            'selesai_pada' => now(),
        ]);
    }

    /**
     * Hapus permintaan beserta file PDF-nya.
     */
    public function hapus(LaporanBulanan $permintaan, User $oleh): void
    {
        $this->assertRole($oleh, self::ADMIN_ROLES);

        if ($permintaan->path) {
            Storage::disk((string) config('penyimpanan.disk_laporan', 'local'))->delete($permintaan->path);
            StorageQuotaService::lupakanCache();
        }

        $permintaan->delete();
    }
}
