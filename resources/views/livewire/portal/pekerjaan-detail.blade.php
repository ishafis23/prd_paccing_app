@php
    $svc = app(\App\Services\LaporanPengerjaanService::class);
@endphp
<div class="space-y-4 p-4">
    <a href="{{ route('portal.pekerjaan') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600">
        <x-heroicon-o-arrow-left class="h-3.5 w-3.5" /> Kembali ke Pekerjaan
    </a>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-gray-900">
                    {{ $order->tanggal_jadwal?->translatedFormat('d F Y') ?? '—' }}
                </h2>
                <p class="mt-0.5 text-sm text-gray-500">
                    {{ $order->customerAddress?->nama_lokasi ?? $order->alamat_pengerjaan ?? '—' }}
                </p>
                <p class="mt-1 text-xs text-gray-400">
                    Teknisi: {{ $order->teknisi?->name ?? ($order->timTeknisi->pluck('name')->implode(', ') ?: '—') }}
                    · {{ str($order->status->value)->headline() }}
                </p>
            </div>
            @if ($invoice)
                <a href="{{ route('portal.invoice.detail', $invoice) }}"
                    class="shrink-0 rounded-full bg-gray-100 px-3 py-1.5 text-[11px] font-bold text-gray-700">
                    {{ $invoice->nomor }}
                </a>
            @endif
        </div>

        <div class="mt-4 flex gap-2">
            <a href="{{ route('portal.laporan.pdf', $order) }}"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm active:bg-blue-700">
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Unduh Laporan PDF
            </a>
        </div>
    </div>

    @if (empty($dokumen['unit']))
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
            <p class="text-sm text-gray-400">Laporan rinci tidak tersedia untuk order ini.</p>
        </div>
    @else
        @foreach ($dokumen['unit'] as $unit)
            <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                <p class="text-xs font-bold uppercase tracking-wide text-gray-700">{{ $unit['caption'] }}</p>
                @if (! empty($unit['catatan']))
                    <p class="mt-1 text-xs text-red-600">Catatan: {{ $unit['catatan'] }}</p>
                @endif

                <div class="mt-3 grid grid-cols-2 gap-2">
                    @foreach ($unit['foto'] as $foto)
                        @php $src = $foto['ada'] ? $svc->dataUri($foto['file']) : null; @endphp
                        <div class="overflow-hidden rounded-xl ring-1 ring-gray-200">
                            @if ($src)
                                <img src="{{ $src }}" alt="{{ $foto['label'] ?? 'Foto' }}" class="h-40 w-full object-cover">
                            @else
                                <div class="flex h-40 w-full items-center justify-center bg-gray-50 text-[11px] text-gray-400">Foto belum diunggah</div>
                            @endif
                            @if (! empty($foto['label']))
                                <p class="px-2 py-1 text-[10px] font-semibold uppercase text-gray-500">{{ $foto['label'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>
