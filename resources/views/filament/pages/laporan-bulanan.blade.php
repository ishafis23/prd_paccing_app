<x-filament-panels::page>
    @php
        $riwayat = $this->riwayat();
        $berjalan = $riwayat->contains(fn ($r) => $r->masihBerjalan());
        $warna = [
            'menunggu' => 'bg-gray-100 text-gray-700',
            'diproses' => 'bg-amber-100 text-amber-700',
            'selesai' => 'bg-emerald-100 text-emerald-700',
            'gagal' => 'bg-rose-100 text-rose-700',
        ];
    @endphp

    <div class="space-y-5" @if ($berjalan) wire:poll.5s @endif>
        <div class="space-y-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <p class="text-sm text-gray-500">
                Pilih customer dan bulan. PDF memuat semua kunjungan order pada bulan itu (urut tanggal), format sama dengan preview laporan per order.
            </p>

            <form wire:submit.prevent="buatAntrean">
                {{ $this->form }}

                <div class="mt-4 flex flex-wrap gap-3">
                    <x-filament::button type="submit" color="gray" icon="heroicon-o-queue-list">
                        Antrekan (butuh queue worker)
                    </x-filament::button>
                    <x-filament::button type="button" wire:click="buatSinkron" wire:loading.attr="disabled" icon="heroicon-o-bolt">
                        Buat sekarang (sinkron)
                    </x-filament::button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Bulan</th>
                        <th class="px-4 py-3">Cabang</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Dibuat</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($riwayat as $r)
                        <tr wire:key="lb-{{ $r->id }}">
                            <td class="px-4 py-3 font-semibold">{{ $r->customer?->nama ?? '—' }}</td>
                            <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $r->bulan)->locale('id')->translatedFormat('F Y') }}</td>
                            <td class="px-4 py-3">{{ $r->alamat?->nama_lokasi ?? 'Semua cabang' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $warna[$r->status] ?? '' }}">{{ \App\Models\LaporanBulanan::STATUS[$r->status] ?? $r->status }}</span>
                                @if ($r->status === 'selesai')
                                    <span class="ml-1 text-xs text-gray-500">{{ $r->jumlah_order }} order · {{ \App\Services\StorageQuotaService::formatBytes((int) $r->ukuran_bytes) }}</span>
                                @endif
                                @if ($r->status === 'gagal' && $r->pesan_error)
                                    <p class="mt-1 max-w-md whitespace-pre-line break-words text-xs text-rose-700">{{ $r->pesan_error }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $r->created_at?->format('d/m/Y H:i') }}<br>{{ $r->pembuat?->name }}</td>
                            <td class="space-x-3 whitespace-nowrap px-4 py-3 text-right">
                                @if ($r->selesai())
                                    <a class="font-semibold text-primary-600 hover:underline" href="{{ \App\Support\Url::absolute('laporan.bulanan.unduh', ['laporan' => $r->id]) }}">Unduh PDF</a>
                                @endif
                                @unless ($r->status === 'diproses')
                                    <button type="button" class="text-rose-600 hover:underline" wire:click="hapus({{ $r->id }})" wire:confirm="Hapus laporan ini beserta filenya?">Hapus</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">Belum ada laporan bulanan dibuat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
