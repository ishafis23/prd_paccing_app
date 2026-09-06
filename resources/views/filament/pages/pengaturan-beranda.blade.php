<x-filament-panels::page>
    <div class="space-y-5">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <p class="text-sm text-gray-600">
                Atur isi halaman depan (landing): peta, jam operasional, sosmed, dan seksi yang tampil.
                Slide hero &amp; layanan dikelola lewat menu <b>Hero Slider</b> dan <b>Layanan Beranda</b>.
            </p>
        </div>

        <form wire:submit="simpan" class="grid gap-5 lg:grid-cols-2">
            <div class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Peta &amp; Kontak Umum</h2>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Google Maps (URL embed)</label>
                    <textarea wire:model="mapsEmbed" rows="3" placeholder="https://www.google.com/maps/embed?pb=..."
                        class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-xs text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500"></textarea>
                    @error('mapsEmbed') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-gray-400">
                        Cara: buka Google Maps → cari alamat usaha → Bagikan → <b>Embed peta</b> → salin bagian
                        <code>src="https://www.google.com/maps/embed?pb=..."</code> lalu tempel di sini.
                        Kosongkan = seksi peta tidak tampil.
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">Jam Operasional</label>
                    <input type="text" wire:model="jamOperasional" placeholder="mis. Senin–Sabtu, 08.00–17.00"
                        class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Instagram (URL)</label>
                        <input type="text" wire:model="sosmedInstagram" placeholder="https://instagram.com/paccing"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Facebook (URL)</label>
                        <input type="text" wire:model="sosmedFacebook" placeholder="https://facebook.com/paccing"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500" />
                    </div>
                </div>
            </div>

            <div class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Seksi yang Tampil di Landing</h2>

                @php
                    $seksi = [
                        'tampilLayanan' => 'Layanan',
                        'tampilCaraKerja' => 'Cara Kerja (4 langkah)',
                        'tampilArea' => 'Area Layanan',
                        'tampilPeta' => 'Peta Lokasi (butuh URL embed di atas)',
                    ];
                @endphp
                <div class="space-y-2">
                    @foreach ($seksi as $prop => $nama)
                        <label class="flex items-center gap-3 rounded-xl bg-gray-50 px-3 py-2.5 text-sm font-medium text-gray-700">
                            <input type="checkbox" wire:model="{{ $prop }}" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            {{ $nama }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-400">Hero carousel selalu tampil (bila ada slide aktif); keunggulan &amp; CTA kontak selalu tampil.</p>

                <div class="flex justify-end border-t border-gray-100 pt-4">
                    <x-filament::button type="submit" icon="heroicon-m-check-circle">
                        Simpan Pengaturan
                    </x-filament::button>
                </div>
            </div>
        </form>
    </div>
</x-filament-panels::page>
