<div class="space-y-3 px-4 pt-4">
    <p class="px-1 text-sm text-gray-500">
        Order di bawah ini sudah dilaporkan tetapi foto atau keterangan unitnya belum lengkap. Lengkapi dulu sebelum berangkat ke order berikutnya.
    </p>

    @forelse ($daftar as $baris)
        @php $order = $baris['order']; @endphp
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-amber-200">
        <a href="{{ $baris['tautan'] }}" wire:navigate
            class="block rounded-2xl p-4 transition active:scale-[0.99] active:bg-gray-50">
            <div class="flex items-start justify-between gap-2">
                <span class="truncate font-bold text-gray-900">{{ $order->customer->nama }}</span>
                <x-teknisi-status-badge :status="$order->status" />
            </div>
            <p class="mt-0.5 truncate text-sm text-gray-500">{{ $order->ringkasanLayanan() }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @if ($baris['foto'] > 0)
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">{{ $baris['foto'] }} foto kurang</span>
                @endif
                @if ($baris['keterangan'] > 0)
                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700">{{ $baris['keterangan'] }} keterangan unit kurang</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-gray-400">{{ $baris['rincian'] }}</p>
        </a>
        <div class="border-t border-gray-100 px-4 py-2">
            <a href="{{ \App\Support\Url::absolute('laporan.preview', ['order' => $order->id]) }}" target="_blank" rel="noopener"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700">
                <x-heroicon-o-document-magnifying-glass class="h-4 w-4" />
                Preview Laporan
            </a>
        </div>
        </div>
    @empty
        <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
                <x-heroicon-o-check-circle class="h-8 w-8 text-emerald-400" />
            </span>
            <p class="mt-4 font-bold text-gray-700">Semua laporan sudah lengkap</p>
            <p class="mt-1 text-sm text-gray-400">Tidak ada foto atau keterangan yang perlu dilengkapi.</p>
        </div>
    @endforelse
</div>
