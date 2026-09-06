<div class="space-y-4 px-4 pt-4">

    {{-- Kartu sapaan --}}
    <div class="flex items-center gap-3 rounded-2xl bg-gradient-to-r from-blue-600 to-sky-500 p-4 shadow-md shadow-blue-200">
        <x-initials-avatar :name="auth()->user()->name" size="h-12 w-12 text-base" />
        <div class="min-w-0 text-white">
            <p class="text-xs text-sky-100">Halo,</p>
            <p class="truncate text-base font-bold">{{ auth()->user()->name }}</p>
        </div>
    </div>

    <div>
        <p class="mb-2 px-1 text-sm font-bold text-gray-500">Order Aktif</p>

        <div class="space-y-3">
            @forelse ($orders as $order)
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
                <a href="{{ url('/teknisi/order/'.$order->id) }}" wire:navigate
                    class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 transition active:scale-[0.99] active:bg-gray-50">
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
                            <p class="mt-0.5 flex items-center gap-1 text-xs text-gray-400">
                                <x-heroicon-o-calendar-days class="h-3.5 w-3.5" />
                                {{ $order->tanggal_jadwal?->format('d M Y') }} &middot; {{ $order->jam_jadwal }}
                            </p>
                        </div>
                        <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-gray-300" />
                    </div>
                </a>
            @empty
                <div class="rounded-2xl bg-white px-6 py-10 text-center shadow-sm ring-1 ring-gray-100">
                    <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-50">
                        <x-heroicon-o-calendar class="h-8 w-8 text-blue-300" />
                    </span>
                    <p class="mt-4 font-bold text-gray-700">Tidak ada order aktif</p>
                    <p class="mt-1 text-sm text-gray-400">Order baru yang dijadwalkan untuk Anda akan muncul di sini.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
