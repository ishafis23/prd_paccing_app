<div class="overflow-x-auto">
    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-slate-100 dark:bg-slate-700">
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">Layanan</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">Kategori</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-white">Qty</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-white">Harga</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-white">Subtotal</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-center text-sm font-semibold text-slate-900 dark:text-white w-12">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($getState() as $index => $item)
                <tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-slate-900 dark:text-white">
                        <div class="font-medium">{{ $item->nama_layanan }}</div>
                        @if ($item->acUnit)
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $item->acUnit->labelTampil() }}</div>
                        @endif
                        @if ($item->catatan)
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $item->catatan }}</div>
                        @endif
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-slate-900 dark:text-white">
                        @if ($item->kategori)
                            <span class="inline-block px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">
                                {{ $item->kategori->value }}
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white font-medium">
                        {{ number_format($item->jumlah, 0, ',', '.') }}
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white">
                        Rp{{ number_format($item->harga, 0, ',', '.') }}
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white font-bold">
                        Rp{{ number_format($item->harga * $item->jumlah, 0, ',', '.') }}
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-center">
                        @if ($index > 0)
                            <div class="relative inline-block group">
                                <button class="p-1 rounded hover:bg-slate-200 dark:hover:bg-slate-700" title="Aksi">
                                    <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                    </svg>
                                </button>
                                <div class="hidden group-hover:block absolute right-0 mt-1 w-32 bg-white dark:bg-slate-700 rounded-lg shadow-lg z-10 border border-slate-200 dark:border-slate-600">
                                    <button class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600 rounded-t-lg">
                                        Edit
                                    </button>
                                    <button class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-b-lg">
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-center text-slate-500 dark:text-slate-400">
                        Tidak ada layanan
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-green-50 dark:bg-green-900/20 font-bold">
                <td colspan="4" class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white">
                    Total Keseluruhan:
                </td>
                <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-lg text-green-600 dark:text-green-400">
                    Rp{{ number_format(
                        collect($getState())->sum(fn ($item) => $item->harga * $item->jumlah),
                        0,
                        ',',
                        '.'
                    ) }}
                </td>
                <td class="border border-slate-300 dark:border-slate-600 px-4 py-3"></td>
            </tr>
        </tfoot>
    </table>
</div>
