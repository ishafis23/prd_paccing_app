<div class="space-y-3 px-4 pt-4">

    <div class="space-y-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <input type="text" wire:model.live.debounce.400ms="cariNama" placeholder="Cari nama customer…"
            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500">
        <div class="grid grid-cols-2 gap-2">
            <input type="date" wire:model.live="tanggal"
                class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500">
            <select wire:model.live="jenisLayanan"
                class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Layanan</option>
                @foreach ($opsiLayanan as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @if ($cariNama !== '' || $tanggal !== '' || $jenisLayanan !== '')
            <button type="button" wire:click="resetFilter" class="text-xs font-semibold text-blue-600">
                Reset Filter
            </button>
        @endif
    </div>

    @forelse ($orders as $order)
        @php
            // List riwayat sengaja TANPA render foto (ringan walau sudah banyak
            // pengerjaan) — fotonya dilihat lewat halaman detail order.
            $total = number_format((float) $order->total(), 0, ',', '.');
        @endphp
        <a href="{{ url('/teknisi/order/'.$order->id) }}" wire:navigate
            class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 transition active:scale-[0.99] active:bg-gray-50">
            <div class="flex items-start gap-3">
                <x-initials-avatar :name="$order->customer->nama" size="h-16 w-16 text-sm" />
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
            @if ($cariNama !== '' || $tanggal !== '' || $jenisLayanan !== '')
                <p class="mt-4 font-bold text-gray-700">Tidak ada yang cocok</p>
                <p class="mt-1 text-sm text-gray-400">Coba ubah/hapus filter di atas.</p>
            @else
                <p class="mt-4 font-bold text-gray-700">Belum ada riwayat pengerjaan</p>
                <p class="mt-1 text-sm text-gray-400">Order yang sudah Anda selesaikan akan tercatat di sini.</p>
            @endif
        </div>
    @endforelse

    <div class="pt-1">
        {{ $orders->links() }}
    </div>
</div>