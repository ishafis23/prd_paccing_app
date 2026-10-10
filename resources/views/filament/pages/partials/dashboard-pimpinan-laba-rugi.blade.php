{{-- Laba/Rugi Harian & Pekanan. --}}
<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    {{-- Harian --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
            <h3 class="text-sm font-bold text-gray-700">Laba/Rugi Harian</h3>
        </div>
        @if ($harian->isEmpty())
            <p class="p-6 text-center text-sm text-gray-400">Belum ada data pada bulan ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="{{ $th }}">Tanggal</th>
                            <th class="{{ $th }} text-right">Pendapatan</th>
                            <th class="{{ $th }} text-right">Pengeluaran</th>
                            <th class="{{ $th }} text-right">Laba/Rugi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($harian as $r)
                            <tr class="hover:bg-gray-50/60">
                                <td class="{{ $th }} font-medium text-gray-700">{{ $tgl($r['tanggal']) }}</td>
                                <td class="{{ $th }} text-right text-emerald-600">{{ $rp($r['pendapatan']) }}</td>
                                <td class="{{ $th }} text-right text-rose-600">{{ $rp($r['pengeluaran']) }}</td>
                                <td class="{{ $th }} text-right font-semibold {{ $r['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $rp($r['laba_rugi']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Pekanan --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
            <h3 class="text-sm font-bold text-gray-700">Laba/Rugi Pekanan</h3>
        </div>
        @if ($pekanan->isEmpty())
            <p class="p-6 text-center text-sm text-gray-400">Belum ada data pada bulan ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="{{ $th }}">Pekan</th>
                            <th class="{{ $th }} text-right">Pendapatan</th>
                            <th class="{{ $th }} text-right">Pengeluaran</th>
                            <th class="{{ $th }} text-right">Laba/Rugi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pekanan as $r)
                            <tr class="hover:bg-gray-50/60">
                                <td class="{{ $th }} font-medium text-gray-700">{{ $r['label'] }}</td>
                                <td class="{{ $th }} text-right text-emerald-600">{{ $rp($r['pendapatan']) }}</td>
                                <td class="{{ $th }} text-right text-rose-600">{{ $rp($r['pengeluaran']) }}</td>
                                <td class="{{ $th }} text-right font-semibold {{ $r['laba_rugi'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">{{ $rp($r['laba_rugi']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
