<div class="space-y-2">
    @if ($items->isEmpty())
        <p class="text-sm text-gray-400">Belum ada riwayat pengerjaan utk unit ini.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-100 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="py-2 pr-3">Tanggal</th>
                        <th class="py-2 pr-3">Layanan</th>
                        <th class="py-2 pr-3">Status Order</th>
                        <th class="py-2 pr-3">Teknisi</th>
                        <th class="py-2 text-right">Harga</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($items as $item)
                        <tr>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ $item->order?->tanggal_jadwal?->format('d M Y') ?? '—' }}</td>
                            <td class="py-2 pr-3">{{ $item->nama_layanan }}</td>
                            <td class="py-2 pr-3"><x-teknisi-status-badge :status="$item->order?->status" /></td>
                            <td class="py-2 pr-3">{{ $item->order?->teknisi?->name ?? '—' }}</td>
                            <td class="py-2 text-right font-semibold">Rp{{ number_format((float) $item->harga, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
