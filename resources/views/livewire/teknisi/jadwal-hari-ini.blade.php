<div class="space-y-4 px-4 pt-4">

    {{-- Kartu sapaan --}}
    <div class="flex items-center gap-3 rounded-2xl bg-gradient-to-r from-blue-600 to-sky-500 p-4 shadow-md shadow-blue-200">
        @if ($urlFoto = auth()->user()->fotoProfilUrl())
            <img src="{{ $urlFoto }}" alt="Foto {{ auth()->user()->name }}" class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white/60">
        @else
            <x-initials-avatar :name="auth()->user()->name" size="h-12 w-12 text-base" />
        @endif
        <div class="min-w-0 text-white">
            <p class="text-xs text-sky-100">Halo,</p>
            <p class="truncate text-base font-bold">{{ auth()->user()->name }}</p>
        </div>
    </div>

    {{-- Gating absen (masukan client Poin 4): buka order hanya setelah absen datang. --}}
    @unless ($sudahAbsen)
        <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
            <p class="flex items-center gap-1.5 text-sm font-bold text-amber-800">
                <x-heroicon-o-exclamation-triangle class="h-4 w-4" /> Anda belum absen hari ini
            </p>
            <p class="mt-1 text-xs text-amber-700">Absen datang dulu sebelum membuka order (biar tidak terlewat).</p>
            <a href="{{ \App\Support\Url::absolute('teknisi.absensi') }}" wire:navigate
                class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-amber-500 px-4 py-2 text-sm font-bold text-white active:bg-amber-600">
                <x-heroicon-o-qr-code class="h-4 w-4" /> Absen Sekarang
            </a>
        </div>
    @endunless

    @php
        $grup = [
            ['judul' => 'Belum Selesai Sebelumnya', 'orders' => $ordersSebelumnya, 'nyala' => true],
            ['judul' => 'Hari Ini & Akan Datang', 'orders' => $ordersHariIni, 'nyala' => false],
        ];
    @endphp

    @foreach ($grup as $g)
        @if ($g['orders']->isNotEmpty())
            <div>
                <p class="mb-2 px-1 text-sm font-bold {{ $g['nyala'] ? 'text-red-600' : 'text-gray-500' }}">{{ $g['judul'] }}</p>
                <div class="space-y-3">
                    @foreach ($g['orders'] as $order)
                        @php
                            $serviceIcon = match ($order->serviceCatalog->jenis_layanan->value) {
                                'cuci_ac' => 'heroicon-o-sparkles',
                                'service_ac' => 'heroicon-o-wrench-screwdriver',
                                default => 'heroicon-o-cube',
                            };
                            $serviceColor = match ($order->serviceCatalog->jenis_layanan->value) {
                                'cuci_ac' => 'bg-sky-50 text-sky-600',
                                'service_ac' => 'bg-blue-50 text-blue-600',
                                default => 'bg-gray-50 text-gray-600',
                            };
                        @endphp
                        <{{ $sudahAbsen ? 'a' : 'div' }}
                            @if ($sudahAbsen) href="{{ \App\Support\Url::absolute('teknisi.order', ['order' => $order->id]) }}" wire:navigate @endif
                            class="block rounded-2xl bg-white p-4 shadow-sm ring-1 {{ $g['nyala'] ? 'ring-red-100' : 'ring-gray-100' }} {{ $sudahAbsen ? 'transition active:scale-[0.99] active:bg-gray-50' : 'opacity-80' }}">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl {{ $serviceColor }}">
                                    <x-dynamic-component :component="$serviceIcon" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="truncate font-bold text-gray-900">{{ $order->customer->nama }}</span>
                                        <x-teknisi-status-badge :status="$order->status" />
                                    </div>
                                    <p class="mt-0.5 truncate text-sm text-gray-500">{{ $order->alamat_pengerjaan }}</p>
                                    @if (app(\App\Services\TeknisiService::class)->perluDilengkapi($order))
                                        <span class="mt-1 inline-block rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Perlu dilengkapi</span>
                                    @endif
                                    <p class="mt-1 flex items-center gap-1 text-xs {{ $g['nyala'] ? 'font-bold text-red-600' : 'text-gray-400' }}">
                                        <x-heroicon-o-calendar-days class="h-3.5 w-3.5" />
                                        {{ $order->tanggal_jadwal?->format('d M Y') }} &middot; {{ $order->jam_jadwal }}
                                    </p>
                                </div>
                                @if ($sudahAbsen)
                                    <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-gray-300" />
                                @else
                                    <x-heroicon-o-lock-closed class="h-5 w-5 shrink-0 text-gray-300" />
                                @endif
                            </div>
                        </{{ $sudahAbsen ? 'a' : 'div' }}>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach

    @if ($ordersSebelumnya->isEmpty() && $ordersHariIni->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-10 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-50">
                <x-heroicon-o-calendar class="h-8 w-8 text-blue-300" />
            </span>
            <p class="mt-4 font-bold text-gray-700">Tidak ada order aktif</p>
            <p class="mt-1 text-sm text-gray-400">Order baru yang dijadwalkan untuk Anda akan muncul di sini.</p>
        </div>
    @endif
</div>
