<x-filament-panels::page>
    @php
        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->translatedFormat('d M Y');
        $th = 'px-4 py-3';
    @endphp

    <div class="space-y-5">
        {{-- Filter bulan --}}
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <label class="text-sm font-semibold text-gray-600" for="akuntan-bulan">Bulan</label>
            <input id="akuntan-bulan" type="month" wire:model.live="bulan"
                class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
            <span class="text-sm font-semibold text-gray-600">{{ $bulanLabel }}</span>

            <a href="{{ \App\Support\Url::absolute('filament.admin.pages.pengeluaran-teknisi') }}"
                class="ml-auto inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                <x-heroicon-o-banknotes class="h-4 w-4" />
                ACC Pengeluaran Teknisi
            </a>
        </div>

        {{-- Kartu ringkasan --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pendapatan Hari Ini</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $rp($kartu['pendapatan_hari']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pendapatan {{ $bulanLabel }}</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $rp($kartu['pendapatan_bulan']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pengeluaran Hari Ini</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ $rp($kartu['pengeluaran_hari']) }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pengeluaran {{ $bulanLabel }}</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ $rp($kartu['pengeluaran_bulan']) }}</p>
                @if ($kartu['pengeluaran_pending_bulan'] > 0)
                    <p class="mt-1 text-xs text-amber-600">+ {{ $rp($kartu['pengeluaran_pending_bulan']) }} menunggu approval (belum dihitung)</p>
                @endif
            </div>
        </div>

        {{-- Tab --}}
        <div class="flex gap-1 rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-950/5">
            @foreach (['pendapatan' => 'Pendapatan Harian', 'pengeluaran' => 'Pengeluaran Harian', 'semua' => 'Semua Transaksi'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                    class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold {{ $tab === $key ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Tab 1 & 2: rekap harian --}}
        @if ($tab === 'pendapatan' || $tab === 'pengeluaran')
            @php $rekap = $tab === 'pendapatan' ? $rekapPendapatan : $rekapPengeluaran; @endphp
            <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                @if ($rekap->isEmpty())
                    <p class="p-6 text-center text-sm text-gray-400">Belum ada data pada bulan ini.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="{{ $th }}">Tanggal</th>
                                    <th class="{{ $th }} text-right">{{ $tab === 'pendapatan' ? 'Jumlah Order' : 'Jumlah Transaksi' }}</th>
                                    <th class="{{ $th }} text-right">{{ $tab === 'pendapatan' ? 'Total Pendapatan' : 'Total Pengeluaran' }}</th>
                                    <th class="{{ $th }}"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($rekap as $r)
                                    <tr class="hover:bg-gray-50/60">
                                        <td class="px-4 py-3 font-medium text-gray-700">{{ $tgl($r['tanggal']) }}</td>
                                        <td class="px-4 py-3 text-right">{{ $r['jumlah'] }}</td>
                                        <td class="px-4 py-3 text-right font-semibold">
                                            {{ $rp($r['total']) }}
                                            @if ($r['pending'] > 0)
                                                <span class="block text-xs font-normal text-amber-600">+ {{ $rp($r['pending']) }} pending</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" wire:click="bukaDetail('{{ $r['tanggal'] }}')"
                                                class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 hover:bg-blue-100">
                                                Lihat Detail
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif

        {{-- Tab 3: semua transaksi --}}
        @if ($tab === 'semua')
            <div class="space-y-3">
                <div class="flex gap-2">
                    @foreach (['pendapatan' => 'Pendapatan', 'pengeluaran' => 'Pengeluaran'] as $key => $label)
                        <button type="button" wire:click="setSemua('{{ $key }}')"
                            class="rounded-full px-3 py-1 text-xs font-bold ring-1 {{ $semua === $key ? 'bg-gray-800 text-white ring-gray-800' : 'bg-white text-gray-600 ring-gray-200' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                    <span class="ml-auto text-xs font-medium text-gray-400">{{ $totalDaftar }} data</span>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
                    @if ($daftar->isEmpty())
                        <p class="p-6 text-center text-sm text-gray-400">Belum ada data pada bulan ini.</p>
                    @else
                        <div class="overflow-x-auto">
                            @if ($semua === 'pendapatan')
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="{{ $th }}">Tanggal</th>
                                            <th class="{{ $th }}">Order</th>
                                            <th class="{{ $th }}">Customer</th>
                                            <th class="{{ $th }}">Layanan</th>
                                            <th class="{{ $th }} text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($daftar as $r)
                                            <tr class="hover:bg-gray-50/60">
                                                <td class="whitespace-nowrap px-4 py-3">{{ $tgl($r['tanggal']) }}</td>
                                                <td class="px-4 py-3">
                                                    <a class="text-primary-600 hover:underline" href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $r['order_id']]) }}">#{{ $r['order_id'] }}</a>
                                                </td>
                                                <td class="px-4 py-3">{{ $r['customer'] }}</td>
                                                <td class="px-4 py-3">
                                                    {{ $r['layanan'] }}
                                                    @if (count($r['items']) > 1)
                                                        <span class="text-xs text-gray-400">(+{{ count($r['items']) - 1 }} tambahan)</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right font-semibold">{{ $rp($r['total']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <table class="w-full text-left text-sm">
                                    <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="{{ $th }}">Tanggal</th>
                                            <th class="{{ $th }}">Sumber</th>
                                            <th class="{{ $th }}">Kategori</th>
                                            <th class="{{ $th }}">Keterangan</th>
                                            <th class="{{ $th }}">Order</th>
                                            <th class="{{ $th }}">Status</th>
                                            <th class="{{ $th }} text-right">Nominal</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($daftar as $r)
                                            @include('filament.pages.partials.akuntan-baris-pengeluaran', ['r' => $r, 'rp' => $rp, 'tgl' => $tgl])
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>

                        @if ($totalHalaman > 1)
                            <div class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-sm">
                                <button type="button" wire:click="halamanSebelumnya" @disabled($halaman <= 1)
                                    class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 disabled:opacity-40">Sebelumnya</button>
                                <span class="text-gray-500">Halaman {{ $halaman }} / {{ $totalHalaman }}</span>
                                <button type="button" wire:click="halamanBerikutnya" @disabled($halaman >= $totalHalaman)
                                    class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 disabled:opacity-40">Berikutnya</button>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Modal detail tanggal --}}
    @if ($detailTanggal !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="tutupDetail">
            <div class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-800">
                        {{ $tab === 'pendapatan' ? 'Detail Pendapatan' : 'Detail Pengeluaran' }} — {{ $tgl($detailTanggal) }}
                    </h3>
                    <button type="button" wire:click="tutupDetail" class="rounded-lg px-2 py-1 text-gray-500 hover:bg-gray-100" aria-label="Tutup">✕</button>
                </div>

                @if ($tab === 'pendapatan')
                    <div class="space-y-3">
                        @forelse ($detailPendapatan as $o)
                            <div class="rounded-lg ring-1 ring-gray-200">
                                <div class="flex flex-wrap items-center justify-between gap-2 bg-gray-50 px-3 py-2 text-sm">
                                    <span class="font-semibold">
                                        <a class="text-primary-600 hover:underline" href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $o['order_id']]) }}">#{{ $o['order_id'] }}</a>
                                        · {{ $o['customer'] }}
                                    </span>
                                    <span class="font-bold">{{ $rp($o['total']) }}</span>
                                </div>
                                <table class="w-full text-left text-xs">
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach ($o['items'] as $item)
                                            <tr>
                                                <td class="px-3 py-1.5">
                                                    {{ $item['nama'] }}
                                                    @if ($item['tambahan'])
                                                        <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 font-semibold text-amber-700">tambahan</span>
                                                    @endif
                                                    @if ($item['catatan'])
                                                        <span class="block text-gray-400">{{ $item['catatan'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-1.5 text-right text-gray-500">{{ $item['jumlah'] }} × {{ $rp($item['harga']) }}</td>
                                                <td class="px-3 py-1.5 text-right font-medium">{{ $rp($item['subtotal']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @empty
                            <p class="text-center text-sm text-gray-400">Tidak ada data.</p>
                        @endforelse
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="{{ $th }}">Tanggal</th>
                                    <th class="{{ $th }}">Sumber</th>
                                    <th class="{{ $th }}">Kategori</th>
                                    <th class="{{ $th }}">Keterangan</th>
                                    <th class="{{ $th }}">Order</th>
                                    <th class="{{ $th }}">Status</th>
                                    <th class="{{ $th }} text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($detailPengeluaran as $r)
                                    @include('filament.pages.partials.akuntan-baris-pengeluaran', ['r' => $r, 'rp' => $rp, 'tgl' => $tgl])
                                @empty
                                    <tr><td colspan="7" class="p-4 text-center text-gray-400">Tidak ada data.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
