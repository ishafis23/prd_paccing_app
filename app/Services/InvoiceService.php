<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Support\Url;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Invoice per order / multi-order (dev-plan/21 §7). Satu-satunya tempat yang
 * membuat nomor, menurunkan baris dari order_items, menghitung total & saldo,
 * dan menyinkronkan status dengan pembayaran. Izin: Owner/Admin/Finance.
 */
class InvoiceService
{
    use RestrictsByRole;

    private const MAKS_PERCOBAAN_NOMOR = 8;

    public function __construct(private readonly BusinessInfoService $usaha) {}

    /** @return array<int, RoleName> */
    private function peranBerhak(): array
    {
        return [RoleName::Owner, RoleName::Admin, RoleName::Finance];
    }

    public static function boleh(?User $user): bool
    {
        return $user?->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value]) ?? false;
    }

    /**
     * Order boleh ditagih: selesai/butuh_followup dan belum punya invoice aktif.
     */
    public function bisaDibuatInvoice(Order $order): bool
    {
        return in_array($order->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)
            && ! $this->punyaInvoiceAktif($order);
    }

    public function punyaInvoiceAktif(Order $order): bool
    {
        return Invoice::query()->aktif()->whereHas('orders', fn ($q) => $q->where('orders.id', $order->id))->exists();
    }

    /** Invoice aktif (bukan batal) pada suatu order, bila ada. */
    public function invoiceAktifOrder(Order $order): ?Invoice
    {
        return Invoice::query()->aktif()->whereHas('orders', fn ($q) => $q->where('orders.id', $order->id))->latest('id')->first();
    }

    /**
     * Order milik customer yang masih bisa dimasukkan ke invoice baru.
     *
     * @return Collection<int, Order>
     */
    public function orderTersedia(Customer|int $customer): Collection
    {
        $id = $customer instanceof Customer ? $customer->id : $customer;

        return Order::query()
            ->with(['customerAddress', 'orderItems'])
            ->where('customer_id', $id)
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->whereNotIn('id', DB::table('invoice_orders')
                ->join('invoices', 'invoices.id', '=', 'invoice_orders.invoice_id')
                ->where('invoices.status', '!=', InvoiceStatus::Batal->value)
                ->select('invoice_orders.order_id'))
            ->orderBy('tanggal_jadwal')
            ->orderBy('id')
            ->get();
    }

    /**
     * Buat invoice dari satu/banyak order milik customer yang sama.
     *
     * @param  iterable<int, Order>  $orders
     * @param  array{tanggal?: mixed, jatuh_tempo?: mixed, catatan?: ?string}  $opsi
     *
     * @throws BusinessRuleException|\Illuminate\Auth\Access\AuthorizationException
     */
    public function buat(iterable $orders, User $by, array $opsi = []): Invoice
    {
        $this->assertRole($by, $this->peranBerhak());

        $orders = collect($orders)->unique('id')->sortBy([['tanggal_jadwal', 'asc'], ['id', 'asc']])->values();

        if ($orders->isEmpty()) {
            throw new BusinessRuleException('Pilih minimal satu order.');
        }

        if ($orders->pluck('customer_id')->unique()->count() > 1) {
            throw new BusinessRuleException('Semua order dalam satu invoice harus milik customer yang sama.');
        }

        foreach ($orders as $order) {
            if (! in_array($order->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)) {
                throw new BusinessRuleException("Order #{$order->id} belum selesai — invoice hanya untuk order selesai/butuh follow-up.");
            }
            if ($this->punyaInvoiceAktif($order)) {
                throw new BusinessRuleException("Order #{$order->id} sudah punya invoice aktif.");
            }
        }

        $tanggal = filled($opsi['tanggal'] ?? null) ? Carbon::parse($opsi['tanggal'])->startOfDay() : now()->startOfDay();
        $jatuhTempo = filled($opsi['jatuh_tempo'] ?? null) ? Carbon::parse($opsi['jatuh_tempo'])->startOfDay() : $tanggal->copy()->addDays(7);

        if ($jatuhTempo->lt($tanggal)) {
            throw new BusinessRuleException('Jatuh tempo tidak boleh sebelum tanggal invoice.');
        }

        $bank = $this->usaha->data();
        $kesalahan = null;

        for ($percobaan = 0; $percobaan < self::MAKS_PERCOBAAN_NOMOR; $percobaan++) {
            try {
                return DB::transaction(function () use ($orders, $by, $opsi, $tanggal, $jatuhTempo, $bank): Invoice {
                    $invoice = Invoice::create([
                        'nomor' => $this->nomorUntuk($tanggal),
                        'customer_id' => $orders->first()->customer_id,
                        'tanggal' => $tanggal->toDateString(),
                        'jatuh_tempo' => $jatuhTempo->toDateString(),
                        'status' => InvoiceStatus::Draft,
                        'catatan' => filled($opsi['catatan'] ?? null) ? trim((string) $opsi['catatan']) : null,
                        'bank_nama' => $bank->bank_nama,
                        'bank_rekening' => $bank->bank_rekening,
                        'bank_atas_nama' => $bank->bank_atas_nama,
                        'dibuat_oleh' => $by->id,
                    ]);

                    $invoice->orders()->attach($orders->pluck('id')->all());

                    $urutan = 0;
                    foreach ($orders as $order) {
                        foreach ($this->barisDariOrder($order) as $baris) {
                            $invoice->items()->create($baris + ['urutan' => ++$urutan]);
                        }
                    }

                    return $this->hitungUlang($invoice);
                });
            } catch (UniqueConstraintViolationException $e) {
                // Dua invoice dibuat bersamaan mendapat nomor sama: transaksi
                // dibatalkan, ulangi penomoran (nomor kembar tidak pernah tersimpan).
                $kesalahan = $e;
            }
        }

        throw new BusinessRuleException('Gagal membuat nomor invoice unik, coba lagi.', 0, $kesalahan);
    }

    /**
     * Nomor berikutnya `INV-YYYYMM-0001` = urutan terbesar bulan itu + 1
     * (invoice batal tetap dihitung: nomor tidak pernah dipakai ulang).
     * Dipanggil DI DALAM transaksi; keunikan dijamin unique index `nomor`.
     */
    protected function nomorUntuk(Carbon $tanggal): string
    {
        $awalan = 'INV-'.$tanggal->format('Ym').'-';

        $terakhir = Invoice::query()
            ->where('nomor', 'like', $awalan.'%')
            ->lockForUpdate()
            ->pluck('nomor')
            ->map(fn (string $n): int => (int) substr($n, strlen($awalan)))
            ->max() ?? 0;

        return $awalan.str_pad((string) ($terakhir + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Baris invoice dari order_items aktif (tidak dibatalkan; baris penyesuaian
     * koreksi total ikut agar Σ = Order::total()). Deskripsi otomatis =
     * tanggal jadwal + nama lokasi/cabang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function barisDariOrder(Order $order): array
    {
        $order->loadMissing(['orderItems', 'customerAddress']);

        $deskripsi = collect([
            $order->tanggal_jadwal?->copy()->locale('id')->translatedFormat('j F Y'),
            trim((string) $order->customerAddress?->nama_lokasi) ?: null,
        ])->filter()->implode("\n");

        return $order->orderItems
            ->reject(fn (OrderItem $i): bool => $i->dibatalkan())
            ->map(function (OrderItem $i) use ($order, $deskripsi): array {
                $nama = (string) $i->nama_layanan;
                $nama = str_contains($nama, '_') ? str($nama)->headline()->toString() : ($nama !== '' ? $nama : 'Layanan');

                return [
                    'order_id' => $order->id,
                    'nama' => $nama,
                    'deskripsi' => $deskripsi !== '' ? $deskripsi : null,
                    'jumlah' => (float) $i->jumlah,
                    'harga' => (float) $i->harga,
                    'subtotal' => round((float) $i->harga * $i->jumlah, 2),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Ganti seluruh baris invoice (hanya selama draft). Tiap baris:
     * nama, deskripsi, jumlah, harga, order_id (opsional, harus order invoice ini).
     *
     * @param  array<int, array<string, mixed>>  $baris
     */
    public function perbaruiBaris(Invoice $invoice, array $baris, User $by, ?string $catatan = null, mixed $jatuhTempo = null): Invoice
    {
        $this->assertRole($by, $this->peranBerhak());

        if (! $invoice->draft()) {
            throw new BusinessRuleException('Baris invoice hanya bisa diubah selama status draft.');
        }

        $baris = collect($baris)->filter(fn (array $b): bool => filled($b['nama'] ?? null))->values();

        if ($baris->isEmpty()) {
            throw new BusinessRuleException('Invoice harus punya minimal satu baris.');
        }

        $orderSah = $invoice->orders()->pluck('orders.id')->all();

        return DB::transaction(function () use ($invoice, $baris, $orderSah, $catatan, $jatuhTempo): Invoice {
            $invoice->items()->delete();

            foreach ($baris as $i => $b) {
                $jumlah = (float) ($b['jumlah'] ?? 0);
                $harga = (float) ($b['harga'] ?? 0);

                if ($jumlah <= 0) {
                    throw new BusinessRuleException('Jumlah baris harus lebih dari 0.');
                }

                $orderId = filled($b['order_id'] ?? null) && in_array((int) $b['order_id'], $orderSah, true) ? (int) $b['order_id'] : null;

                $invoice->items()->create([
                    'order_id' => $orderId,
                    'nama' => trim((string) $b['nama']),
                    'deskripsi' => filled($b['deskripsi'] ?? null) ? trim((string) $b['deskripsi']) : null,
                    'jumlah' => $jumlah,
                    'harga' => $harga,
                    'subtotal' => round($jumlah * $harga, 2),
                    'urutan' => $i + 1,
                ]);
            }

            if ($catatan !== null) {
                $invoice->catatan = trim($catatan) !== '' ? trim($catatan) : null;
            }
            if (filled($jatuhTempo)) {
                $invoice->jatuh_tempo = Carbon::parse($jatuhTempo)->toDateString();
            }

            return $this->hitungUlang($invoice);
        });
    }

    /** subtotal & total SELALU dari invoice_items (bukan dari Order mentah). */
    public function hitungUlang(Invoice $invoice): Invoice
    {
        $jumlah = (float) $invoice->items()->get()->sum(fn (InvoiceItem $i): float => (float) $i->subtotal);

        $invoice->subtotal = round($jumlah, 2);
        $invoice->total = round($jumlah, 2);
        $invoice->save();
        $invoice->unsetRelation('items');

        return $invoice;
    }

    public function tandaiTerkirim(Invoice $invoice, User $by): Invoice
    {
        $this->assertRole($by, $this->peranBerhak());

        if (! $invoice->draft()) {
            throw new BusinessRuleException('Hanya invoice draft yang bisa ditandai terkirim.');
        }

        $this->pastikanToken($invoice);
        $invoice->status = InvoiceStatus::Terkirim;
        $invoice->save();

        return $this->segarkanStatus($invoice);
    }

    public function batalkan(Invoice $invoice, User $by): Invoice
    {
        $this->assertRole($by, $this->peranBerhak());

        if ($invoice->batal()) {
            throw new BusinessRuleException('Invoice sudah dibatalkan.');
        }

        $invoice->status = InvoiceStatus::Batal;
        $invoice->save();

        return $invoice;
    }

    /**
     * Status mengikuti pembayaran: `lunas` bila SEMUA order di invoice punya
     * payments.status = lunas; kalau tidak, tetap `terkirim`. Dihitung ulang
     * tiap invoice dibuka (halaman admin, halaman publik, unduh PDF, daftar) —
     * tanpa job terjadwal, sehingga selalu sesuai pembayaran terkini.
     * Draft & batal tidak disentuh.
     */
    public function segarkanStatus(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, [InvoiceStatus::Terkirim, InvoiceStatus::Lunas], true)) {
            return $invoice;
        }

        $invoice->unsetRelation('orders');
        $invoice->load('orders.payments');

        $semuaLunas = $invoice->orders->isNotEmpty() && $invoice->orders->every(
            fn (Order $o): bool => $o->payments->contains(fn (Payment $p): bool => $p->status === PaymentStatus::Lunas)
        );

        $baru = $semuaLunas ? InvoiceStatus::Lunas : InvoiceStatus::Terkirim;

        if ($invoice->status !== $baru) {
            $invoice->status = $baru;
            $invoice->save();
        }

        return $invoice;
    }

    public function segarkanSemuaAktif(): void
    {
        Invoice::query()
            ->whereIn('status', [InvoiceStatus::Terkirim->value, InvoiceStatus::Lunas->value])
            ->get()
            ->each(fn (Invoice $i) => $this->segarkanStatus($i));
    }

    public function pastikanToken(Invoice $invoice): string
    {
        if ($invoice->token === null) {
            $invoice->token = Str::random(40);
            $invoice->save();
        }

        return $invoice->token;
    }

    public function urlPublik(Invoice $invoice): string
    {
        return Url::absolute('invoice.publik', ['invoice' => $invoice->id, 'token' => $this->pastikanToken($invoice)]);
    }

    /**
     * Tautan wa.me berisi teks singkat + tautan publik (tanpa API pihak ketiga).
     */
    public function urlWa(Invoice $invoice): string
    {
        $invoice->loadMissing('customer');

        $teks = 'Halo '.($invoice->customer?->nama ?? '').', berikut invoice '.$invoice->nomor
            .' sebesar IDR '.number_format((float) $invoice->total, 0, ',', '.')
            .' dari '.$this->usaha->namaUsaha().'. Lihat & unduh: '.$this->urlPublik($invoice);

        $hp = preg_replace('/\D+/', '', (string) $invoice->customer?->no_hp);
        if ($hp !== '' && str_starts_with($hp, '0')) {
            $hp = '62'.substr($hp, 1);
        }

        return 'https://wa.me/'.$hp.'?text='.rawurlencode($teks);
    }

    /**
     * Data siap-render untuk Blade (halaman publik, PDF, ringkasan admin).
     *
     * @return array<string, mixed>
     */
    public function tampilan(Invoice $invoice): array
    {
        $invoice->load(['customer', 'items', 'orders.payments']);

        $dibayar = $invoice->totalDibayar();
        $lama = $invoice->jatuh_tempo !== null ? (int) $invoice->tanggal->diffInDays($invoice->jatuh_tempo) : null;

        return [
            'nomor' => $invoice->nomor,
            'status' => $invoice->status,
            'customer' => (string) ($invoice->customer?->nama ?? ''),
            'tanggal' => $invoice->tanggal,
            'jatuh_tempo' => $invoice->jatuh_tempo,
            'ketentuan' => $lama === null ? null : ($lama === 0 ? 'Jatuh tempo di Kuitansi' : 'Net '.(int) $lama.' hari'),
            'baris' => $invoice->items->values()->all(),
            'subtotal' => (float) $invoice->subtotal,
            'total' => (float) $invoice->total,
            'dibayar' => $dibayar,
            'saldo' => max(0.0, round((float) $invoice->total - $dibayar, 2)),
            'catatan' => $invoice->catatan,
            'bank' => [
                'nama' => $invoice->bank_nama,
                'rekening' => $invoice->bank_rekening,
                'atas_nama' => $invoice->bank_atas_nama,
            ],
            'kop' => app(LaporanPengerjaanService::class)->kop(),
        ];
    }
}
