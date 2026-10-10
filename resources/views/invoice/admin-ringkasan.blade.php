@php
    /** @var \App\Models\Invoice $invoice */
    $invoice = $getState();
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left text-xs uppercase text-gray-500">
                <th class="py-2 pr-2">#</th>
                <th class="py-2 pr-2">Item</th>
                <th class="py-2 pr-2">Deskripsi</th>
                <th class="py-2 pr-2 text-right">Jml</th>
                <th class="py-2 pr-2 text-right">Tarif</th>
                <th class="py-2 text-right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $i => $b)
                <tr class="border-b align-top">
                    <td class="py-2 pr-2">{{ $i + 1 }}</td>
                    <td class="py-2 pr-2">{{ $b->nama }}</td>
                    <td class="py-2 pr-2">{!! nl2br(e((string) $b->deskripsi)) !!}</td>
                    <td class="py-2 pr-2 text-right">{{ number_format((float) $b->jumlah, 2, '.', ',') }}</td>
                    <td class="py-2 pr-2 text-right">{{ number_format((float) $b->harga, 2, '.', ',') }}</td>
                    <td class="py-2 text-right">{{ number_format((float) $b->subtotal, 2, '.', ',') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="5" class="py-2 pr-2 text-right font-semibold">Total</td><td class="py-2 text-right font-semibold">{{ number_format((float) $invoice->total, 2, '.', ',') }}</td></tr>
        </tfoot>
    </table>
</div>
