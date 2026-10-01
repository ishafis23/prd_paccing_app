<div class="space-y-4 px-4 pt-4">
    {{-- Tab switcher --}}
    <div class="grid grid-cols-2 gap-1 rounded-2xl bg-white p-1 shadow-sm ring-1 ring-gray-100">
        <button
            type="button"
            wire:click="setTab('pengeluaran')"
            class="rounded-xl py-2 text-sm font-semibold transition {{ $tab === 'pengeluaran' ? 'bg-blue-600 text-white shadow' : 'text-gray-500 hover:text-gray-700' }}">
            Pengeluaran
        </button>
        <button
            type="button"
            wire:click="setTab('pendapatan')"
            class="rounded-xl py-2 text-sm font-semibold transition {{ $tab === 'pendapatan' ? 'bg-emerald-600 text-white shadow' : 'text-gray-500 hover:text-gray-700' }}">
            Pendapatan
        </button>
    </div>

    @if ($tab === 'pengeluaran')
        <livewire:teknisi.laporan-pengeluaran />
    @else
        {{-- Summary Cards --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-100">
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                    <x-heroicon-o-bolt class="h-5 w-5" />
                </span>
                <p class="mt-2 text-sm font-extrabold text-gray-900">{{ $this->formatRupiah($this->pendapatanHariIni) }}</p>
                <p class="mt-0.5 text-[11px] font-medium text-gray-500">Hari Ini</p>
            </div>
            <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-100">
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                    <x-heroicon-o-calendar-days class="h-5 w-5" />
                </span>
                <p class="mt-2 text-sm font-extrabold text-gray-900">{{ $this->formatRupiah($this->pendapatanBulanIni) }}</p>
                <p class="mt-0.5 text-[11px] font-medium text-gray-500">Bulan Ini</p>
            </div>
            <div class="rounded-2xl bg-white p-4 text-center shadow-sm ring-1 ring-gray-100">
                <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                    <x-heroicon-o-trophy class="h-5 w-5" />
                </span>
                <p class="mt-2 text-sm font-extrabold text-gray-900">{{ $this->formatRupiah($this->pendapatanTotal) }}</p>
                <p class="mt-0.5 text-[11px] font-medium text-gray-500">Total</p>
            </div>
        </div>

        {{-- Filter --}}
        <div class="rounded-2xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    wire:click="$set('modePendapatan', 'bulanan')"
                    class="rounded-lg py-1.5 text-xs font-semibold {{ $modePendapatan === 'bulanan' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600' }}">
                    Bulanan
                </button>
                <button
                    type="button"
                    wire:click="$set('modePendapatan', 'harian')"
                    class="rounded-lg py-1.5 text-xs font-semibold {{ $modePendapatan === 'harian' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600' }}">
                    Harian
                </button>
            </div>

            <div class="mt-2">
                @if ($modePendapatan === 'harian')
                    <label class="block text-xs font-medium text-gray-600">Tanggal</label>
                    <input type="date" wire:model.live="filterTanggal"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @else
                    <label class="block text-xs font-medium text-gray-600">Bulan</label>
                    <input type="month" wire:model.live="filterBulan"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @endif
            </div>
        </div>

        {{-- Table --}}
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <h2 class="font-bold text-gray-900">Riwayat Pendapatan</h2>
                <span class="text-sm font-bold text-emerald-600">{{ $this->formatRupiah($this->totalPendapatanFilter) }}</span>
            </div>

            @if (count($this->daftarPendapatan) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-[11px] uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2 font-semibold">No</th>
                                <th class="px-2 py-2 font-semibold">Tanggal</th>
                                <th class="px-2 py-2 font-semibold">Customer</th>
                                <th class="px-4 py-2 text-right font-semibold">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach ($this->daftarPendapatan as $index => $item)
                                <tr>
                                    <td class="px-4 py-2 text-xs text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-2 py-2 text-xs text-gray-600">{{ $item['tanggal'] }}</td>
                                    <td class="px-2 py-2">
                                        <p class="text-xs font-medium text-gray-800">{{ $item['customer'] }}</p>
                                        <p class="text-[10px] text-blue-500">Order #{{ $item['order_id'] }}</p>
                                    </td>
                                    <td class="px-4 py-2 text-right text-xs font-bold text-gray-900">{{ $this->formatRupiah($item['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="px-4 py-8 text-center">
                    <x-heroicon-o-inbox class="mx-auto h-12 w-12 text-gray-300" />
                    <p class="mt-2 text-sm text-gray-500">Belum ada pendapatan pada periode ini</p>
                </div>
            @endif
        </div>
    @endif
</div>
