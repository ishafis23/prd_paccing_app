<div class="space-y-4 px-4 pt-4">
    <div class="rounded-2xl bg-gradient-to-r from-blue-600 to-sky-500 p-4 shadow-md shadow-blue-200">
        <p class="text-sm font-semibold text-white">Capaian pengerjaan order Anda sejauh ini.</p>
    </div>

    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                <x-heroicon-o-bolt class="h-5 w-5" />
            </span>
            <p class="mt-2 text-2xl font-extrabold text-gray-900">{{ $this->hariIni }}</p>
            <p class="mt-0.5 text-[11px] font-medium text-gray-500">Selesai Hari Ini</p>
        </div>
        <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <x-heroicon-o-calendar-days class="h-5 w-5" />
            </span>
            <p class="mt-2 text-2xl font-extrabold text-gray-900">{{ $this->bulanIni }}</p>
            <p class="mt-0.5 text-[11px] font-medium text-gray-500">Selesai Bulan Ini</p>
        </div>
        <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                <x-heroicon-o-trophy class="h-5 w-5" />
            </span>
            <p class="mt-2 text-2xl font-extrabold text-gray-900">{{ $this->totalSelesai }}</p>
            <p class="mt-0.5 text-[11px] font-medium text-gray-500">Total Selesai</p>
        </div>
    </div>

    {{-- Rincian harian per bulan --}}
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
            <h2 class="font-bold text-gray-900">Rincian Harian</h2>
            <input
                type="month"
                wire:model.live="filterBulan"
                class="rounded-lg border border-gray-300 px-2 py-1 text-xs focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-2 font-semibold">No</th>
                        <th class="px-2 py-2 font-semibold">Tanggal</th>
                        <th class="px-2 py-2 text-center font-semibold">Selesai</th>
                        <th class="px-4 py-2 text-center font-semibold">Terkendala</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach ($this->capaianHarian as $baris)
                        <tr class="{{ $baris['selesai'] === 0 && $baris['terkendala'] === 0 ? 'text-gray-300' : 'text-gray-800' }}">
                            <td class="px-4 py-2 text-xs">{{ $baris['hari'] }}</td>
                            <td class="px-2 py-2 text-xs">{{ $baris['tanggal'] }}</td>
                            <td class="px-2 py-2 text-center">
                                @if ($baris['selesai'] > 0)
                                    <span class="inline-flex min-w-[24px] justify-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                        {{ $baris['selesai'] }}
                                    </span>
                                @else
                                    <span class="text-xs">0</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if ($baris['terkendala'] > 0)
                                    <span class="inline-flex min-w-[24px] justify-center rounded-full bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700">
                                        {{ $baris['terkendala'] }}
                                    </span>
                                @else
                                    <span class="text-xs">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
