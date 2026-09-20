<div class="space-y-4 px-4 pb-2 pt-3">

    {{-- Kembali --}}
    <a href="{{ url('/teknisi') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold text-gray-500">
        <x-heroicon-o-chevron-left class="h-4 w-4" /> Jadwal Saya
    </a>

    @if (session('status'))
        <div class="rounded-2xl bg-emerald-100 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-2xl bg-red-100 px-4 py-3 text-sm font-medium text-red-800">{{ session('error') }}</div>
    @endif

    @php
        $phoneDigits = preg_replace('/\D/', '', $order->customer->no_hp ?? '');
        $waNumber = str_starts_with($phoneDigits, '0') ? '62'.substr($phoneDigits, 1) : $phoneDigits;

        $fmtRp = fn (float $n): string => 'Rp '.number_format($n, 0, ',', '.');

        $bayar = $latestPayment;
        $isLunas = $bayar?->status === $paymentStatus::Lunas;
        $isDp = $bayar?->status === $paymentStatus::Dp;
        $badgeBayar = $isLunas
            ? ['Lunas', 'bg-emerald-100 text-emerald-700']
            : ($isDp ? ['DP', 'bg-amber-100 text-amber-700'] : ['Belum bayar', 'bg-gray-100 text-gray-500']);
        $jumlahDibayar = $bayar ? (float) $bayar->jumlah_dibayar : 0.0;
        $totalTagihan = (float) $order->total();
        $sisaTagihan = max(0.0, $totalTagihan - $jumlahDibayar);

        $laporanFoto = $order->workReports
            ->sortByDesc('id')
            ->filter(fn ($r) => filled($r->foto_sebelum) || filled($r->foto_sesudah))
            ->map(fn ($r) => [
                'id' => $r->id,
                'waktu' => $r->waktu_selesai
                    ? \Illuminate\Support\Carbon::parse($r->waktu_selesai)->format('d M Y H:i')
                    : '-',
                'catatan' => $r->catatan_pengerjaan,
                'sebelum' => $r->foto_sebelum ? asset('storage/'.ltrim($r->foto_sebelum, '/')) : null,
                'sesudah' => $r->foto_sesudah ? asset('storage/'.ltrim($r->foto_sesudah, '/')) : null,
            ])
            ->values();

        $metodeDipilih = $order->metode_dipilih?->value;
        $resiUrl = $order->resi_token ? route('resi.show', [$order->id, $order->resi_token]) : null;
        $opsiMetode = [
            ['value' => 'cash', 'label' => 'Tunai'],
            ['value' => 'qris', 'label' => 'QRIS'],
            ['value' => 'transfer', 'label' => 'Transfer'],
            ['value' => 'ewallet', 'label' => 'E-Wallet'],
        ];

        $metodeLabel = $metodeDipilih
            ? (collect($opsiMetode)->firstWhere('value', $metodeDipilih)['label'] ?? ucfirst($metodeDipilih))
            : null;
    @endphp

    {{-- Kartu customer --}}
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center gap-3">
            <x-initials-avatar :name="$order->customer->nama" />
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <p class="truncate font-bold text-gray-900">{{ $order->customer->nama }}</p>
                    <x-teknisi-status-badge :status="$order->status" />
                </div>
                <p class="mt-0.5 truncate text-sm text-gray-500">{{ $order->alamat_pengerjaan }}</p>
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            @if ($order->customer->no_hp)
                <a href="tel:{{ $phoneDigits }}" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-blue-50 py-2.5 text-sm font-semibold text-blue-700 active:bg-blue-100">
                    <x-heroicon-o-phone class="h-4 w-4" /> Telepon
                </a>
                <a href="https://wa.me/{{ $waNumber }}" target="_blank" class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-green-50 py-2.5 text-sm font-semibold text-green-700 active:bg-green-100">
                    <x-heroicon-o-chat-bubble-left-right class="h-4 w-4" /> WhatsApp
                </a>
            @endif
        </div>
        <div class="mt-3 flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
            <span class="flex min-w-0 items-center gap-2 text-sm text-gray-500">
                <x-heroicon-o-wrench-screwdriver class="h-4 w-4 shrink-0" />
                <span class="truncate">{{ $order->serviceCatalog->jenis_layanan->value }}</span>
                <span class="text-gray-300">&middot;</span>
                <span class="shrink-0">{{ $order->jumlah_unit }} unit</span>
            </span>
            <span class="shrink-0 text-sm font-extrabold text-gray-900">{{ $fmtRp($totalTagihan) }}</span>
        </div>
    </div>

    {{-- Peta lokasi customer (dari koordinat yg diisi Admin di Data Customer) --}}
    @if ($order->customer->latitude !== null && $order->customer->longitude !== null)
        @php
            $custLat = (float) $order->customer->latitude;
            $custLng = (float) $order->customer->longitude;
            $rute = 'https://www.google.com/maps/dir/?api=1&destination='.$custLat.','.$custLng;
        @endphp
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <div
                wire:ignore
                x-data="{
                    map: null,
                    init() {
                        this.map = L.map(this.$el, { zoomControl: false }).setView([{{ $custLat }}, {{ $custLng }}], 16);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap contributors',
                            maxZoom: 19,
                        }).addTo(this.map);
                        L.marker([{{ $custLat }}, {{ $custLng }}]).addTo(this.map);
                    },
                    destroy() {
                        if (this.map) this.map.remove();
                    },
                }"
                x-init="init()"
                class="h-40 w-full"
            ></div>
            <a href="{{ $rute }}" target="_blank" rel="noopener"
                class="flex items-center justify-center gap-2 border-t border-gray-100 py-3 text-sm font-bold text-blue-700 active:bg-blue-50">
                <x-heroicon-o-map class="h-4 w-4" /> Buka Rute ke Lokasi Customer
            </a>
        </div>
    @endif

    {{-- Ping GPS latar belakang (B-lacak-lokasi): aktif hanya selagi menuju
         lokasi/mengerjakan order ini. wire:key berganti tiap status supaya
         Alpine bikin ulang timer & bersih-bersih interval lama lewat destroy(). --}}
    @if (in_array($order->status, [$orderStatus::MenujuLokasi, $orderStatus::Dikerjakan]))
        <div
            wire:key="lokasi-tracker-{{ $order->status->value }}"
            x-data="{
                timer: null,
                init() {
                    this.kirim();
                    this.timer = setInterval(() => this.kirim(), 30000);
                },
                destroy() {
                    if (this.timer) clearInterval(this.timer);
                },
                kirim() {
                    if (! navigator.geolocation) return;
                    navigator.geolocation.getCurrentPosition(
                        (pos) => $wire.updateLokasi(pos.coords.latitude, pos.coords.longitude),
                        () => {},
                        { enableHighAccuracy: true, maximumAge: 15000, timeout: 10000 }
                    );
                },
            }"
        ></div>
    @endif

    {{-- Order terkendala (B-kendala-lapangan): menunggu admin menjadwalkan
         ulang, sembunyikan aksi lanjutan sampai itu terjadi. --}}
    @if ($order->status === $orderStatus::Terkendala)
        <div class="rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-100">
            <p class="flex items-center gap-2 font-bold text-rose-700">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" /> Order Terkendala
            </p>
            <p class="mt-1 text-sm text-rose-900/80">{{ $order->alasan_kendala }}</p>
            <p class="mt-2 text-xs text-rose-600">Menunggu admin menjadwalkan ulang order ini.</p>
        </div>
    @endif

    @if ($order->status === $orderStatus::Terjadwal)
        <x-teknisi-slider
            hint="Geser untuk mulai berangkat ke lokasi customer."
            label="Mulai Berangkat"
            action="berangkat"
        />
    @endif

    @if ($order->status === $orderStatus::MenujuLokasi)
        @if ($this->butuhFotoTitikPertama)
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-amber-100">
                <p class="mb-1 flex items-center gap-2 text-sm font-semibold text-amber-700">
                    <x-heroicon-o-camera class="h-5 w-5" /> Titik Pertama Hari Ini (Games 2)
                </p>
                <p class="mb-3 text-xs text-gray-500">Ini check-in pertama Anda hari ini — upload foto bukti (mis. buka cover AC indoor) sebelum geser check-in.</p>
                <div x-data="cameraUpload('fotoTitikPertama')" class="relative h-32 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
                    <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                        <template x-if="!preview">
                            <div class="flex flex-col items-center">
                                <x-heroicon-o-camera class="h-6 w-6" />
                                <span class="mt-1 text-xs">Ketuk untuk ambil/pilih foto</span>
                            </div>
                        </template>
                        <img x-show="preview" :src="preview" alt="Pratinjau foto titik pertama" class="absolute inset-0 h-full w-full object-cover">
                        <input type="file" accept="image/*" capture="environment"
                            x-on:change="onFile($event)"
                            class="absolute inset-0 cursor-pointer opacity-0">
                    </label>
                    <div x-show="uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-white/70">
                        <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                        <span x-show="progress > 0" x-text="progress + '%'" class="text-[10px] font-bold text-blue-600"></span>
                    </div>
                    <p x-show="error" x-text="error" class="absolute inset-x-2 bottom-1 text-center text-[10px] font-medium text-rose-600"></p>
                </div>
                @error('fotoTitikPertama') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <x-teknisi-slider
            hint="Geser jika sudah tiba di lokasi customer."
            label="Check-in Sekarang"
            action="checkIn"
        />
    @endif

    {{-- Tombol Terkendala/Gagal: tersedia selama order masih berjalan aktif
         (belum submit laporan), utk lapor kendala lapangan mis. customer
         tidak jadi / tidak ada di lokasi. --}}
    @if (in_array($order->status, [$orderStatus::Terjadwal, $orderStatus::MenujuLokasi, $orderStatus::Dikerjakan]))
        <div x-data="{ buka: false }" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <button type="button" x-show="!buka" @click="buka = true"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-rose-50 py-2.5 text-sm font-bold text-rose-700 active:bg-rose-100">
                <x-heroicon-o-exclamation-triangle class="h-4 w-4" /> Terkendala / Gagal
            </button>
            <div x-show="buka" x-cloak class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">Alasan kendala</label>
                <textarea wire:model="alasanKendala" rows="3" placeholder="mis. customer tidak jadi / tidak ada di lokasi"
                    class="w-full rounded-xl border border-gray-300 text-sm"></textarea>
                @error('alasanKendala') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <div class="flex gap-2">
                    <button type="button" @click="buka = false"
                        class="flex-1 rounded-xl bg-gray-100 py-2.5 text-sm font-semibold text-gray-600 active:bg-gray-200">
                        Batal
                    </button>
                    <button type="button" wire:click="tandaiKendala" wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-rose-600 py-2.5 text-sm font-bold text-white active:bg-rose-700">
                        Kirim
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Notice "menunggu konfirmasi perbaikan" (dev-plan/13 §2): sudah
         dilaporkan, tampilkan status menunggu, jangan tampilkan tombolnya lagi. --}}
    @if ($order->perbaikan_menunggu_konfirmasi)
        <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-100">
            <p class="flex items-center gap-2 font-bold text-amber-700">
                <x-heroicon-o-wrench class="h-5 w-5" /> Menunggu Konfirmasi Perbaikan
            </p>
            <p class="mt-1 text-sm text-amber-900/80">{{ $order->perbaikan_catatan }}</p>
            @if ($order->perbaikan_estimasi_harga !== null)
                <p class="mt-1 text-sm text-amber-900/80">Estimasi: Rp{{ number_format($order->perbaikan_estimasi_harga, 0, ',', '.') }}</p>
            @endif
            <p class="mt-2 text-xs text-amber-600">Admin sedang konfirmasi ke customer. Lanjutkan pekerjaan seperti biasa.</p>
        </div>
    @elseif ($order->status === $orderStatus::Dikerjakan)
        {{-- Tombol "Ada Perbaikan": lapor kebutuhan sparepart/perbaikan
             tambahan yg sudah dibicarakan dgn customer, tanpa menghentikan
             progres order (beda dari Terkendala/Gagal di atas). --}}
        <div x-data="{ buka: false }" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <button type="button" x-show="!buka" @click="buka = true"
                class="flex w-full items-center justify-center gap-2 rounded-xl bg-amber-50 py-2.5 text-sm font-bold text-amber-700 active:bg-amber-100">
                <x-heroicon-o-wrench class="h-4 w-4" /> Ada Perbaikan
            </button>
            <div x-show="buka" x-cloak class="space-y-2">
                <label class="block text-sm font-semibold text-gray-700">Apa yang perlu diganti/diperbaiki?</label>
                <textarea wire:model="catatanPerbaikan" rows="3" placeholder="mis. kapasitor lemah, perlu diganti"
                    class="w-full rounded-xl border border-gray-300 text-sm"></textarea>
                @error('catatanPerbaikan') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                <label class="block text-sm font-semibold text-gray-700">Estimasi harga (opsional)</label>
                <input type="number" wire:model="estimasiHargaPerbaikan" min="0" placeholder="mis. 75000"
                    class="w-full rounded-xl border border-gray-300 text-sm">
                <div class="flex gap-2">
                    <button type="button" @click="buka = false"
                        class="flex-1 rounded-xl bg-gray-100 py-2.5 text-sm font-semibold text-gray-600 active:bg-gray-200">
                        Batal
                    </button>
                    <button type="button" wire:click="laporPerbaikan" wire:loading.attr="disabled"
                        class="flex-1 rounded-xl bg-amber-600 py-2.5 text-sm font-bold text-white active:bg-amber-700">
                        Kirim
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if (in_array($order->status, [$orderStatus::Dikerjakan, $orderStatus::ButuhFollowup]))
        <form wire:submit="submitLaporan" class="space-y-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h2 class="flex items-center gap-2 font-bold text-gray-900">
                <x-heroicon-o-clipboard-document-check class="h-5 w-5 text-blue-600" />
                Detail Pelaksanaan Order
            </h2>

            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Catatan Pengerjaan</label>
                <textarea wire:model="catatan" rows="3" class="w-full rounded-xl border border-gray-300 text-sm"></textarea>
                @error('catatan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-700">Bahan &amp; Alat Digunakan</label>
                <div class="space-y-2">
                    @foreach ($materials as $i => $material)
                        <div class="flex items-center gap-2 rounded-xl bg-gray-50 p-2">
                            <select wire:model="materials.{{ $i }}.stock_item_id" class="flex-1 rounded-lg border border-gray-300 bg-white text-sm">
                                <option value="">— pilih barang —</option>
                                @foreach ($stockItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->nama_barang }} ({{ $item->stok_saat_ini }} {{ $item->satuan }})</option>
                                @endforeach
                            </select>
                            <div class="flex items-center gap-1 rounded-full bg-white px-1 shadow-sm">
                                <button type="button" wire:click="decMaterial({{ $i }})" class="flex h-7 w-7 items-center justify-center rounded-full text-blue-600">
                                    <x-heroicon-o-minus class="h-4 w-4" />
                                </button>
                                <span class="w-5 text-center text-sm font-semibold">{{ $material['jumlah'] }}</span>
                                <button type="button" wire:click="incMaterial({{ $i }})" class="flex h-7 w-7 items-center justify-center rounded-full text-blue-600">
                                    <x-heroicon-o-plus class="h-4 w-4" />
                                </button>
                            </div>
                            <button type="button" wire:click="hapusMaterial({{ $i }})" class="text-red-400">
                                <x-heroicon-o-x-mark class="h-5 w-5" />
                            </button>
                        </div>
                    @endforeach
                </div>
                <button type="button" wire:click="tambahMaterial" class="mt-2 flex items-center gap-1 text-sm font-semibold text-blue-600">
                    <x-heroicon-o-plus-circle class="h-4 w-4" /> Tambah material
                </button>
            </div>

            {{-- Foto per kategori order_item (dev-plan/13 §3): tiap baris
                 layanan punya slot fotonya sendiri sesuai kategori. --}}
            @if ($order->orderItems->isNotEmpty())
                <div>
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Foto per Layanan <span class="font-normal text-gray-400">(yang ditandai "Wajib" harus dilengkapi sebelum order berikutnya, JPG/PNG maks 5 MB)</span></label>
                    <div class="space-y-3">
                        @foreach ($order->orderItems as $item)
                            <div class="rounded-xl bg-gray-50 p-3">
                                <p class="mb-2 text-sm font-semibold text-gray-700">
                                    {{ $item->nama_layanan }}
                                    @if ($item->acUnit)
                                        <span class="font-normal text-gray-400">— {{ $item->acUnit->labelTampil() }}</span>
                                    @endif
                                </p>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($fotoSlots[$item->id] ?? [] as $slotKey => $slotLabel)
                                        <div x-data="cameraUpload('fotoKategori.{{ $item->id }}.{{ $slotKey }}')">
                                            <label class="relative flex h-20 flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-white text-center text-gray-400">
                                                <template x-if="!uploading">
                                                    <div class="flex flex-col items-center">
                                                        <x-heroicon-o-camera class="h-5 w-5" />
                                                        <span class="mt-0.5 px-1 text-[11px]">
                                                            {{ $slotLabel }}
                                                            @if (in_array($slotKey, $fotoSlotsWajib[$item->id] ?? [], true))
                                                                <span class="text-rose-500">*Wajib</span>
                                                            @endif
                                                        </span>
                                                        @if (! empty($fotoKategori[$item->id][$slotKey] ?? null))
                                                            <span class="mt-0.5 text-[10px] font-bold text-emerald-600">Terpilih</span>
                                                        @endif
                                                    </div>
                                                </template>
                                                <div x-show="uploading" class="flex flex-col items-center">
                                                    <x-heroicon-o-arrow-path class="h-5 w-5 animate-spin text-blue-600" />
                                                    <span x-show="progress > 0" x-text="progress + '%'" class="text-[9px] font-bold text-blue-600"></span>
                                                </div>
                                                <input type="file" accept="image/*" capture="environment"
                                                    x-on:change="onFile($event)"
                                                    class="absolute inset-0 cursor-pointer opacity-0">
                                            </label>
                                            <p x-show="error" x-text="error" class="text-[10px] font-medium text-rose-600"></p>
                                        </div>
                                        @error('fotoKategori.'.$item->id.'.'.$slotKey)
                                            <p class="col-span-2 text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-700">Sertakan Foto <span class="font-normal text-gray-400">(opsional, JPG/PNG maks 5 MB)</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <div x-data="cameraUpload('fotoSebelum')" class="relative h-28 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 transition focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100">
                        <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                            <template x-if="!preview">
                                <div class="flex flex-col items-center">
                                    <x-heroicon-o-camera class="h-6 w-6" />
                                    <span class="mt-1 text-xs">Foto Sebelum</span>
                                    <span class="mt-0.5 px-2 text-center text-[10px] text-gray-300" x-text="nama || 'Ketuk untuk pilih'"></span>
                                </div>
                            </template>
                            <img x-show="preview" :src="preview" alt="Pratinjau foto sebelum"
                                class="absolute inset-0 h-full w-full object-cover">
                            <span x-show="preview" x-cloak
                                class="absolute bottom-1 right-1 rounded-full bg-black/60 px-2 py-0.5 text-[10px] text-white" x-text="nama"></span>
                            <input type="file" accept="image/*" capture="environment"
                                x-on:change="onFile($event)"
                                class="absolute inset-0 cursor-pointer opacity-0">
                        </label>
                        <div x-show="uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-white/70">
                            <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                            <span x-show="progress > 0" x-text="progress + '%'" class="text-[10px] font-bold text-blue-600"></span>
                        </div>
                        <p x-show="error" x-text="error" class="absolute inset-x-2 bottom-1 text-center text-[10px] font-medium text-rose-600"></p>
                    </div>
                    <div x-data="cameraUpload('fotoSesudah')" class="relative h-28 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 transition focus-within:border-blue-400 focus-within:ring-2 focus-within:ring-blue-100">
                        <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                            <template x-if="!preview">
                                <div class="flex flex-col items-center">
                                    <x-heroicon-o-camera class="h-6 w-6" />
                                    <span class="mt-1 text-xs">Foto Sesudah</span>
                                    <span class="mt-0.5 px-2 text-center text-[10px] text-gray-300" x-text="nama || 'Ketuk untuk pilih'"></span>
                                </div>
                            </template>
                            <img x-show="preview" :src="preview" alt="Pratinjau foto sesudah"
                                class="absolute inset-0 h-full w-full object-cover">
                            <span x-show="preview" x-cloak
                                class="absolute bottom-1 right-1 rounded-full bg-black/60 px-2 py-0.5 text-[10px] text-white" x-text="nama"></span>
                            <input type="file" accept="image/*" capture="environment"
                                x-on:change="onFile($event)"
                                class="absolute inset-0 cursor-pointer opacity-0">
                        </label>
                        <div x-show="uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-white/70">
                            <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                            <span x-show="progress > 0" x-text="progress + '%'" class="text-[10px] font-bold text-blue-600"></span>
                        </div>
                        <p x-show="error" x-text="error" class="absolute inset-x-2 bottom-1 text-center text-[10px] font-medium text-rose-600"></p>
                    </div>
                </div>
                @error('fotoSebelum') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('fotoSesudah') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-gray-400" wire:loading.remove wire:target="fotoSebelum,fotoSesudah">
                    Foto tersimpan otomatis saat laporan dikirim.
                </p>
            </div>

            <label class="flex items-center gap-2 rounded-xl bg-gray-50 p-3 text-sm font-medium text-gray-700">
                <input type="checkbox" wire:model="butuhFollowup" class="rounded border-gray-300 text-blue-600">
                Perlu follow-up (mis. sparepart kurang)
            </label>

            <label class="flex items-center gap-2 rounded-xl bg-gray-50 p-3 text-sm font-medium text-gray-700">
                <input type="checkbox" wire:model="isKlaim" class="rounded border-gray-300 text-blue-600">
                Ini pekerjaan klaim/garansi (tidak ditagih)
            </label>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-full bg-blue-600 py-3 font-bold text-white shadow-md shadow-blue-200 active:bg-blue-700">
                Konfirmasi Selesai
            </button>
        </form>
    @endif

    @if ($order->status === $orderStatus::Selesai)
        <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
            <p class="flex items-center gap-2 font-bold text-emerald-700">
                <x-heroicon-o-check-circle class="h-5 w-5" /> Order ini sudah selesai.
            </p>
            @foreach ($order->workReports->sortByDesc('id') as $report)
                <div class="mt-2 border-t border-emerald-100 pt-2 text-sm text-emerald-900/70">
                    <p>{{ $report->catatan_pengerjaan }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- dev-plan/17, B63 (revisi): laporan bisa disubmit walau foto wajib
         belum lengkap (supaya pembayaran tidak tertahan) — tapi teknisi
         tidak bisa berangkat ke order berikutnya sebelum ini dilengkapi. --}}
    @if (! empty($this->fotoWajibKurang))
        <form wire:submit="lengkapiFotoWajib" class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
            <p class="flex items-center gap-2 text-sm font-bold text-amber-800">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5" /> Lengkapi Foto Wajib
            </p>
            <p class="mt-1 text-xs text-amber-700">
                Laporan sudah tersimpan, tapi masih ada foto wajib yang belum diisi. Anda tidak bisa berangkat ke
                order berikutnya sampai ini dilengkapi.
            </p>
            <div class="mt-3 grid grid-cols-2 gap-2">
                @foreach ($this->fotoWajibKurang as $kurang)
                    <div x-data="cameraUpload('fotoLengkapi.{{ $kurang['order_item']->id }}.{{ $kurang['kode_slot'] }}')">
                        <label class="relative flex h-20 flex-col items-center justify-center rounded-lg border-2 border-dashed border-amber-300 bg-white text-center text-amber-500">
                            <template x-if="!uploading">
                                <div class="flex flex-col items-center">
                                    <x-heroicon-o-camera class="h-5 w-5" />
                                    <span class="mt-0.5 px-1 text-[11px]">{{ $kurang['label'] }}</span>
                                </div>
                            </template>
                            <div x-show="uploading" class="flex flex-col items-center">
                                <x-heroicon-o-arrow-path class="h-5 w-5 animate-spin text-amber-600" />
                                <span x-show="progress > 0" x-text="progress + '%'" class="text-[9px] font-bold text-amber-600"></span>
                            </div>
                            <input type="file" accept="image/*" capture="environment"
                                x-on:change="onFile($event)"
                                class="absolute inset-0 cursor-pointer opacity-0">
                        </label>
                        <p x-show="error" x-text="error" class="text-[10px] font-medium text-rose-600"></p>
                    </div>
                    @error('fotoLengkapi.'.$kurang['order_item']->id.'.'.$kurang['kode_slot'])
                        <p class="col-span-2 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                @endforeach
            </div>
            <button type="submit" wire:loading.attr="disabled"
                class="mt-3 w-full rounded-full bg-amber-600 py-2.5 text-sm font-bold text-white active:bg-amber-700">
                Simpan Foto
            </button>
        </form>
    @endif

    {{-- Layar sukses setelah slider "Selesaikan Order" (B32) --}}
    @if (session('order_ditutup'))
        <div class="rounded-2xl bg-emerald-600 p-5 text-center text-white shadow-lg shadow-emerald-200">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-white/20">
                <x-heroicon-o-check-circle class="h-7 w-7" />
            </span>
            <p class="mt-2 text-lg font-extrabold">Order selesai!</p>
            <p class="mt-1 text-sm text-emerald-100">
                Metode pembayaran terkunci{{ $metodeLabel ? ' ('.$metodeLabel.')' : '' }} — order tercatat ditutup.
            </p>
            <a href="{{ url('/teknisi') }}" wire:navigate
                class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-white px-5 py-2.5 text-sm font-bold text-emerald-700 active:bg-emerald-50">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> Kembali ke Jadwal Saya
            </a>
        </div>
    @endif

    {{-- ===== Info Pembayaran + Galeri (B13b/B14a/B18a) ===== --}}
    @if (in_array($order->status, [$orderStatus::Selesai, $orderStatus::ButuhFollowup]))
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
            <div class="flex items-center gap-2 border-b border-gray-100 bg-gradient-to-r from-sky-50 to-blue-50/40 px-4 py-3">
                <x-heroicon-o-credit-card class="h-5 w-5 text-blue-600" />
                <h2 class="text-sm font-extrabold text-gray-900">Info Pembayaran</h2>
                <span class="ml-auto inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold leading-none {{ $badgeBayar[1] }}">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    {{ $badgeBayar[0] }}
                </span>
            </div>

            <div class="space-y-4 p-4">
                @if ($isLunas)
                    <div class="flex items-center gap-3 rounded-xl bg-emerald-50 p-3 ring-1 ring-emerald-100">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                            <x-heroicon-o-check-circle class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-emerald-800">Pembayaran lunas</p>
                            <p class="text-xs text-emerald-700">
                                Total dibayar <span class="font-extrabold">{{ $fmtRp($jumlahDibayar) }}</span>
                            </p>
                        </div>
                    </div>
                @else
                    {{-- Ringkasan tagihan --}}
                    <div class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2.5 text-sm ring-1 ring-gray-100">
                        <span class="font-medium text-gray-500">Total Tagihan</span>
                        <span class="font-extrabold text-gray-900">{{ $fmtRp($totalTagihan) }}</span>
                    </div>
                    @if ($jumlahDibayar > 0)
                        <div class="-mt-1 space-y-0.5 px-1 text-xs text-gray-500">
                            <p>Sudah dibayar: <b class="text-gray-700">{{ $fmtRp($jumlahDibayar) }}</b></p>
                            <p>Sisa tagihan: <b class="text-amber-600">{{ $fmtRp($sisaTagihan) }}</b></p>
                        </div>
                    @endif

                    {{-- (1) Metode yang dipilih customer --}}
                    <div>
                        <p class="text-sm font-bold text-gray-800">Metode yang dipilih customer</p>

                        @if ($order->sudahDitutup())
                            {{-- B32: terkunci setelah slider "Selesaikan Order". --}}
                            <div class="mt-2 flex items-center gap-3 rounded-xl bg-emerald-50 px-3 py-2.5 ring-1 ring-emerald-100">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                    <x-heroicon-o-lock-closed class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="text-sm font-extrabold text-emerald-800">{{ $metodeLabel ?? '—' }}</p>
                                    <p class="text-[11px] font-medium text-emerald-600">Terkunci — order sudah ditutup teknisi.</p>
                                </div>
                            </div>
                        @else
                            <p class="mt-0.5 text-xs text-gray-400">Tanda bantu utk Admin — pencatatan resminya tetap oleh Admin/Finance.</p>
                            <div class="mt-2 grid grid-cols-4 gap-2">
                                @foreach ($opsiMetode as $opsi)
                                    @php $aktif = $metodeDipilih === $opsi['value']; @endphp
                                    <button type="button" wire:click="pilihMetode('{{ $opsi['value'] }}')"
                                        class="rounded-xl border px-1 py-2 text-xs font-bold transition {{ $aktif
                                            ? 'border-blue-600 bg-blue-600 text-white shadow-md shadow-blue-200'
                                            : 'border-gray-200 bg-white text-gray-500 active:bg-gray-50' }}">
                                        {{ $opsi['label'] }}
                                    </button>
                                @endforeach
                            </div>
                            @if ($metodeDipilih)
                                <button type="button" wire:click="pilihMetode()"
                                    class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-red-500">
                                    <x-heroicon-o-x-mark class="h-3.5 w-3.5" /> Hapus pilihan
                                </button>
                            @endif
                        @endif
                    </div>

                    {{-- (1b) Bukti Pembayaran (B-bukti-bayar): wajib utk rumahan,
                         opsional utk instansi — di-set admin di form Order. --}}
                    @php
                        $wajibBukti = $order->jenis_pelanggan?->value !== 'company';
                        $buktiUrl = $order->bukti_pembayaran ? asset('storage/'.ltrim($order->bukti_pembayaran, '/')) : null;
                    @endphp
                    <div class="border-t border-gray-100 pt-4">
                        <p class="text-sm font-bold text-gray-800">
                            Bukti Pembayaran
                            @if ($wajibBukti)
                                <span class="text-red-500">*wajib</span>
                            @else
                                <span class="text-xs font-normal text-gray-400">(opsional utk instansi)</span>
                            @endif
                        </p>

                        @if ($order->sudahDitutup())
                            @if ($buktiUrl)
                                <img src="{{ $buktiUrl }}" alt="Bukti pembayaran order {{ $order->id }}"
                                    class="mt-2 h-40 w-full rounded-xl object-cover ring-1 ring-gray-100">
                            @else
                                <p class="mt-1 text-xs text-gray-400">Tidak ada bukti pembayaran diunggah.</p>
                            @endif
                        @else
                            @if ($buktiUrl)
                                <img src="{{ $buktiUrl }}" alt="Bukti pembayaran order {{ $order->id }}"
                                    class="mt-2 h-40 w-full rounded-xl object-cover ring-1 ring-gray-100">
                                <p class="mt-1 text-xs text-gray-400">Pilih file baru di bawah untuk mengganti.</p>
                            @endif
                            <div x-data="cameraUpload('buktiPembayaran')">
                                <div class="mt-2 flex items-center gap-2">
                                    <input type="file" accept="image/*" capture="environment"
                                        x-on:change="onFile($event)"
                                        class="flex-1 text-xs text-gray-500">
                                    <button type="button" wire:click="uploadBuktiPembayaran" wire:loading.attr="disabled" wire:target="uploadBuktiPembayaran"
                                        x-bind:disabled="uploading"
                                        class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-xs font-bold text-white active:bg-blue-700 disabled:opacity-60">
                                        Simpan
                                    </button>
                                </div>
                                <div x-show="uploading" class="mt-1 text-xs text-gray-400">
                                    Mengunggah<span x-show="progress > 0" x-text="' ('+progress+'%)'"></span>...
                                </div>
                                <p x-show="error" x-text="error" class="mt-1 text-xs text-rose-600"></p>
                            </div>
                            @error('buktiPembayaran') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @endif
                    </div>

                    {{-- (2) Channel aktif QRIS/rekening --}}
                    @if ($paymentChannels->isNotEmpty())
                        <p class="text-sm font-bold text-gray-800">Bayar Pakai QRIS / Rekening</p>
                    @endif
                    <div class="space-y-3">
                        @forelse ($paymentChannels as $channel)
                            <div class="overflow-hidden rounded-xl ring-1 ring-gray-200">
                                @if ($channel->jenis->value === 'qris')
                                    @if ($channel->gambar)
                                        <div class="bg-white p-4 pb-0">
                                            <img src="{{ asset('storage/'.ltrim($channel->gambar, '/')) }}"
                                                alt="Kode QR {{ $channel->nama }} untuk pembayaran"
                                                class="mx-auto h-44 w-44 rounded-lg object-contain">
                                        </div>
                                    @endif
                                    <div class="flex items-center gap-2 p-3 {{ $channel->gambar ? 'border-t border-gray-100' : '' }}">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                            <x-heroicon-o-qr-code class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-bold text-gray-900">{{ $channel->nama }}</p>
                                            @if ($channel->atas_nama)
                                                <p class="text-xs text-gray-500">a.n. {{ $channel->atas_nama }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="flex items-center gap-2 p-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-green-50 text-green-600">
                                            <x-heroicon-o-banknotes class="h-5 w-5" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-bold text-gray-900">{{ $channel->nama_bank ?: $channel->nama }}</p>
                                            <p class="truncate text-xs text-gray-500">
                                                @if ($channel->atas_nama)
                                                    a.n. {{ $channel->atas_nama }}
                                                @else
                                                    {{ $channel->nama }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between gap-2 border-t border-gray-100 bg-gray-50/60 px-3 py-2.5">
                                        <p class="select-all font-mono text-sm font-extrabold tracking-wide text-gray-900">{{ $channel->nomor_rekening }}</p>
                                        <button type="button"
                                            x-data="{ done: false }"
                                            x-on:click="const teks = '{{ $channel->nomor_rekening }}'; const lama = () => { const el = document.createElement('textarea'); el.value = teks; el.style.position = 'fixed'; el.style.opacity = '0'; document.body.appendChild(el); el.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(el); }; if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(teks).then(() => { this.done = true; setTimeout(() => this.done = false, 1500); }).catch(lama); } else { lama(); } this.done = true; setTimeout(() => this.done = false, 1500);"
                                            class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-blue-600 px-3 text-xs font-bold text-white active:bg-blue-700">
                                            <x-heroicon-o-clipboard-document class="h-4 w-4" />
                                            <span x-text="done ? 'Tersalin' : 'Salin'"></span>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-xl bg-gray-50 px-3 py-3 text-center text-xs text-gray-400">
                                Belum ada channel pembayaran aktif yang disiapkan Admin.
                            </p>
                        @endforelse
                    </div>

                    {{-- (3) Tunai --}}
                    <div class="flex items-center gap-3 rounded-xl p-3 ring-1 ring-gray-200">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-50 text-green-600">
                            <x-heroicon-o-banknotes class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-bold text-gray-900">Tunai</p>
                            <p class="text-xs text-gray-500">Bayar langsung ke teknisi yang bertugas.</p>
                        </div>
                    </div>

                    {{-- (4) Bagikan Resi --}}
                    @if ($resiUrl)
                        <div class="rounded-xl bg-blue-50/70 p-3 ring-1 ring-blue-100">
                            <p class="flex items-center gap-1.5 text-sm font-bold text-gray-800">
                                <x-heroicon-o-link class="h-4 w-4 text-blue-600" /> Bagikan Resi
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500">Kirim link ini ke customer untuk rincian &amp; bukti pengerjaan.</p>
                            <div class="mt-2 flex items-center gap-2">
                                <code class="min-w-0 flex-1 truncate rounded-lg bg-white px-2 py-2 text-[11px] text-gray-600 ring-1 ring-gray-200">{{ $resiUrl }}</code>
                                <button type="button"
                                    x-data="{ done: false }"
                                    x-on:click="const teks = '{{ $resiUrl }}'; const lama = () => { const el = document.createElement('textarea'); el.value = teks; el.style.position = 'fixed'; el.style.opacity = '0'; document.body.appendChild(el); el.select(); try { document.execCommand('copy'); } catch (e) {} document.body.removeChild(el); }; if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(teks).then(() => { this.done = true; setTimeout(() => this.done = false, 1500); }).catch(lama); } else { lama(); } this.done = true; setTimeout(() => this.done = false, 1500);"
                                    class="flex h-9 shrink-0 items-center gap-1 rounded-full bg-blue-600 px-3 text-xs font-bold text-white active:bg-blue-700">
                                    <x-heroicon-o-clipboard-document class="h-4 w-4" />
                                    <span x-text="done ? 'Tersalin' : 'Salin'"></span>
                                </button>
                            </div>
                            <a href="{{ $resiUrl }}" target="_blank" rel="noopener"
                                class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-xl bg-white py-2.5 text-sm font-bold text-blue-700 ring-1 ring-blue-200 active:bg-blue-50">
                                <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /> Buka Resi
                            </a>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Slider penutup order (B32): muncul setelah metode dipilih, sebelum lunas. --}}
        @if ($order->status === $orderStatus::Selesai && ! $isLunas && $metodeDipilih && ! $order->sudahDitutup())
            <x-teknisi-slider
                hint="Geser untuk menutup order — metode {{ $metodeLabel }} akan terkunci & tidak bisa diubah lagi."
                label="Selesaikan Order"
                action="tutupOrder"
            />
        @endif

        {{-- (5) Galeri Foto Pengerjaan (semua laporan, bukan hanya terakhir) --}}
        @if ($laporanFoto->isNotEmpty())
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <h2 class="flex items-center gap-2 text-sm font-extrabold text-gray-900">
                    <x-heroicon-o-camera class="h-5 w-5 text-blue-600" /> Foto Pengerjaan
                </h2>
                @foreach ($laporanFoto as $lp)
                    <div class="mt-3 border-t border-gray-100 pt-3 first:border-t-0 first:pt-0">
                        @if ($laporanFoto->count() > 1)
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Laporan {{ \Illuminate\Support\Carbon::parse($lp['waktu'])->format('d M Y H:i') }}</p>
                        @endif
                        @if ($lp['catatan'])
                            <p class="mt-0.5 text-xs text-gray-500">{{ $lp['catatan'] }}</p>
                        @endif
                        <div class="mt-2 grid grid-cols-2 gap-3">
                            @if ($lp['sebelum'])
                                <figure>
                                    <img src="{{ $lp['sebelum'] }}" alt="Foto sebelum pengerjaan {{ $order->customer->nama }}"
                                        class="h-36 w-full rounded-xl object-cover ring-1 ring-gray-100" loading="lazy">
                                    <figcaption class="mt-1 text-center text-xs font-medium text-gray-400">Sebelum</figcaption>
                                </figure>
                            @endif
                            @if ($lp['sesudah'])
                                <figure>
                                    <img src="{{ $lp['sesudah'] }}" alt="Foto sesudah pengerjaan {{ $order->customer->nama }}"
                                        class="h-36 w-full rounded-xl object-cover ring-1 ring-gray-100" loading="lazy">
                                    <figcaption class="mt-1 text-center text-xs font-medium text-gray-400">Sesudah</figcaption>
                                </figure>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif

</div>
