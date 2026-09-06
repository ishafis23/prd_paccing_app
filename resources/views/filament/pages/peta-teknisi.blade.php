<x-filament-panels::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        function petaTeknisi(markers, center, zoom) {
            return {
                map: null,
                layer: null,
                init() {
                    this.map = L.map(this.$el).setView(center, zoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; OpenStreetMap contributors',
                        maxZoom: 19,
                    }).addTo(this.map);

                    this.layer = L.layerGroup().addTo(this.map);
                    markers.forEach((t) => {
                        L.marker([t.lat, t.lng], { opacity: t.stale ? 0.5 : 1 })
                            .addTo(this.layer)
                            .bindPopup(
                                `<b>${t.nama}</b><br>${t.customer}<br>${t.alamat}`
                                + `<br><small>${t.stale ? 'Sinyal terakhir (basi): ' : 'Update: '}${t.updated_label}</small>`
                            );
                    });
                },
                destroy() {
                    if (this.map) this.map.remove();
                },
            };
        }
    </script>

    <div class="space-y-5" wire:poll.20s>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-950">Posisi Teknisi Saat Ini</h2>
                <span class="text-xs text-gray-400">Auto-refresh tiap 20 detik</span>
            </div>

            @if ($markers->isEmpty())
                <p class="mt-3 text-sm text-gray-400">
                    Belum ada teknisi yang mengirim GPS. Peta hanya menyala saat teknisi sedang menuju lokasi atau mengerjakan order.
                </p>
            @else
                <div
                    wire:key="peta-teknisi-map-{{ $markersKey }}"
                    x-data="petaTeknisi(@js($markers), @js($center), {{ $zoom }})"
                    x-init="init()"
                    class="mt-3 h-[420px] w-full overflow-hidden rounded-xl ring-1 ring-gray-100"
                ></div>
            @endif
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <h2 class="mb-3 text-sm font-semibold text-gray-950">Teknisi Sedang Bertugas ({{ $teknisi->count() }})</h2>

            @if ($teknisi->isEmpty())
                <p class="text-sm text-gray-400">Tidak ada teknisi yang sedang menuju lokasi atau mengerjakan order.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($teknisi as $t)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-gray-900">{{ $t['nama'] }}</p>
                                    <x-teknisi-status-badge :status="$t['order_status']" />
                                </div>
                                <p class="truncate text-xs text-gray-500">{{ $t['customer'] }} &middot; {{ $t['alamat'] }}</p>
                            </div>
                            <div class="text-right text-xs">
                                @if ($t['lat'] === null)
                                    <span class="font-semibold text-gray-400">Belum ada sinyal GPS</span>
                                @else
                                    <span class="font-semibold {{ $t['stale'] ? 'text-amber-600' : 'text-emerald-600' }}">
                                        {{ $t['stale'] ? 'Basi' : 'Live' }} &middot; {{ $t['updated_at']?->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
