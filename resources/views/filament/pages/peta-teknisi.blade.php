<x-filament-panels::page>
    <script>
        // Leaflet dimuat dinamis (sekali) sehingga peta tetap tampil walau skrip
        // tidak tereksekusi ulang saat halaman di-render ulang oleh Livewire.
        window.muatLeaflet = window.muatLeaflet || function () {
            if (window.L) return Promise.resolve();
            if (window._leafletPromise) return window._leafletPromise;

            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(css);

            window._leafletPromise = new Promise((ok, gagal) => {
                const js = document.createElement('script');
                js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                js.onload = ok;
                js.onerror = () => { window._leafletPromise = null; gagal(new Error('Leaflet gagal dimuat')); };
                document.head.appendChild(js);
            });

            return window._leafletPromise;
        };

        window.petaTeknisi = window.petaTeknisi || function (markers, center, zoom) {
            return {
                map: null,
                gagal: false,
                async init() {
                    try {
                        await window.muatLeaflet();
                    } catch (e) {
                        this.gagal = true;
                        return;
                    }

                    this.map = L.map(this.$refs.peta).setView(center, zoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    const layer = L.layerGroup().addTo(this.map);
                    markers.forEach((t) => {
                        // Warna = status APP (hijau aktif, abu-abu selain itu); pudar = GPS basi.
                        const warna = t.app_status === 'aktif' ? '#059669' : '#6b7280';
                        const titik = document.createElement('span');
                        titik.style.cssText = `display:block;width:22px;height:22px;border-radius:50%;background:${warna};border:3px solid #fff;box-shadow:0 0 0 2px ${warna},0 1px 4px rgba(0,0,0,.4);`;
                        const ikon = L.divIcon({
                            className: '',
                            html: titik,
                            iconSize: [22, 22],
                            iconAnchor: [11, 11],
                            popupAnchor: [0, -12],
                        });
                        const labelApp = { aktif: 'App aktif', keluar: 'Sudah logout', tidak_aktif: 'App tidak aktif', belum: 'App belum pernah dibuka' }[t.app_status] || '';
                        L.marker([t.lat, t.lng], { icon: ikon, opacity: t.stale ? 0.5 : 1 })
                            .addTo(layer)
                            .bindPopup(
                                `<b>${t.nama}</b><br>${t.customer}<br>${t.alamat}`
                                + `<br><small><b>${labelApp}</b> &middot; GPS ${t.stale ? 'basi: ' : 'update: '}${t.updated_label}</small>`
                            );
                    });
                    setTimeout(() => this.map && this.map.invalidateSize(), 200);
                },
                destroy() {
                    if (this.map) this.map.remove();
                },
            };
        };
    </script>

    <div class="space-y-5" wire:poll.20s>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-950">Posisi Teknisi Saat Ini</h2>
                <span class="text-xs text-gray-400">Auto-refresh tiap 20 detik</span>
            </div>

            @if ($markers->isEmpty())
                <p class="mt-3 text-sm text-gray-400">
                    Belum ada teknisi yang pernah mengirim GPS. Posisi muncul setelah teknisi membuka app saat jam kerja.
                </p>
            @else
                <div wire:key="peta-teknisi-map-{{ $markersKey }}" x-data="petaTeknisi(@js($markers), @js($center), {{ $zoom }})" x-init="init()">
                    <div x-ref="peta" wire:ignore style="height:420px;width:100%;margin-top:12px;border-radius:12px;overflow:hidden;position:relative;z-index:0;" class="ring-1 ring-gray-100"></div>
                    <p x-show="gagal" x-cloak class="mt-2 text-sm text-rose-600">
                        Peta gagal dimuat (koneksi ke unpkg.com / openstreetmap.org terblokir?). Daftar teknisi di bawah tetap akurat.
                    </p>
                </div>
            @endif
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="mb-3 text-sm font-semibold text-gray-950">Semua Teknisi ({{ $teknisi->count() }})</h2>

            @if ($teknisi->isEmpty())
                <p class="text-sm text-gray-400">Belum ada teknisi aktif.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($teknisi as $t)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ $t['nama'] }}</p>
                                    @if ($t['order_status'])
                                        <x-teknisi-status-badge :status="$t['order_status']" />
                                    @elseif (($t['kelompok'] ?? 1) === 2)
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">Di luar jam kerja</span>
                                    @else
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">Jam kerja</span>
                                    @endif
                                </div>
                                <p class="truncate text-xs text-gray-500">{{ $t['customer'] }} &middot; {{ $t['alamat'] }}</p>
                            </div>
                            <div class="text-right text-xs">
                                @php $statusApp = $t['app_status']; $aktifApp = $statusApp === 'aktif'; @endphp
                                {{-- Aplikasi: status dari waktu server (panggilan API / logout) --}}
                                <div>
                                    @if ($statusApp === 'aktif')
                                        <span class="font-semibold text-emerald-600">&#9679; App aktif &middot; {{ $t['last_seen_at']->diffForHumans() }}</span>
                                    @elseif ($statusApp === 'keluar')
                                        <span class="font-semibold text-gray-500">&#9679; Sudah logout{{ $t['last_seen_at'] ? ' · terakhir dibuka '.$t['last_seen_at']->diffForHumans() : '' }}</span>
                                    @elseif ($statusApp === 'tidak_aktif')
                                        <span class="font-semibold text-gray-500">&#9679; App tidak aktif &middot; terakhir dibuka {{ $t['last_seen_at']->diffForHumans() }}</span>
                                    @else
                                        <span class="font-semibold text-gray-400">&#9679; App belum pernah dibuka</span>
                                    @endif
                                </div>
                                {{-- GPS --}}
                                <div class="mt-0.5">
                                    @if ($t['lat'] === null)
                                        <span class="font-semibold text-gray-400">GPS: belum ada sinyal</span>
                                    @else
                                        <span class="font-semibold {{ $t['stale'] ? 'text-amber-600' : 'text-emerald-600' }}">
                                            GPS: {{ $t['stale'] ? ($t['kelompok'] === 2 ? 'Terakhir' : 'Basi') : 'Live' }} &middot; {{ $t['updated_at']?->diffForHumans() }}
                                        </span>
                                    @endif
                                </div>
                                @if ($aktifApp && ($t['lat'] === null || $t['stale']))
                                    <div class="mt-0.5 text-[11px] text-rose-600">App aktif tetapi GPS belum masuk &mdash; cek izin lokasi / hemat baterai di HP.</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
