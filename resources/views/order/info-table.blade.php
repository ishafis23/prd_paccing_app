<div class="space-y-6">
    <!-- Informasi Utama -->
    <div>
        <table class="w-full">
            <tbody>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Customer</td>
                    <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $getState()->customer->nama }}</td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Alamat</td>
                    <td class="py-3 px-4">
                        @if ($getState()->customerAddress?->nama_lokasi)
                            <span class="inline-block px-3 py-1 text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">
                                {{ $getState()->customerAddress->nama_lokasi }}
                            </span>
                        @else
                            <span class="text-gray-500 dark:text-gray-400">—</span>
                        @endif
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Layanan</td>
                    <td class="py-3 px-4">
                        <span class="inline-block px-3 py-1 text-sm font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded">
                            {{ $getState()->serviceCatalog?->jenis_layanan->value ?? '—' }}
                        </span>
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Teknisi</td>
                    <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $getState()->teknisi?->name ?? '— belum di-assign —' }}</td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Status</td>
                    <td class="py-3 px-4">
                        <span class="inline-block px-3 py-1 text-sm font-medium rounded {{ $getState()->status->value === 'selesai' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : ($getState()->status->value === 'batal' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200') }}">
                            {{ ucfirst(str_replace('_', ' ', $getState()->status->value)) }}
                        </span>
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Jenis Pelanggan</td>
                    <td class="py-3 px-4">
                        <span class="inline-block px-3 py-1 text-sm font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 rounded">
                            @if ($getState()->jenis_pelanggan?->value === 'company')
                                Instansi
                            @elseif ($getState()->jenis_pelanggan?->value === 'perorangan')
                                Cust Umum
                            @else
                                —
                            @endif
                        </span>
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Tanggal Jadwal</td>
                    <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $getState()->tanggal_jadwal?->format('d M Y') ?? '—' }}</td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Alamat Pengerjaan</td>
                    <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $getState()->alamat_pengerjaan ?? '—' }}</td>
                </tr>
                @if ($getState()->alasan_kendala)
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
                        <td class="py-3 px-4 font-semibold text-red-700 dark:text-red-300 w-1/3">Alasan Kendala</td>
                        <td class="py-3 px-4 text-red-900 dark:text-red-200">{{ $getState()->alasan_kendala }}</td>
                    </tr>
                @endif
                @if ($getState()->perbaikan_menunggu_konfirmasi)
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20">
                        <td class="py-3 px-4 font-semibold text-amber-700 dark:text-amber-300 w-1/3">Menunggu Konfirmasi</td>
                        <td class="py-3 px-4 text-amber-900 dark:text-amber-200">
                            {{ $getState()->perbaikan_catatan }}
                            @if ($getState()->perbaikan_estimasi_harga)
                                <span class="text-sm">(estimasi Rp{{ number_format($getState()->perbaikan_estimasi_harga, 0, ',', '.') }})</span>
                            @endif
                            — dilaporkan {{ $getState()->pelaporPerbaikan?->name ?? '—' }}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Catatan Admin</td>
                    <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $getState()->catatan_admin ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Tim Teknisi -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-4">Tim Teknisi (B21)</h3>
        <table class="w-full">
            <tbody>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Anggota Tim</td>
                    <td class="py-3 px-4 text-gray-900 dark:text-white">
                        @php
                            $tim = $getState()->timTeknisi
                                ->map(fn ($u) => (int) $u->id === (int) $getState()->teknisi_id
                                    ? $u->name . ' (PIC)'
                                    : $u->name);

                            if ($tim->isEmpty() && $getState()->teknisi !== null) {
                                $tim = collect([$getState()->teknisi->name . ' (PIC)']);
                            }
                        @endphp
                        {{ $tim->isEmpty() ? '— belum di-assign —' : $tim->implode(', ') }}
                    </td>
                </tr>
                <tr>
                    <td class="py-3 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3">Di-assign lewat Tim</td>
                    <td class="py-3 px-4 text-gray-900 dark:text-white">{{ $getState()->team?->nama ?? '— assign manual —' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
