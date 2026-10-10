<div class="space-y-6">
    <!-- Informasi Utama -->
    <div>
        <table class="w-full border-collapse">
            <tbody>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Customer</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">{{ $getState()->customer->nama }}</td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Alamat</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">
                        {{ $getState()->customerAddress?->nama_lokasi ?? '—' }}
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Layanan</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">
                        {{ $getState()->ringkasanLayanan() }}
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Teknisi</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">{{ $getState()->teknisi?->name ?? '— belum di-assign —' }}</td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Status</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">
                        {{ ucfirst(str_replace('_', ' ', $getState()->status->value)) }}
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Jenis Pelanggan</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">
                        @if ($getState()->jenis_pelanggan?->value === 'company')
                            Instansi
                        @elseif ($getState()->jenis_pelanggan?->value === 'perorangan')
                            Cust Umum
                        @else
                            —
                        @endif
                    </td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Tanggal Jadwal</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">{{ $getState()->tanggal_jadwal?->format('d M Y') ?? '—' }}</td>
                </tr>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Alamat Pengerjaan</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">{{ $getState()->alamat_pengerjaan ?? '—' }}</td>
                </tr>
                @if ($getState()->alasan_kendala)
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-red-50 dark:bg-red-900/20">
                        <td class="py-4 px-4 font-semibold text-red-700 dark:text-red-300 w-1/3 align-top">Alasan Kendala</td>
                        <td class="py-4 px-4 text-red-900 dark:text-red-200 align-top">{{ $getState()->alasan_kendala }}</td>
                    </tr>
                @endif
                @if ($getState()->perbaikan_menunggu_konfirmasi)
                    <tr class="border-b border-gray-200 dark:border-gray-700 bg-amber-50 dark:bg-amber-900/20">
                        <td class="py-4 px-4 font-semibold text-amber-700 dark:text-amber-300 w-1/3 align-top">Menunggu Konfirmasi</td>
                        <td class="py-4 px-4 text-amber-900 dark:text-amber-200 align-top">
                            {{ $getState()->perbaikan_catatan }}
                            @if ($getState()->perbaikan_estimasi_harga)
                                <span class="text-sm">(estimasi Rp{{ number_format($getState()->perbaikan_estimasi_harga, 0, ',', '.') }})</span>
                            @endif
                            — dilaporkan {{ $getState()->pelaporPerbaikan?->name ?? '—' }}
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Catatan Admin</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">{{ $getState()->catatan_admin ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Tim Teknisi -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
        <h3 class="font-semibold text-lg text-gray-900 dark:text-white mb-4">Tim Teknisi (B21)</h3>
        <table class="w-full border-collapse">
            <tbody>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Anggota Tim</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">
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
                    <td class="py-4 px-4 font-semibold text-gray-700 dark:text-gray-300 w-1/3 align-top">Di-assign lewat Tim</td>
                    <td class="py-4 px-4 text-gray-900 dark:text-white align-top">{{ $getState()->team?->nama ?? '— assign manual —' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
