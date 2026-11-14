<x-filament-panels::page>
    @php
        $kartu = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5';
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $butir = ['absensi' => '1 · Absensi', 'dokumentasi' => '2 · Dokumentasi', 'dana' => '3 · Laporan Dana', 'setoran' => '4 · Setoran Tunai'];
        $lencana = fn (string $s) => match ($s) {
            'ok' => ['Lengkap', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'],
            'kurang' => ['Kurang', 'bg-red-50 text-red-700 ring-red-600/20'],
            default => ['-', 'bg-gray-50 text-gray-500 ring-gray-500/20'],
        };
    @endphp

    <div class="space-y-5">
        {{-- Pilih tanggal --}}
        <div class="flex flex-wrap items-center gap-3 {{ $kartu }}">
            <button type="button" wire:click="geser(-1)" class="rounded-lg px-3 py-1.5 text-sm font-bold ring-1 ring-gray-300 hover:bg-gray-50">&larr;</button>
            <input type="date" wire:model.live="tanggal" max="{{ now()->toDateString() }}"
                class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
            <button type="button" wire:click="geser(1)" @disabled($adalahHariIni)
                class="rounded-lg px-3 py-1.5 text-sm font-bold ring-1 ring-gray-300 hover:bg-gray-50 disabled:opacity-40">&rarr;</button>
            <span class="text-sm font-semibold text-gray-600">{{ $tanggalLabel }}</span>

            <div class="ml-auto flex gap-1">
                @foreach (['semua' => 'Semua teknisi', 'kurang' => 'Hanya yang kurang'] as $k => $l)
                    <button type="button" wire:click="$set('tampil', '{{ $k }}')"
                        class="rounded-full px-3 py-1.5 text-xs font-bold ring-1 {{ $tampil === $k ? 'bg-primary-600 text-white ring-primary-600' : 'text-gray-600 ring-gray-300 hover:bg-gray-50' }}">
                        {{ $l }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($adalahHariIni)
            <p class="text-xs text-amber-600">Hari ini belum berakhir: butir yang masih berjalan (absen pulang, order berjalan, pengeluaran) bisa menyusul. Gunakan tanggal kemarin atau sebelumnya sebelum transfer.</p>
        @endif

        {{-- Ringkasan --}}
        <div class="flex flex-wrap gap-4">
            <div class="{{ $kartu }} min-w-[9rem] flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Lengkap semua</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $ringkasan['lengkap'] }} <span class="text-sm font-medium text-gray-400">/ {{ $ringkasan['teknisi'] }} teknisi</span></p>
            </div>
            <div class="{{ $kartu }} min-w-[9rem] flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Ada yang kurang</p>
                <p class="mt-1 text-2xl font-bold {{ $ringkasan['kurang'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $ringkasan['kurang'] }}</p>
            </div>
            @foreach ($butir as $kode => $label)
                <div class="{{ $kartu }} min-w-[9rem] flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $ringkasan['per_butir'][$kode] > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $ringkasan['per_butir'][$kode] }} <span class="text-sm font-medium text-gray-400">kurang</span></p>
                </div>
            @endforeach
        </div>

        {{-- Tabel per teknisi --}}
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Teknisi</th>
                        @foreach ($butir as $label)
                            <th class="px-4 py-3">{{ $label }}</th>
                        @endforeach
                        <th class="px-4 py-3">Hasil</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($baris as $r)
                        <tr class="align-top">
                            <td class="px-4 py-3 font-semibold text-gray-800">
                                {{ $r['teknisi']->name }}
                                @if ($r['libur'])
                                    <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-bold text-gray-500">OFF</span>
                                @endif
                            </td>

                            {{-- 1 Absensi --}}
                            @php [$lb, $kls] = $lencana($r['absensi']['status']); @endphp
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-bold ring-1 {{ $kls }}">{{ $lb }}</span>
                                <p class="mt-1 text-xs text-gray-500">
                                    @if ($r['absensi']['datang']) Datang {{ $r['absensi']['datang'] }} @endif
                                    @if ($r['absensi']['pulang']) · Pulang {{ $r['absensi']['pulang'] }} @endif
                                </p>
                                @if ($r['absensi']['catatan']) <p class="text-xs text-gray-500">{{ $r['absensi']['catatan'] }}</p> @endif
                            </td>

                            {{-- 2 Dokumentasi --}}
                            @php [$lb, $kls] = $lencana($r['dokumentasi']['status']); @endphp
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-bold ring-1 {{ $kls }}">{{ $lb }}</span>
                                @if ($r['dokumentasi']['total'] > 0)
                                    <p class="mt-1 text-xs text-gray-500">{{ $r['dokumentasi']['lengkap'] }} dari {{ $r['dokumentasi']['total'] }} order lengkap</p>
                                @else
                                    <p class="mt-1 text-xs text-gray-400">Tidak ada order</p>
                                @endif
                                @foreach ($r['dokumentasi']['rincian'] as $x) <p class="text-xs text-red-600">{{ $x }}</p> @endforeach
                            </td>

                            {{-- 3 Dana --}}
                            @php [$lb, $kls] = $lencana($r['dana']['status']); @endphp
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-bold ring-1 {{ $kls }}">{{ $lb }}</span>
                                @if ($r['dana']['jumlah'] > 0)
                                    <p class="mt-1 text-xs text-gray-500">{{ $r['dana']['jumlah'] }} catatan · {{ $rp($r['dana']['total']) }}</p>
                                @endif
                                @if ($r['dana']['catatan']) <p class="text-xs {{ $r['dana']['status'] === 'kurang' ? 'text-red-600' : 'text-gray-500' }}">{{ $r['dana']['catatan'] }}</p> @endif
                            </td>

                            {{-- 4 Setoran --}}
                            @php [$lb, $kls] = $lencana($r['setoran']['status']); @endphp
                            <td class="px-4 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-xs font-bold ring-1 {{ $kls }}">{{ $lb }}</span>
                                @if ($r['setoran']['wajib'] > 0)
                                    <p class="mt-1 text-xs text-gray-500">{{ $r['setoran']['tersetor'] }} dari {{ $r['setoran']['wajib'] }} order tunai · setor {{ $rp($r['setoran']['nominal_setor']) }} / tagihan {{ $rp($r['setoran']['nominal_order']) }}</p>
                                @else
                                    <p class="mt-1 text-xs text-gray-400">Tidak ada order tunai</p>
                                @endif
                                @foreach ($r['setoran']['rincian'] as $x) <p class="text-xs text-red-600">{{ $x }}</p> @endforeach
                            </td>

                            <td class="px-4 py-3">
                                @if ($r['lengkap'])
                                    <span class="rounded-full bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white">Siap transfer</span>
                                @else
                                    <span class="rounded-full bg-red-600 px-2.5 py-1 text-xs font-bold text-white">Tahan dulu</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-gray-400">Tidak ada teknisi untuk ditampilkan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-400">
            Aturan: absensi lengkap = ada absen datang &amp; pulang · dokumentasi = foto &amp; keterangan wajib tiap order selesai terpenuhi · dana = minimal satu catatan pengeluaran (hari OFF dikecualikan), tidak ada yang ditolak/tanpa foto bukti · setoran = tiap order tunai punya laporan setoran dengan nominal sama dengan tagihan.
        </p>
    </div>
</x-filament-panels::page>
