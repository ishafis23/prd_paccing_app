<?php

namespace App\Support\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\CashHandover;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderUnitReport;
use App\Models\TeknisiExpense;
use App\Models\TechnicianEarlyLeave;
use App\Models\TechnicianIncentive;
use App\Models\User;
use App\Models\WorkReportPhoto;
use App\Services\PaymentChannelService;
use App\Services\TeknisiService;
use App\Services\UnitReportService;
use App\Support\FotoLaporanSlot;
use Illuminate\Support\Collection;

/**
 * Bentuk JSON API mobile teknisi (dev-plan/mobile-app/02 §3): uang = integer
 * rupiah, tanggal = Y-m-d, waktu = ISO 8601. Satu tempat agar kontrak stabil.
 */
class Presenter
{
    public static function url(?string $path): ?string
    {
        return filled($path) ? asset('storage/'.ltrim((string) $path, '/')) : null;
    }

    private const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

    private const BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** "Sel, 10 Nov 2026" — tanpa bergantung pada locale server. */
    public static function tanggalIndonesia(?\DateTimeInterface $tanggal): ?string
    {
        if ($tanggal === null) {
            return null;
        }

        return sprintf('%s, %d %s %s', self::HARI[(int) $tanggal->format('w')], (int) $tanggal->format('j'), self::BULAN[(int) $tanggal->format('n')], $tanggal->format('Y'));
    }

    public static function rupiah(float|int|string|null $nilai): int
    {
        return (int) round((float) $nilai);
    }

    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'foto_url' => $user->fotoProfilUrl(),
        ];
    }

    /** Tautan Google Maps lokasi order (alamat tersimpan > koordinat customer > pencarian alamat). */
    public static function mapsUrl(Order $order): ?string
    {
        $alamat = $order->customerAddress;

        if (filled($alamat?->maps_link)) {
            return $alamat->maps_link;
        }

        $lat = $alamat?->latitude ?? $order->customer?->latitude;
        $lng = $alamat?->longitude ?? $order->customer?->longitude;

        if ($lat !== null && $lng !== null) {
            return "https://www.google.com/maps?q={$lat},{$lng}";
        }

        $teks = $order->alamat_pengerjaan ?: ($alamat?->alamat ?? $order->customer?->alamat);

        return filled($teks) ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $teks) : null;
    }

    /** Ringkasan order untuk daftar (jadwal/riwayat/lengkapi). */
    public static function orderRingkas(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'tanggal_jadwal' => $order->tanggal_jadwal?->toDateString(),
            'jam_jadwal' => $order->jam_jadwal ? substr((string) $order->jam_jadwal, 0, 5) : null,
            'tanggal_tampil' => self::tanggalIndonesia($order->tanggal_jadwal),
            'titik' => $order->titik ? ['id' => $order->titik->id, 'nama' => $order->titik->nama, 'urutan' => (int) $order->titik->urutan, 'jam' => substr((string) $order->titik->jam, 0, 5)] : null,
            'customer' => ['nama' => $order->customer?->nama, 'no_hp' => $order->customer?->no_hp, 'wa_url' => self::waUrl($order->customer?->no_hp)],
            'alamat' => $order->alamat_pengerjaan ?: $order->customerAddress?->alamat ?: $order->customer?->alamat,
            'maps_url' => self::mapsUrl($order),
            'layanan' => $order->ringkasanLayanan(),
            'jumlah_unit' => $order->jumlahUnit(),
            'total' => self::rupiah($order->total()),
            'is_klaim' => (bool) $order->is_klaim,
            'ditutup' => $order->sudahDitutup(),
            'updated_at' => $order->updated_at?->toIso8601String(),
        ] + ($order->relationLoaded('workReports') ? [
            // Catatan pengerjaan teknisi (revisi 14 Nov poin 16) — hanya bila relasi sudah dimuat (riwayat).
            'catatan_pengerjaan' => $order->workReports->sortByDesc('id')->first()?->catatan_pengerjaan,
        ] : []);
    }

    /** Tautan chat WhatsApp langsung ke customer (https://wa.me/62...), null bila nomor kosong. */
    public static function waUrl(?string $noHp): ?string
    {
        $digit = preg_replace('/\D/', '', (string) $noHp) ?? '';
        if ($digit === '') {
            return null;
        }
        if (str_starts_with($digit, '0')) {
            $digit = '62'.substr($digit, 1);
        } elseif (! str_starts_with($digit, '62')) {
            $digit = '62'.$digit;
        }

        return 'https://wa.me/'.$digit;
    }

    /**
     * Status kunci urutan titik untuk teknisi: null bila terbuka, selain itu info titik penghalang.
     *
     * @return array{terkunci: bool, terkunci_oleh: ?string, pesan_terkunci: ?string}
     */
    public static function kunciTitik(Order $order, User $teknisi): array
    {
        $penghalang = $order->status === \App\Enums\OrderStatus::Terjadwal
            ? app(TeknisiService::class)->titikPenghalang($order, $teknisi)
            : null;

        return [
            'terkunci' => $penghalang !== null,
            'terkunci_oleh' => $penghalang?->titik?->nama,
            'pesan_terkunci' => $penghalang !== null ? app(TeknisiService::class)->pesanTitikTerkunci($penghalang) : null,
        ];
    }

    public static function foto(WorkReportPhoto $foto): array
    {
        return [
            'id' => $foto->id,
            'order_item_id' => $foto->order_item_id,
            'unit_id' => $foto->order_unit_report_id,
            'slot' => $foto->slot,
            'url' => self::url($foto->path),
        ];
    }

    public static function unit(OrderUnitReport $unit, UnitReportService $svc, Collection $fotoUnit): array
    {
        $item = $unit->orderItem;

        return [
            'id' => $unit->id,
            'order_item_id' => $unit->order_item_id,
            'unit_no' => $unit->unit_no,
            'nama_layanan' => $item?->nama_layanan,
            'lokasi_label' => $unit->lokasi_label,
            'posisi' => $unit->posisi,
            'jenis_pekerjaan' => $unit->jenis_pekerjaan,
            'bagian' => $unit->bagian ?: 'indoor',
            'suhu' => $unit->suhu === null ? null : (float) $unit->suhu,
            'rpm' => $unit->rpm === null ? null : (float) $unit->rpm,
            'kondisi' => $unit->kondisi,
            'catatan_kondisi' => $unit->catatan_kondisi,
            'field_set' => $item ? $svc->fieldSetTampil($item) : 'bebas',
            'butuh_keterangan' => $item ? $svc->butuhKeterangan($item) : false,
            'suhu_wajib' => $item ? $svc->suhuWajib($item) : false,
            'lengkap' => $item ? $svc->lengkap($unit, $item) : false,
            'foto' => $fotoUnit->map(fn (WorkReportPhoto $f): array => self::foto($f))->values()->all(),
        ];
    }

    /** Detail order lengkap untuk teknisi (anggota tim). */
    public static function orderDetail(Order $order, User $teknisi): array
    {
        $order->loadMissing([
            'customer', 'customerAddress', 'titik', 'orderItems.acUnit', 'workReports.photos', 'latestPayment',
            'cashHandovers', 'orderTechnicians.teknisi', 'teknisi', 'kendalaBuktis', 'videos',
        ]);

        $teknisiSvc = app(TeknisiService::class);
        $unitSvc = app(UnitReportService::class);

        $units = collect();
        if (in_array($order->status, [OrderStatus::Dikerjakan, OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)) {
            $units = $unitSvc->bolehDisiapkanOtomatis($order) ? $unitSvc->siapkan($order) : $unitSvc->unitAktif($order);
        }

        $semuaFoto = $order->workReports->flatMap->photos;
        $unitPerItem = $units->groupBy('order_item_id');

        $unitJson = $units->map(function (OrderUnitReport $unit) use ($unitSvc, $semuaFoto, $unitPerItem): array {
            $pertama = $unitPerItem->get($unit->order_item_id)->min('unit_no') === $unit->unit_no;
            $foto = $semuaFoto
                ->where('order_item_id', $unit->order_item_id)
                ->filter(fn ($p): bool => $p->order_unit_report_id === null ? $pertama : (int) $p->order_unit_report_id === $unit->id)
                ->sortBy('urutan')
                ->values();

            return self::unit($unit, $unitSvc, $foto);
        })->values()->all();

        $items = $order->orderItems->map(function (OrderItem $item) use ($unitSvc, $unitPerItem, $semuaFoto): array {
            $fotoItem = $semuaFoto->where('order_item_id', $item->id);
            $perUnit = $unitSvc->fotoPerUnit($item, $unitPerItem->get($item->id));
            $wajib = FotoLaporanSlot::wajibUntuk($item->kategori);
            $dianjurkan = FotoLaporanSlot::dianjurkanUntuk($item->kategori);

            return [
                'id' => $item->id,
                'nama_layanan' => $item->nama_layanan,
                'kategori' => $item->kategori?->value ?? (string) $item->kategori,
                'komponen' => $item->komponen?->value,
                'harga' => self::rupiah($item->harga),
                'jumlah' => (int) $item->jumlah,
                'catatan' => $item->catatan,
                'dibatalkan' => $item->dibatalkan(),
                'penyesuaian' => (bool) $item->penyesuaian,
                'punya_foto' => $fotoItem->isNotEmpty(),
                'foto_per_unit' => $perUnit,
                'slot_foto' => collect(FotoLaporanSlot::untuk($item->kategori))->map(fn (string $label, string $kode): array => [
                    'kode' => $kode,
                    'label' => $label,
                    'wajib' => array_key_exists($kode, $wajib),
                    'dianjurkan' => array_key_exists($kode, $dianjurkan),
                    'foto' => $perUnit ? null : ($fotoItem->firstWhere('slot', $kode) ? self::foto($fotoItem->firstWhere('slot', $kode)) : null),
                ])->values()->all(),
            ];
        })->values()->all();

        $kurang = in_array($order->status, [OrderStatus::Selesai, OrderStatus::ButuhFollowup], true)
            ? collect($teknisiSvc->fotoWajibKurang($order))->map(fn (array $k): array => [
                'jenis' => $k['jenis'],
                'label' => $k['label'],
                'order_item_id' => $k['order_item']->id,
                'kode_slot' => $k['kode_slot'],
                'unit_id' => $k['unit_report_id'] ?? null,
                'unit_no' => $k['unit_no'] ?? null,
            ])->values()->all()
            : [];

        $laporan = $order->workReports->sortByDesc('id')->first();
        $bayar = $order->latestPayment;
        $handover = $order->cashHandovers->first(fn (CashHandover $h): bool => in_array($h->status->value, ['menunggu', 'diterima'], true));

        $sudahAdaTitikPertama = \App\Models\Attendance::query()
            ->where('user_id', $teknisi->id)
            ->where('tanggal', now()->toDateString())
            ->exists();

        return self::orderRingkas($order) + self::kunciTitik($order, $teknisi) + [
            'catatan_admin' => $order->catatan_admin,
            'alasan_kendala' => $order->alasan_kendala,
            'kendala_bukti' => ($kb = $order->kendalaBuktis->first()) === null ? null : [
                'jenis' => $kb->jenis,
                'pihak' => $kb->pihak,
                'alasan' => $kb->alasan,
                'foto_url' => self::url($kb->foto_path),
                'dicatat_pada' => $kb->created_at?->toIso8601String(),
            ],
            'video' => $order->videos->map(fn ($v): array => [
                'id' => $v->id,
                'url' => $v->url(),
                'nama' => $v->nama_asli,
                'ukuran' => (int) $v->ukuran,
                'keterangan' => $v->keterangan,
                'dicatat_pada' => $v->created_at?->toIso8601String(),
                'bisa_hapus' => (int) $v->user_id === (int) $teknisi->id && ! $order->sudahDitutup(),
            ])->values()->all(),
            'perbaikan' => [
                'menunggu_konfirmasi' => (bool) $order->perbaikan_menunggu_konfirmasi,
                'catatan' => $order->perbaikan_catatan,
                'estimasi_harga' => $order->perbaikan_estimasi_harga === null ? null : self::rupiah($order->perbaikan_estimasi_harga),
            ],
            'tim' => $order->orderTechnicians->map(fn ($t): array => [
                'id' => $t->teknisi_id,
                'name' => $t->teknisi?->name,
                'pic' => (int) $t->teknisi_id === (int) $order->teknisi_id,
            ])->values()->all(),
            'saya_pic' => (int) $order->teknisi_id === (int) $teknisi->id,
            'butuh_foto_titik_pertama' => ! $sudahAdaTitikPertama,
            'items' => $items,
            'units' => $unitJson,
            'laporan' => $laporan ? [
                'ada' => true,
                'catatan' => $laporan->catatan_pengerjaan,
                'dikirim_pada' => $laporan->created_at?->toIso8601String(),
            ] : ['ada' => false],
            'foto_wajib_kurang' => $kurang,
            'pembayaran' => [
                'metode_dipilih' => $order->metode_dipilih?->value,
                'metode_tunai' => $order->metodeTunai(),
                'bayar_dilaporkan' => (bool) $order->bayar_dilaporkan_teknisi,
                'bukti_url' => self::url($order->bukti_pembayaran),
                'status_bayar' => $bayar?->status?->value,
                'setoran_aktif' => $handover ? ['id' => $handover->id, 'status' => $handover->status->value] : null,
                'saluran' => app(PaymentChannelService::class)->daftarAktif()->map(fn ($c): array => [
                    'id' => $c->id,
                    'nama' => $c->nama,
                    'jenis' => $c->jenis->value,
                    'atas_nama' => $c->atas_nama,
                    'nomor_rekening' => $c->nomor_rekening,
                    'nama_bank' => $c->nama_bank,
                    'gambar_url' => self::url($c->gambar),
                ])->values()->all(),
                'metode_opsi' => array_map(fn (PaymentMethod $m): string => $m->value, PaymentMethod::cases()),
            ],
        ];
    }

    public static function pengeluaran(TeknisiExpense $e, int $userId): array
    {
        return [
            'id' => $e->id,
            'tanggal_input' => $e->tanggal_input?->toDateString(),
            'kategori' => $e->kategori,
            'kategori_label' => TeknisiExpense::kategoriLabel((string) $e->kategori),
            'qty' => (int) $e->qty,
            'harga' => (int) $e->harga,
            'nominal' => (int) $e->nominal,
            'keterangan' => $e->keterangan,
            'status' => $e->status,
            'catatan_approval' => $e->catatan_approval,
            'foto_url' => self::url($e->foto_path),
            'foto_diunggah_pada' => $e->foto_diunggah_pada?->toIso8601String(),
            'dicatat_pada' => $e->created_at?->toIso8601String(),
            'order_id' => $e->order_id,
            'order_label' => $e->order ? "#{$e->order->id} — ".($e->order->customer?->nama ?? 'Tanpa nama') : null,
            'partner' => $e->partner ? ['id' => $e->partner->id, 'name' => $e->partner->name] : null,
            'dicatat_oleh' => $e->teknisi ? ['id' => $e->teknisi->id, 'name' => $e->teknisi->name] : null,
            'sebagai_partner' => (int) $e->partner_id === $userId && (int) $e->teknisi_id !== $userId,
            'bisa_ubah' => (int) $e->teknisi_id === $userId,
            'bisa_hapus' => (int) $e->teknisi_id === $userId && $e->status === 'pending',
        ];
    }

    public static function absensi(?DailyAttendance $a): ?array
    {
        if ($a === null) {
            return null;
        }

        return [
            'id' => $a->id,
            'tanggal' => $a->tanggal?->toDateString(),
            'jam_datang' => $a->jam_datang?->toIso8601String(),
            'jam_pulang' => $a->jam_pulang?->toIso8601String(),
            'status_datang' => $a->status_datang?->value,
            'dikecualikan_denda' => (bool) $a->dikecualikan_denda,
            'foto_datang_url' => self::url($a->foto_datang),
            'foto_pulang_url' => self::url($a->foto_pulang),
        ];
    }

    public static function pulangCepat(TechnicianEarlyLeave $p): array
    {
        return [
            'id' => $p->id,
            'tanggal' => $p->tanggal?->toDateString(),
            'jam_pulang' => $p->jam_pulang ? substr((string) $p->jam_pulang, 0, 5) : null,
            'alasan' => $p->alasan,
            'status' => $p->status?->value ?? (string) $p->status,
            'nominal_potongan' => $p->nominal_potongan === null ? null : self::rupiah($p->nominal_potongan),
            'catatan_hrd' => $p->catatan_hrd,
        ];
    }

    public static function setoran(CashHandover $h): array
    {
        return [
            'id' => $h->id,
            'order_id' => $h->order_id,
            'customer' => $h->order?->customer?->nama,
            'tanggal_setor' => $h->tanggal_setor instanceof \DateTimeInterface ? $h->tanggal_setor->format('Y-m-d') : (string) $h->tanggal_setor,
            'nominal' => self::rupiah($h->nominal),
            'diserahkan_ke' => $h->diserahkan_ke,
            'status' => $h->status->value,
            'catatan_verifikasi' => $h->catatan_verifikasi,
            'foto_url' => self::url($h->foto_path),
        ];
    }

    public static function insentif(TechnicianIncentive $i): array
    {
        return [
            'id' => $i->id,
            'tanggal' => $i->tanggal instanceof \DateTimeInterface ? $i->tanggal->format('Y-m-d') : (string) $i->tanggal,
            'kategori' => $i->kategori instanceof \BackedEnum ? $i->kategori->value : (string) $i->kategori,
            'kategori_label' => $i->kategori instanceof \App\Enums\IncentiveKategori ? $i->kategori->label() : (string) $i->kategori,
            'tipe' => $i->tipe instanceof \BackedEnum ? $i->tipe->value : (string) $i->tipe,
            'nominal' => self::rupiah($i->nominal),
            'status_verifikasi' => $i->status_verifikasi instanceof \BackedEnum ? $i->status_verifikasi->value : (string) $i->status_verifikasi,
            'catatan' => $i->catatan,
            'foto_url' => self::url($i->foto_bukti),
        ];
    }
}
