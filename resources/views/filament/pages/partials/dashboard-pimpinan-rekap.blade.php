{{-- Rekap Harian & Pekanan (dipakai tab Pendapatan & Pengeluaran). --}}
@php $rp = $rp ?? fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.'); @endphp
@php $angka = $angka ?? fn ($n) => number_format((float) $n, 0, ',', '.'); @endphp
@php $tgl = $tgl ?? fn ($t) => \Illuminate\Support\Carbon::parse($t)->translatedFormat('d M Y'); @endphp

<div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    {{-- Harian --}}
    <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="border-b border-gray-100 bg-gray-50/60 px-4 py-3">
            <h3 class="text-sm font-bold text-gray-700">{{ $labelTotal }} Harian</h3>
        </div>
        @if ($harian->isEmpty())
            <p class="p-6 text-center text-sm text-gray-400">Belum ada data pada bulan ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="{{ $th }}">Tanggal</th>
                            <th class="{{ $th }} text-right">Jumlah</th>
                            <th class="{{ $th }} text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($harian as $r)
                            <tr class="hover:bg-gray-50/60">
                                <td class="{{ $th }} font-medium text-gray-700">{{ $tgl($r['tanggal']) }}</td>
                                <td class="{{ $th }} text-right">{{ $angka($r['jumlah']) }}</td>
                                <td class="{{ $th }} text-right font-semibold">
                                    {{ $rp($r['total']) }}
                                    @if (($r['pending'] ?? 0) > 0)
                                        <span class="block text-xs font-normal text-amber-600">+ {{ $rp($r['pending']) }} pending</span>
                                    @endif
                                </td>
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
            <h3 class="text-sm font-bold text-gray-700">{{ $labelTotal }} Pekanan</h3>
        </div>
        @if ($pekanan->isEmpty())
            <p class="p-6 text-center text-sm text-gray-400">Belum ada data pada bulan ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="{{ $th }}">Pekan</th>
                            <th class="{{ $th }} text-right">Jumlah</th>
                            <th class="{{ $th }} text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($pekanan as $r)
                            <tr class="hover:bg-gray-50/60">
                                <td class="{{ $th }} font-medium text-gray-700">{{ $r['label'] }}</td>
                                <td class="{{ $th }} text-right">{{ $angka($r['jumlah']) }}</td>
                                <td class="{{ $th }} text-right font-semibold">
                                    {{ $rp($r['total']) }}
                                    @if (($r['pending'] ?? 0) > 0)
                                        <span class="block text-xs font-normal text-amber-600">+ {{ $rp($r['pending']) }} pending</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
