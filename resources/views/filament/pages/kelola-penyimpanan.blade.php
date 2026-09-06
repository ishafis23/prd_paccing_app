<x-filament-panels::page>
    <div class="space-y-5">
        {{-- ═══ Ringkasan kuota ═══ --}}
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-gray-950">Penyimpanan Foto & Gambar</h2>
                    @if ($penuh)
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold text-white" style="background:#dc2626;">PENUH</span>
                    @elseif ($peringatan)
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" style="background:#fffbeb;color:#b45309;">HAMPIR PENUH</span>
                    @endif
                </div>
                <span class="text-sm font-semibold" style="color:{{ $penuh ? '#dc2626' : ($peringatan ? '#d97706' : '#16a34a') }};">
                    {{ number_format($persen, 0, ',', '.') }}%
                </span>
            </div>

            <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full" style="background:#e5e7eb;">
                <div class="h-full rounded-full transition-all" style="width:{{ $persen }}%;background:{{ $penuh ? '#dc2626' : ($peringatan ? '#d97706' : '#16a34a') }};"></div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:grid-cols-4">
                <div>
                    <p class="text-gray-400">Terpakai</p>
                    <p class="font-semibold text-gray-700">{{ $pakaiLabel }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Sisa kuota</p>
                    <p class="font-semibold text-gray-700">{{ $sisaLabel }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Total file terkelola</p>
                    <p class="font-semibold text-gray-700">{{ number_format($total, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-gray-400">Umur maks foto</p>
                    <p class="font-semibold text-gray-700">{{ $maxUmurHari }} hari</p>
                </div>
            </div>

            @if ($penuh)
                <p class="mt-3 rounded-lg px-3 py-2 text-xs font-medium" style="background:#fef2f2;color:#b91c1c;">
                    Penyimpanan penuh — unggahan foto baru diblokir sampai kuota dinaikkan atau file dibersihkan.
                </p>
            @elseif ($peringatan)
                <p class="mt-3 rounded-lg px-3 py-2 text-xs font-medium" style="background:#fffbeb;color:#b45309;">
                    Penyimpanan hampir penuh. Hapus file yang tidak diperlukan di bawah, atau naikkan FOTO_KUOTA_MB di .env.
                </p>
            @endif

            <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3">
                <p class="text-[11px] text-gray-400">
                    Foto pengerjaan berumur lebih dari {{ $maxUmurHari }} hari dibersihkan otomatis tiap 03:00 (riwayat laporan tetap utuh). QRIS/logo tidak dihapus otomatis.
                </p>
                @if ($bisaHapus)
                    <button type="button" wire:click="bersihkanSekarang" wire:confirm="Jalankan pembersihan foto lama (> {{ $maxUmurHari }} hari) sekarang?"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200">
                        <x-heroicon-m-sparkles class="h-4 w-4" />
                        Bersihkan foto lama sekarang
                    </button>
                @endif
            </div>
        </div>

        {{-- ═══ Pencarian & filter ═══ --}}
        <div class="grid gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 sm:grid-cols-12">
            <div class="sm:col-span-5">
                <label class="mb-1 block text-xs font-medium text-gray-500">Cari file / label</label>
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Nama file, nomor order, nama customer…"
                       class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-amber-500" />
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-500">Folder</label>
                <select wire:model.live="filterFolder"
                        class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-amber-500">
                    <option value="">Semua folder</option>
                    @foreach ($filterFolderOptions as $nilai => $nama)
                        <option value="{{ $nilai }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3">
                <label class="mb-1 block text-xs font-medium text-gray-500">Status</label>
                <select wire:model.live="filterStatus"
                        class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-amber-500">
                    <option value="">Semua status</option>
                    @foreach ($filterStatusOptions as $nilai => $nama)
                        <option value="{{ $nilai }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-medium text-gray-500">Urutkan</label>
                <select wire:model.live="urut"
                        class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-amber-500">
                    @foreach ($urutOptions as $nilai => $nama)
                        <option value="{{ $nilai }}">{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- ═══ Daftar file ═══ --}}
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
            @if (count($files) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-xs text-gray-400">
                                @if ($bisaHapus)
                                    <th class="w-10 px-4 py-3">
                                        <input type="checkbox" wire:change="pilihHalamanIni($event.target.checked)"
                                               title="Pilih semua di halaman ini"
                                               class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                                    </th>
                                @endif
                                <th class="w-14 px-2 py-3"></th>
                                <th class="px-3 py-3 font-medium">File</th>
                                <th class="px-3 py-3 font-medium">Folder</th>
                                <th class="px-3 py-3 font-medium">Ukuran</th>
                                <th class="px-3 py-3 font-medium">Waktu</th>
                                @if ($bisaHapus)
                                    <th class="w-16 px-4 py-3"></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($files as $f)
                                <tr class="border-b border-gray-50 transition hover:bg-gray-50/60" wire:key="{{ $f['path'] }}">
                                    @if ($bisaHapus)
                                        <td class="px-4 py-2.5">
                                            <input type="checkbox" wire:model="terpilih" value="{{ $f['path'] }}"
                                                   class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                                        </td>
                                    @endif
                                    <td class="px-2 py-2.5">
                                        @if (str_contains('.jpg.jpeg.png.webp.gif', strtolower(pathinfo($f['nama'], PATHINFO_EXTENSION))))
                                            <button type="button" wire:click="$set('pratinjau', {{ \Illuminate\Support\Js::from($f['path']) }})" title="Lihat besar">
                                                <img src="{{ $this->urlFile($f['path']) }}" alt="{{ $f['nama'] }}"
                                                     class="h-10 w-10 rounded-lg object-cover ring-1 ring-gray-200 transition hover:ring-amber-400" loading="lazy" />
                                            </button>
                                        @else
                                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-400">
                                                <x-heroicon-o-document class="h-5 w-5" />
                                            </span>
                                        @endif
                                    </td>
                                    <td class="max-w-md px-3 py-2.5">
                                        <p class="truncate font-medium text-gray-800" title="{{ $f['path'] }}">{{ $f['nama'] }}</p>
                                        <p class="truncate text-xs text-gray-400">{{ $f['label'] }}</p>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600">
                                            {{ $f['folder'] }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-xs text-gray-600">
                                        {{ \App\Services\StorageQuotaService::formatBytes($f['ukuran']) }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-xs text-gray-500" title="{{ $f['mtime']?->format('d/m/Y H:i') }}">
                                        {{ $f['mtime']?->diffForHumans() ?? '—' }}
                                    </td>
                                    @if ($bisaHapus)
                                        <td class="px-4 py-2.5 text-right">
                                            <button type="button" wire:click="hapusFile({{ \Illuminate\Support\Js::from($f['path']) }})"
                                                    wire:confirm="Hapus file ini? File fisik dibuang & foto terkait dikosongkan (riwayat order tetap ada)."
                                                    class="inline-flex items-center rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600" title="Hapus file">
                                                <x-heroicon-o-trash class="h-4 w-4" />
                                            </button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Bar aksi massal --}}
                @if ($bisaHapus && count($terpilih) > 0)
                    <div class="flex items-center justify-between gap-3 border-t border-amber-100 bg-amber-50/70 px-4 py-2.5 text-sm">
                        <span class="text-xs font-medium text-amber-800">{{ count($terpilih) }} file dipilih</span>
                        <button type="button" wire:click="hapusMassal"
                                wire:confirm="Hapus {{ count($terpilih) }} file yang dipilih? Riwayat order tetap utuh."
                                class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700">
                            <x-heroicon-m-trash class="h-4 w-4" />
                            Hapus yang dipilih
                        </button>
                    </div>
                @endif

                {{-- Pagination --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3">
                    <p class="text-xs text-gray-400">
                        {{ number_format($total, 0, ',', '.') }} file — halaman {{ $halamanAktif }} dari {{ number_format($jumlahHalaman, 0, ',', '.') }}
                    </p>
                    <div class="flex items-center gap-1">
                        <button type="button" wire:click="setHalaman({{ max(1, $halamanAktif - 1) }})" @disabled($halamanAktif <= 1)
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-100 disabled:opacity-40">
                            &laquo; Sebelumnya
                        </button>
                        <button type="button" wire:click="setHalaman({{ min($jumlahHalaman, $halamanAktif + 1) }})" @disabled($halamanAktif >= $jumlahHalaman)
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-100 disabled:opacity-40">
                            Berikutnya &raquo;
                        </button>
                    </div>
                </div>
            @else
                <div class="flex flex-col items-center gap-2 px-6 py-14 text-center">
                    <x-heroicon-o-photo class="h-10 w-10 text-gray-300" />
                    <p class="text-sm font-medium text-gray-500">Tidak ada file ditemukan</p>
                    <p class="max-w-sm text-xs text-gray-400">
                        @if ($total === 0)
                            Folder penyimpanan masih kosong. File muncul di sini begitu teknisi melampirkan foto laporan atau admin mengunggah gambar QRIS.
                        @else
                            Coba ubah kata kunci pencarian atau filter.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>

    {{-- ═══ Modal pratinjau ═══ --}}
    @if ($pratinjau !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4" wire:click="$set('pratinjau', null)">
            <div class="max-h-full w-full max-w-2xl overflow-auto rounded-2xl bg-white p-4 shadow-2xl" wire:click.stop>
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="truncate text-sm font-semibold text-gray-800">{{ basename($pratinjau) }}</p>
                    <button type="button" wire:click="$set('pratinjau', null)" class="rounded-lg p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
                        <x-heroicon-m-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <img src="{{ $this->urlFile($pratinjau) }}" alt="{{ basename($pratinjau) }}" class="mx-auto max-h-[70vh] rounded-xl object-contain" />
                @if ($bisaHapus)
                    <div class="mt-3 flex justify-end">
                        <button type="button" wire:click="hapusFile({{ \Illuminate\Support\Js::from($pratinjau) }})" wire:confirm="Hapus file ini?"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-red-700">
                            <x-heroicon-m-trash class="h-4 w-4" />
                            Hapus file
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</x-filament-panels::page>
