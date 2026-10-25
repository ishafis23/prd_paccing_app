<div class="space-y-4 px-4 pt-4">
    @if (session('status')) <div class="rounded-2xl bg-green-50 p-4 text-sm font-semibold text-green-800 ring-1 ring-green-200">{{ session('status') }}</div> @endif
    @if (session('error')) <div class="rounded-2xl bg-red-50 p-4 text-sm font-semibold text-red-800 ring-1 ring-red-200">{{ session('error') }}</div> @endif

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <h1 class="flex items-center gap-2 font-extrabold text-gray-900"><x-heroicon-o-banknotes class="h-5 w-5 text-blue-600" /> Setoran Tunai</h1>
        <p class="mt-1 text-xs text-gray-500">Laporkan uang tunai yang telah diserahkan beserta bukti fotonya.</p>
        @forelse ($kewajiban as $order)
            <div class="mt-3 rounded-xl {{ in_array($order->id, $telatIds, true) ? 'bg-red-50 ring-red-200' : 'bg-amber-50 ring-amber-200' }} p-3 ring-1">
                <p class="font-bold text-sm text-gray-900">#{{ $order->id }} — {{ $order->customer?->nama }}</p>
                <p class="text-xs text-gray-600">Tagihan Rp {{ number_format((float) $order->total(), 0, ',', '.') }} · {{ $order->metode_dipilih_pada?->format('d M Y') }}</p>
                @if (in_array($order->id, $telatIds, true)) <span class="text-xs font-bold text-red-700">Terlambat dilaporkan</span> @endif
            </div>
        @empty
            <p class="mt-3 text-sm text-gray-500">Tidak ada kewajiban setoran tunai.</p>
        @endforelse
    </div>

    @if ($kewajiban->isNotEmpty())
        <form wire:submit="simpan" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 space-y-3">
            <h2 class="font-bold text-gray-900">Laporkan Setoran</h2>
            <select wire:model="pilihOrderId" class="w-full rounded-xl border-gray-300 text-sm"><option value="">Pilih order</option>@foreach($kewajiban as $order)<option value="{{ $order->id }}">#{{ $order->id }} — {{ $order->customer?->nama }}</option>@endforeach</select>
            <input wire:model="nominal" type="number" min="1000" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Nominal setoran">
            <input wire:model="diserahkanKe" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Diserahkan kepada siapa">
            <input wire:model="tanggalSetor" type="date" class="w-full rounded-xl border-gray-300 text-sm">
            <textarea wire:model="catatan" class="w-full rounded-xl border-gray-300 text-sm" placeholder="Catatan (opsional)"></textarea>
            <input type="file" accept="image/*" capture="environment" wire:model="foto" class="w-full text-sm">
            @if ($foto) <img src="{{ $foto->temporaryUrl() }}" class="h-32 w-full rounded-xl object-cover"> @endif
            @error('foto') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <button class="w-full rounded-xl bg-blue-600 py-3 text-sm font-bold text-white active:bg-blue-700" wire:loading.attr="disabled">Kirim Laporan Setoran</button>
        </form>
    @endif

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <h2 class="font-bold text-gray-900">Riwayat Setoran</h2>
        @forelse($riwayat as $handover)
            <div class="mt-3 flex gap-3 border-t border-gray-100 pt-3 first:border-0 first:pt-0">
                <img src="{{ $handover->fotoUrl() }}" class="h-12 w-12 rounded-lg object-cover" alt="Bukti setoran">
                <div class="min-w-0 flex-1 text-xs"><p class="font-bold">#{{ $handover->order_id }} · Rp {{ number_format((float) $handover->nominal, 0, ',', '.') }}</p><p>{{ $handover->tanggal_setor?->format('d M Y') }} · {{ $handover->diserahkan_ke }}</p><p class="font-semibold">{{ $handover->status->label() }}</p>@if($handover->catatan_verifikasi)<p>{{ $handover->catatan_verifikasi }}</p>@endif</div>
            </div>
        @empty <p class="mt-2 text-sm text-gray-500">Belum ada riwayat setoran.</p> @endforelse
    </div>
</div>
