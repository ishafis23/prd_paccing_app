<div class="space-y-4 p-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-base font-bold text-gray-900">Ajukan Pulang Cepat</h2>
        <p class="mt-0.5 text-xs text-gray-500">Isi tanggal & jam pulang bila Anda pulang lebih awal. Pengajuan ini dihitung setengah hari setelah disetujui HRD.</p>

        @if (session('status'))
            <div class="mt-3 rounded-xl bg-green-50 px-3 py-2 text-sm text-green-700 ring-1 ring-green-100">{{ session('status') }}</div>
        @endif

        <form wire:submit="ajukan" class="mt-4 space-y-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Tanggal</label>
                <input type="date" wire:model="tanggal" max="{{ now()->toDateString() }}"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5">
                @error('tanggal') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Jam Pulang</label>
                <input type="time" wire:model="jam_pulang"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5">
                @error('jam_pulang') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Alasan (opsional)</label>
                <textarea wire:model="alasan" rows="2"
                    class="w-full rounded-xl border border-gray-300 px-4 py-2.5"></textarea>
                @error('alasan') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-full bg-blue-600 py-3 font-semibold text-white shadow-md shadow-blue-200 active:bg-blue-700">
                Kirim Pengajuan
            </button>
        </form>
    </div>

    <div>
        <h3 class="mb-2 px-1 text-sm font-bold text-gray-700">Riwayat Pengajuan</h3>

        @forelse ($riwayat as $row)
            @php
                $badge = match ($row->status->value) {
                    'disetujui' => 'bg-green-100 text-green-700',
                    'ditolak' => 'bg-red-100 text-red-700',
                    default => 'bg-amber-100 text-amber-700',
                };
            @endphp
            <div class="mb-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-sm font-bold text-gray-900">{{ $row->tanggal->translatedFormat('d F Y') }}</p>
                        <p class="text-xs text-gray-500">Pulang {{ \Illuminate\Support\Carbon::parse($row->jam_pulang)->format('H:i') }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge }}">{{ $row->status->label() }}</span>
                </div>
                @if ($row->alasan)
                    <p class="mt-2 text-xs text-gray-600">{{ $row->alasan }}</p>
                @endif
                @if ($row->catatan_hrd)
                    <p class="mt-1 text-xs text-gray-500">Catatan HRD: {{ $row->catatan_hrd }}</p>
                @endif
            </div>
        @empty
            <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
                <p class="text-sm text-gray-400">Belum ada pengajuan.</p>
            </div>
        @endforelse
    </div>
</div>
