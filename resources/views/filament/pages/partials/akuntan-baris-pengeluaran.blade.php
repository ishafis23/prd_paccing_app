<tr class="hover:bg-gray-50/60">
    <td class="whitespace-nowrap px-4 py-3">{{ $tgl($r['tanggal']) }}</td>
    <td class="px-4 py-3">{{ $r['sumber'] }}<span class="block text-xs text-gray-400">{{ $r['pelaku'] }}</span></td>
    <td class="px-4 py-3">{{ ucfirst($r['kategori']) }}</td>
    <td class="px-4 py-3">
        {{ $r['keterangan'] ?: '-' }}
        @if ($r['qty'] && $r['harga'])
            <span class="block text-xs text-gray-400">{{ $r['qty'] }} × {{ $rp($r['harga']) }}</span>
        @endif
    </td>
    <td class="px-4 py-3">
        @if ($r['order_id'])
            <a class="text-primary-600 hover:underline" href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $r['order_id']]) }}">#{{ $r['order_id'] }}</a>
        @else
            -
        @endif
    </td>
    <td class="px-4 py-3">
        @if ($r['status'] === 'pending')
            <span class="rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-700">pending</span>
        @else
            <span class="text-xs text-gray-500">{{ $r['status'] }}</span>
        @endif
    </td>
    <td class="px-4 py-3 text-right font-semibold {{ $r['dihitung'] ? '' : 'text-gray-400' }}">{{ $rp($r['nominal']) }}</td>
</tr>
