<div class="space-y-4 p-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-base font-bold text-gray-900">Unit AC</h2>
        <p class="mt-0.5 text-xs text-gray-500">Rekap unit per cabang & status pengerjaannya.</p>
        <select wire:model.live="cabang" class="mt-3 w-full rounded-xl border border-gray-300 px-3 py-2 text-sm">
            <option value="">Semua cabang</option>
            @foreach ($alamatOptions as $alamat)
                <option value="{{ $alamat->id }}">{{ $alamat->nama_lokasi ?? $alamat->alamat }}</option>
            @endforeach
        </select>
    </div>

    @php $grup = $rekap->groupBy(fn ($row) => $row['unit']->customerAddress?->nama_lokasi ?? 'Tanpa cabang'); @endphp

    @forelse ($grup as $namaCabang => $baris)
        <div>
            <h3 class="mb-1.5 px-1 text-xs font-bold uppercase tracking-wide text-gray-500">{{ $namaCabang }}</h3>
            <div class="space-y-2">
                @foreach ($baris as $row)
                    @php
                        $unit = $row['unit'];
                        $badge = match ($row['status']) {
                            'Sudah' => 'bg-green-100 text-green-700',
                            'Terjadwal' => 'bg-blue-100 text-blue-700',
                            default => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-gray-900">{{ $unit->kode_unit }}</p>
                                <p class="text-sm text-gray-500">{{ $unit->kode_ruangan }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                @if ($unit->pk)
                                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ $unit->pk }}</span>
                                @endif
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge }}">{{ $row['status'] }}</span>
                            </div>
                        </div>

                        <div class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-600">
                            @if ($row['terakhir'])
                                <p>Terakhir: <span class="font-semibold text-gray-800">{{ $row['terakhir']->nama_layanan }}</span>
                                    — {{ $row['terakhir']->order?->tanggal_jadwal?->translatedFormat('d M Y') ?? '—' }}</p>
                            @else
                                <p class="text-gray-400">Belum pernah dikerjakan.</p>
                            @endif

                            @if ($row['terjadwal'])
                                <p class="mt-0.5 text-blue-600">Dijadwalkan: {{ $row['terjadwal']->order?->tanggal_jadwal?->translatedFormat('d M Y') ?? '—' }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
            <p class="text-sm text-gray-400">Belum ada unit terdaftar.</p>
        </div>
    @endforelse
</div>
