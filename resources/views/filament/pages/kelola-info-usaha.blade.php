<x-filament-panels::page>
    <div class="space-y-5">
        {{-- Pengantar --}}
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <p class="text-sm text-gray-600">
                Nama usaha, logo, dan kontak di sini otomatis dipakai di <b>sidebar &amp; login admin</b>,
                <b>judul tab browser</b>, portal teknisi, landing page, dan kop resi.
            </p>
            @if ($pengubahNama)
                <p class="mt-2 text-xs text-gray-400">
                    Terakhir diubah oleh <b>{{ $pengubahNama }}</b>{{ $pengubahWaktu ? ' pada '.$pengubahWaktu : '' }}.
                </p>
            @endif
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Kolom kiri: logo --}}
            <div class="space-y-5">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                    <h2 class="text-sm font-semibold text-gray-950">Logo Usaha</h2>
                    <p class="mt-0.5 text-xs text-gray-400">PNG/JPG/SVG maks. 2 MB. Kosongkan = pakai teks nama / logo bawaan.</p>

                    <div class="mt-4 flex h-40 items-center justify-center rounded-xl bg-gray-50 ring-1 ring-gray-200"
                        x-data="{ previewUrl: null }">
                        {{-- Preview instan file yang baru dipilih (client-side). --}}
                        <template x-if="previewUrl">
                            <img :src="previewUrl" alt="Pratinjau logo baru" class="max-h-32 max-w-full object-contain" />
                        </template>
                        <div x-show="!previewUrl" class="flex h-full w-full items-center justify-center">
                            @if ($logoFile)
                                {{-- Fallback: preview via Livewire bila skrip client tidak aktif. --}}
                                <img src="{{ $logoFile->temporaryUrl() }}" alt="Pratinjau logo baru" class="max-h-32 max-w-full object-contain">
                            @elseif ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Logo {{ $namaUsaha }}" class="max-h-32 max-w-full object-contain">
                            @else
                                <span class="flex flex-col items-center gap-1 text-gray-300">
                                    <x-heroicon-o-photo class="h-10 w-10" />
                                    <span class="text-xs">Belum ada logo</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    <input type="file" wire:model="logoFile" accept="image/png,image/jpeg,image/svg+xml"
                        x-on:change="const f = $event.target.files[0]; previewUrl = f ? URL.createObjectURL(f) : null"
                        class="mt-3 block w-full text-sm text-gray-500 file:mr-3 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100" />
                    @error('logoFile') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    @if ($logoUrl && ! $logoFile)
                        <label class="mt-3 flex items-center gap-2 text-sm font-medium text-gray-600">
                            <input type="checkbox" wire:model="hapusLogo" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            Hapus logo (kembali ke fallback)
                        </label>
                    @endif
                </div>

                <div class="rounded-xl bg-sky-50/70 p-4 text-xs leading-relaxed text-sky-900/80 ring-1 ring-sky-100">
                    <b>Catatan:</b> logo tersimpan di folder penyimpanan publik dan ikut dihitung kuota (1 GB).
                    Logo tidak ikut pembersihan otomatis foto lama.
                </div>
            </div>

            {{-- Kolom kanan: data usaha --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 lg:col-span-2">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3">
                    <h2 class="text-sm font-semibold text-gray-950">Data Usaha</h2>
                    <x-filament::button wire:click="simpan" wire:loading.attr="disabled" icon="heroicon-m-check-circle">
                        Simpan Info Usaha
                    </x-filament::button>
                </div>
                <form wire:submit="simpan" class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nama Usaha <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="namaUsaha" placeholder="contoh: Paccing Official"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500" />
                        @error('namaUsaha') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Alamat</label>
                        <textarea wire:model="alamat" rows="2" placeholder="Alamat lengkap usaha"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">No. HP / WA (kontak)</label>
                        <input type="text" wire:model="kontakWa" placeholder="contoh: 628123456789"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" wire:model="email" placeholder="opsional"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500" />
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Nama Pemilik (owner)</label>
                        <input type="text" wire:model="namaPemilik" placeholder="opsional"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-blue-500" />
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 sm:col-span-2">
                        <x-filament::button type="submit" icon="heroicon-m-check-circle">
                            Simpan Info Usaha
                        </x-filament::button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
