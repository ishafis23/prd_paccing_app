<x-filament-panels::page>
    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $kartu = 'rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5';
        $input = 'mt-1 w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500';
    @endphp

    <div class="space-y-5">
        <div class="{{ $kartu }}">
            <p class="text-sm text-gray-600">
                Bonus <b>Admin, HRD, dan Finance</b> = <b>{{ rtrim(rtrim(number_format((float) $persen, 2, ',', '.'), '0'), ',') }}%</b> dari <b>omset jasa</b> periode,
                diberikan hanya bila omset jasa mencapai minimal <b>{{ $rp($minimalOmsetJasa) }}</b>. Omset jasa memakai sumber yang sama dengan Dashboard Pimpinan dan Akuntan.
            </p>
        </div>

        {{-- Perhitungan --}}
        <div class="{{ $kartu }} space-y-4">
            <h2 class="text-sm font-semibold text-gray-950">Perhitungan Periode</h2>
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500">Mulai tanggal</label>
                    <input type="date" wire:model.live="dari" class="mt-1 rounded-lg border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500">Sampai tanggal</label>
                    <input type="date" wire:model.live="sampai" class="mt-1 rounded-lg border-gray-300 text-sm">
                </div>
            </div>

            @if ($hasil === null)
                <p class="text-sm text-rose-600">Periode tidak valid — pastikan tanggal mulai tidak setelah tanggal sampai.</p>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-xs font-semibold uppercase text-gray-500">Omset Jasa</p>
                        <p class="mt-1 text-xl font-bold text-gray-800">{{ $rp($hasil['omset_jasa']) }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3">
                        <p class="text-xs font-semibold uppercase text-gray-500">Syarat Minimal</p>
                        <p class="mt-1 text-xl font-bold text-gray-800">{{ $rp($hasil['minimal']) }}</p>
                    </div>
                    <div class="rounded-lg p-3 {{ $hasil['memenuhi'] ? 'bg-emerald-50' : 'bg-amber-50' }}">
                        <p class="text-xs font-semibold uppercase text-gray-500">Status</p>
                        @if (! $hasil['aktif'])
                            <p class="mt-1 text-sm font-bold text-gray-600">Bonus dinonaktifkan</p>
                        @elseif ($hasil['memenuhi'])
                            <p class="mt-1 text-xl font-bold text-emerald-700">Memenuhi syarat</p>
                        @else
                            <p class="mt-1 text-sm font-bold text-amber-700">Belum memenuhi — kurang {{ $rp($hasil['kurang']) }}</p>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Penerima</th>
                                <th class="px-4 py-3">Peran</th>
                                <th class="px-4 py-3 text-right">Bonus</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($hasil['penerima'] as $p)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-700">{{ $p['nama'] }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $p['peran'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $p['bonus'] > 0 ? 'text-emerald-700' : 'text-gray-400' }}">{{ $rp($p['bonus']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">Belum ada pengguna aktif dengan peran penerima.</td></tr>
                            @endforelse
                        </tbody>
                        @if (count($hasil['penerima']) > 0)
                            <tfoot class="border-t-2 border-gray-200 text-sm font-bold">
                                <tr>
                                    <td class="px-4 py-3" colspan="2">Total bonus yang dibayarkan</td>
                                    <td class="px-4 py-3 text-right">{{ $rp($hasil['bonus_per_orang'] * count($hasil['penerima'])) }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                <p class="text-xs text-gray-400">
                    Mode: {{ $hasil['mode'] === 'dibagi_rata' ? 'dibagi rata ke semua penerima' : 'tiap penerima mendapat persentase penuh' }}.
                    Ini hasil perhitungan untuk dicatat/dibayar di luar slip teknisi.
                </p>
            @endif
        </div>

        {{-- Pengaturan (Owner) --}}
        <form wire:submit="simpan" class="{{ $kartu }} space-y-4">
            <h2 class="text-sm font-semibold text-gray-950">Pengaturan Bonus</h2>
            @unless ($bolehUbah)
                <p class="text-xs text-gray-500">Hanya Owner yang dapat mengubah pengaturan.</p>
            @endunless

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model="aktif" @disabled(! $bolehUbah) class="rounded border-gray-300">
                Aktifkan bonus admin
            </label>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-semibold text-gray-500">Persentase dari omset jasa (%)</label>
                    <input type="number" step="0.01" min="0" max="100" wire:model="persen" @disabled(! $bolehUbah) class="{{ $input }}">
                    @error('persen') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500">Minimal omset jasa (Rp)</label>
                    <input type="number" min="0" wire:model="minimalOmsetJasa" @disabled(! $bolehUbah) class="{{ $input }}">
                    @error('minimalOmsetJasa') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500">Pembagian</label>
                    <select wire:model="mode" @disabled(! $bolehUbah) class="{{ $input }}">
                        <option value="per_orang">Tiap penerima mendapat persentase penuh</option>
                        <option value="dibagi_rata">Dibagi rata ke semua penerima</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500">Penerima (peran)</label>
                    <div class="mt-2 flex flex-wrap gap-4 text-sm text-gray-700">
                        @foreach (['admin' => 'Admin', 'hr' => 'HRD', 'finance' => 'Finance'] as $nilai => $label)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" value="{{ $nilai }}" wire:model="peran" @disabled(! $bolehUbah) class="rounded border-gray-300">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('peran') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($bolehUbah)
                <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Simpan Pengaturan</button>
            @endif
        </form>
    </div>
</x-filament-panels::page>
