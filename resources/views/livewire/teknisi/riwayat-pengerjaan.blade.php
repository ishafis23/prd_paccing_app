<div class="space-y-3 px-4 pt-4">

    @forelse ($orders as $order)
        @php
            $laporan = $order->workReports->sortByDesc('id')->first();
            $total = number_format((float) $order->total(), 0, ',', '.');

            // Seluruh foto laporan terakhir: sebelum, sesudah, lalu per-kategori.
            $daftarFoto = collect();
            if ($laporan) {
                $daftarFoto = collect()
                    ->concat($laporan->foto_sebelum ? [['path' => $laporan->foto_sebelum, 'label' => 'Sebelum']] : [])
                    ->concat($laporan->foto_sesudah ? [['path' => $laporan->foto_sesudah, 'label' => 'Sesudah']] : [])
                    ->concat(
                        $laporan->photos
                            ->sortBy('urutan')
                            ->map(function ($p) {
                                $label = \App\Support\FotoLaporanSlot::untuk($p->orderItem?->kategori)[$p->slot] ?? $p->slot;

                                return ['path' => $p->path, 'label' => $label];
                            })
                    )
                    ->values();
            }
            $fotoUtama = $daftarFoto->first()['path'] ?? null;
        @endphp
        <a href="{{ url('/teknisi/order/'.$order->id) }}" wire:navigate
            class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 transition active:scale-[0.99] active:bg-gray-50">
            <div class="flex items-start gap-3">
                @if ($fotoUtama)
                    <img src="{{ asset('storage/'.ltrim($fotoUtama, '/')) }}" alt="Foto hasil pengerjaan {{ $order->customer->nama }}"
                        class="h-16 w-16 shrink-0 rounded-xl object-cover ring-1 ring-gray-100">
                @else
                    <x-initials-avatar :name="$order->customer->nama" size="h-16 w-16 text-sm" />
                @endif
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <span class="truncate font-bold text-gray-900">{{ $order->customer->nama }}</span>
                        <x-teknisi-status-badge :status="$order->status" />
                    </div>
                    <p class="mt-1 flex items-center gap-1 truncate text-sm text-gray-500">
                        <x-heroicon-o-wrench-screwdriver class="h-4 w-4 shrink-0 text-gray-400" />
                        {{ $order->serviceCatalog->jenis_layanan->value }} &middot; {{ $order->jumlah_unit }} unit
                    </p>
                    <p class="mt-0.5 flex items-center gap-1 text-xs text-gray-400">
                        <x-heroicon-o-calendar-days class="h-3.5 w-3.5" />
                        {{ $order->updated_at->format('d M Y') }}
                    </p>
                </div>
            </div>
            @if ($daftarFoto->count() > 1)
                <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                    @foreach ($daftarFoto->take(4) as $foto)
                        <figure class="w-24 shrink-0">
                            <img src="{{ asset('storage/'.ltrim($foto['path'], '/')) }}"
                                alt="{{ $foto['label'] }} {{ $order->customer->nama }}"
                                class="h-24 w-24 rounded-xl object-cover ring-1 ring-gray-100" loading="lazy">
                            <figcaption class="mt-1 truncate text-center text-[9px] font-medium text-gray-400">{{ $foto['label'] }}</figcaption>
                        </figure>
                    @endforeach
                    @if ($daftarFoto->count() > 4)
                        <div class="flex h-24 w-16 shrink-0 flex-col items-center justify-center gap-0.5 rounded-xl bg-gray-50 text-xs font-bold text-gray-400 ring-1 ring-gray-100">
                            <x-heroicon-o-photo class="h-5 w-5" />
                            +{{ $daftarFoto->count() - 4 }}
                        </div>
                    @endif
                </div>
            @endif
            <div class="mt-3 flex items-center justify-between border-t border-dashed border-gray-100 pt-3">
                <span class="text-xs font-medium text-gray-400">Total Tagihan</span>
                <span class="text-sm font-extrabold text-gray-900">Rp {{ $total }}</span>
            </div>
        </a>
    @empty
        <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-50">
                <x-heroicon-o-clipboard-document-list class="h-8 w-8 text-blue-300" />
            </span>
            <p class="mt-4 font-bold text-gray-700">Belum ada riwayat pengerjaan</p>
            <p class="mt-1 text-sm text-gray-400">Order yang sudah Anda selesaikan akan tercatat di sini.</p>
        </div>
    @endforelse

    <div class="pt-1">
        {{ $orders->links() }}
    </div>
</div>
