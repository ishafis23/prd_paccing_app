<div class="space-y-4 p-4">
    <a href="{{ route('portal.invoice') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-blue-600">
        <x-heroicon-o-arrow-left class="h-3.5 w-3.5" /> Kembali ke Invoice
    </a>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <div class="flex items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-gray-900">{{ $tampilan['nomor'] }}</h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Tanggal {{ $tampilan['tanggal']?->translatedFormat('d M Y') }}
                    @if ($tampilan['jatuh_tempo']) · Jatuh tempo {{ $tampilan['jatuh_tempo']->translatedFormat('d M Y') }} @endif
                </p>
                @if ($tampilan['ketentuan'])
                    <p class="text-xs text-gray-400">{{ $tampilan['ketentuan'] }}</p>
                @endif
            </div>
            <span @class([
                'shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                'bg-green-100 text-green-700' => $tampilan['status'] === \App\Enums\InvoiceStatus::Lunas,
                'bg-amber-100 text-amber-700' => $tampilan['status'] === \App\Enums\InvoiceStatus::Terkirim,
            ])>{{ $tampilan['status'] === \App\Enums\InvoiceStatus::Lunas ? 'Lunas' : 'Belum lunas' }}</span>
        </div>

        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
            <a href="{{ route('portal.invoice.pdf', $invoice) }}"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm active:bg-blue-700">
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> Unduh PDF
            </a>
            <a href="{{ route('portal.invoice.pdf', $invoice) }}?lampiran=1"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 active:bg-gray-200">
                <x-heroicon-o-document-arrow-down class="h-4 w-4" /> PDF + Laporan
            </a>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-2 font-semibold">Item</th>
                    <th class="px-2 py-2 text-right font-semibold">Jml</th>
                    <th class="px-4 py-2 text-right font-semibold">Subtotal</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($tampilan['baris'] as $baris)
                    <tr>
                        <td class="px-4 py-2.5">
                            <p class="font-medium text-gray-800">{{ $baris->nama }}</p>
                            @if ($baris->deskripsi)
                                <p class="text-[11px] text-gray-400">{!! nl2br(e($baris->deskripsi)) !!}</p>
                            @endif
                        </td>
                        <td class="px-2 py-2.5 text-right text-gray-600">{{ rtrim(rtrim(number_format((float) $baris->jumlah, 2, ',', '.'), '0'), ',') }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-700">Rp {{ number_format((float) $baris->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 text-sm">
                <tr>
                    <td colspan="2" class="px-4 py-2 text-right text-gray-500">Total</td>
                    <td class="px-4 py-2 text-right font-bold text-gray-800">Rp {{ number_format($tampilan['total'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="px-4 py-2 text-right text-gray-500">Sudah dibayar</td>
                    <td class="px-4 py-2 text-right text-gray-700">Rp {{ number_format($tampilan['dibayar'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="px-4 py-2 text-right font-semibold text-gray-600">Saldo</td>
                    <td class="px-4 py-2 text-right font-extrabold {{ $tampilan['saldo'] > 0 ? 'text-red-600' : 'text-green-600' }}">Rp {{ number_format($tampilan['saldo'], 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    @if ($tampilan['bank']['nama'] || $tampilan['bank']['rekening'])
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Instruksi Transfer</p>
            <p class="mt-1 text-sm text-gray-700">{{ $tampilan['bank']['nama'] }}</p>
            <p class="text-sm font-bold text-gray-900">{{ $tampilan['bank']['rekening'] }}</p>
            @if ($tampilan['bank']['atas_nama'])
                <p class="text-xs text-gray-500">a.n. {{ $tampilan['bank']['atas_nama'] }}</p>
            @endif
        </div>
    @endif

    @if ($orders->isNotEmpty())
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Order yang Ditagih</p>
            <div class="space-y-1.5">
                @foreach ($orders as $order)
                    <a href="{{ route('portal.pekerjaan.detail', $order) }}"
                        class="flex items-center justify-between rounded-xl bg-gray-50 px-3 py-2 text-sm active:bg-gray-100">
                        <span class="text-gray-700">{{ $order->tanggal_jadwal?->translatedFormat('d M Y') ?? '—' }} · {{ $order->ringkasanLayanan() }}</span>
                        <x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 text-gray-400" />
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($tampilan['catatan'])
        <div class="rounded-2xl bg-white p-4 text-sm text-gray-600 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Catatan</p>
            <p class="mt-1">{!! nl2br(e($tampilan['catatan'])) !!}</p>
        </div>
    @endif
</div>
