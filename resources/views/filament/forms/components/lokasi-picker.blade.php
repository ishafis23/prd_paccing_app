@php
    $hasTitik = $lat !== null && $lat !== '' && $lng !== null && $lng !== '';
    $center = $hasTitik ? [(float) $lat, (float) $lng] : [-2.5, 118.0];
    $zoom = $hasTitik ? 16 : 5;
    $mapKey = md5(($lat ?? '').':'.($lng ?? ''));
@endphp

<div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <div
        wire:key="lokasi-picker-{{ $mapKey }}"
        wire:ignore
        x-data="{
            map: null,
            marker: null,
            latPath: @js($latPath),
            lngPath: @js($lngPath),
            hasTitik: {{ $hasTitik ? 'true' : 'false' }},
            init() {
                this.map = L.map(this.$el).setView(@js($center), {{ $zoom }});
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(this.map);

                if (this.hasTitik) {
                    this.taruhMarker(@js($center));
                }

                this.map.on('click', (e) => this.pilihTitik(e.latlng.lat, e.latlng.lng));
            },
            taruhMarker(latlng) {
                if (this.marker) {
                    this.marker.setLatLng(latlng);
                    return;
                }
                this.marker = L.marker(latlng, { draggable: true }).addTo(this.map);
                this.marker.on('dragend', () => {
                    const p = this.marker.getLatLng();
                    this.simpanTitik(p.lat, p.lng);
                });
            },
            pilihTitik(lat, lng) {
                this.taruhMarker([lat, lng]);
                this.simpanTitik(lat, lng);
            },
            simpanTitik(lat, lng) {
                $wire.set(this.latPath, Math.round(lat * 1e7) / 1e7);
                $wire.set(this.lngPath, Math.round(lng * 1e7) / 1e7);
            },
            destroy() {
                if (this.map) this.map.remove();
            },
        }"
        x-init="init()"
        class="h-[320px] w-full overflow-hidden rounded-xl ring-1 ring-gray-200"
    ></div>
    <p class="mt-1 text-xs text-gray-400">Klik di peta atau geser pin untuk mengatur titik lokasi customer.</p>
</div>
