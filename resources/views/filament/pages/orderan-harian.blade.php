<x-filament-panels::page>
    <div class="space-y-5">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-wrap items-center gap-3">
                <input type="date" wire:model.live="tanggal"
                    class="rounded-lg border-gray-300 text-sm focus:border-primary-500 focus:ring-primary-500">
                @unless ($isHariIni)
                    <button type="button" wire:click="hariIni"
                        class="rounded-lg bg-blue-50 px-3 py-1.5 text-xs font-bold text-blue-700 active:bg-blue-100">
                        Hari Ini
                    </button>
                @endunless
                <span class="text-sm font-semibold text-gray-600">
                    {{ \Illuminate\Support\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                </span>
                <span class="ml-auto text-xs font-medium text-gray-400">{{ $orders->count() }} order</span>
            </div>

            @if ($orders->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($ringkasan as $status => $jumlah)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-600 ring-1 ring-gray-200">
                            {{ ucwords(str_replace('_', ' ', $status)) }}: {{ $jumlah }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            @if ($orders->isEmpty())
                <p class="p-6 text-center text-sm text-gray-400">Tidak ada order dijadwalkan pada tanggal ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-100 bg-gray-50/60 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Jam</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Layanan</th>
                                <th class="px-4 py-3">Teknisi</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Jenis</th>
                                <th class="px-4 py-3">Laporan</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($orders as $order)
                                @php
                                    $tim = $order->timTeknisi->pluck('name');
                                    if ($tim->isEmpty() && $order->teknisi) {
                                        $tim = collect([$order->teknisi->name]);
                                    }
                                    $laporan = $order->workReports->sortByDesc('id')->first();
                                    $jenisPelanggan = $order->jenis_pelanggan?->value === 'company' ? 'Instansi' : 'Cust Umum';
                                @endphp
                                <tr class="hover:bg-gray-50/60">
                                    <td class="whitespace-nowrap px-4 py-3 font-medium text-gray-700">
                                        {{ $order->jam_jadwal ? \Illuminate\Support\Carbon::parse($order->jam_jadwal)->format('H:i') : '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <p class="font-semibold text-gray-900">{{ $order->customer?->nama }}</p>
                                        <p class="max-w-[220px] truncate text-xs text-gray-400">{{ $order->alamat_pengerjaan }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600">{{ $order->serviceCatalog?->jenis_layanan?->value }}</td>
                                    <td class="px-4 py-3 text-gray-600">
                                        {{ $tim->isNotEmpty() ? $tim->join(', ') : '— belum di-assign —' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <x-teknisi-status-badge :status="$order->status" />
                                        @if ($order->perbaikan_menunggu_konfirmasi)
                                            <span title="{{ $order->perbaikan_catatan }}"
                                                class="ml-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700">
                                                Ada Perbaikan
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ $jenisPelanggan }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($laporan === null)
                                            <span class="text-xs text-gray-400">—</span>
                                        @elseif ($laporan->sudahDiverifikasi())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-700">Terverifikasi</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-700">Menunggu</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-gray-900">
                                        Rp{{ number_format($order->total(), 0, ',', '.') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <a href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $order]) }}"
                                            class="text-xs font-bold text-blue-600 hover:underline">
                                            Lihat
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
