{{-- Fase 4 (dev-plan/21 §5): keterangan per unit. Satu accordion per unit;
     posisi/suhu/kondisi/catatan diisi teknisi, sisanya sudah ter-prefill. --}}
@if ($unitView->isNotEmpty())
    @php $inputCls = 'w-full rounded-lg border border-gray-300 bg-white text-sm'; @endphp
    <div id="keterangan-unit" class="space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <h2 class="flex items-center gap-2 font-bold text-gray-900">
            <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-blue-600" />
            Keterangan Unit
            <span class="text-xs font-normal text-gray-400">({{ $unitView->count() }} unit)</span>
        </h2>

        <div class="space-y-2">
            @foreach ($unitView as $uv)
                @php
                    $unit = $uv['unit'];
                    $terbuka = $unitBuka === $unit->unit_no;
                    $k = "unitForm.{$unit->id}.";
                @endphp
                <div id="unit-{{ $unit->unit_no }}" wire:key="unit-{{ $unit->id }}" class="overflow-hidden rounded-xl bg-gray-50 ring-1 ring-gray-100">
                    <button type="button" wire:click="toggleUnit({{ $unit->unit_no }})"
                        class="flex w-full items-center justify-between gap-2 p-3 text-left">
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-800">Unit {{ $unit->unit_no }}
                                <span class="font-normal text-gray-500">&middot; {{ $unit->jenis_pekerjaan ?: $uv['item']->nama_layanan }}</span>
                            </span>
                            @if ($unit->posisi || $unit->lokasi_label)
                                <span class="block truncate text-xs text-gray-400">{{ collect([$unit->lokasi_label, $unit->posisi])->filter()->implode(' · ') }}</span>
                            @endif
                        </span>
                        @if ($uv['lengkap'])
                            <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Lengkap</span>
                        @elseif ($uv['butuh'])
                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Perlu diisi</span>
                        @else
                            <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">Opsional</span>
                        @endif
                    </button>

                    @if ($terbuka)
                        <div class="space-y-3 border-t border-gray-100 bg-white p-3">
                            @if ($uv['perUnit'])
                                {{-- Fase 4b: foto milik unit ini (baris layanan jumlah > 1). --}}
                                @php $sudahLaporan = $order->workReports->isNotEmpty(); @endphp
                                <div>
                                    <p class="mb-1 text-xs font-semibold text-gray-600">Foto Unit {{ $unit->unit_no }}</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach ($uv['slots'] as $slotKey => $slotLabel)
                                            @php
                                                $tersimpan = $uv['fotoPerSlot']->get($slotKey);
                                                $wajib = array_key_exists($slotKey, $uv['slotWajib']);
                                            @endphp
                                            <div wire:key="fotounit-{{ $unit->id }}-{{ $slotKey }}" x-data="photoUpload('fotoUnit.{{ $unit->id }}.{{ $slotKey }}', {{ $order->id }})">
                                                <button type="button" @click="openDialog()"
                                                    class="relative flex h-28 w-full flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-white text-center text-gray-400 transition hover:border-blue-400 hover:bg-blue-50">
                                                    @if ($tersimpan)
                                                        <img src="{{ asset('storage/'.ltrim($tersimpan->path, '/')) }}" alt="{{ $slotLabel }}"
                                                            class="absolute inset-0 h-full w-full object-cover">
                                                    @else
                                                        <template x-if="!preview">
                                                            <div class="flex flex-col items-center">
                                                                <x-heroicon-o-camera class="h-5 w-5" />
                                                                <span class="mt-0.5 px-1 text-[11px]">{{ $slotLabel }}@if ($wajib) <span class="text-rose-500">*Wajib</span>@endif</span>
                                                            </div>
                                                        </template>
                                                    @endif
                                                    <img x-show="preview" x-cloak :src="preview" alt="Pratinjau {{ $slotLabel }}"
                                                        class="absolute inset-0 h-full w-full object-cover">
                                                    <span @if (! $tersimpan) x-show="preview" x-cloak @endif
                                                        class="absolute inset-x-1 bottom-1 truncate rounded bg-black/60 px-1.5 py-0.5 text-center text-[9px] leading-tight text-white">{{ $slotLabel }}@if ($tersimpan) · ganti @endif</span>
                                                </button>

                                                <div x-show="showDialog" x-cloak class="fixed inset-0 z-50 flex items-end bg-black/40 transition" @click="showDialog = false">
                                                    <div class="w-full rounded-t-2xl bg-white shadow-xl" @click.stop>
                                                        <div class="border-b border-gray-100 px-4 py-3">
                                                            <h3 class="text-sm font-bold text-gray-900">Unit {{ $unit->unit_no }} · {{ $slotLabel }}</h3>
                                                            <p class="mt-0.5 text-xs text-gray-500">Pilih sumber foto</p>
                                                        </div>
                                                        <div class="space-y-2 p-4">
                                                            <button type="button" @click="openCamera()"
                                                                class="flex w-full items-center gap-3 rounded-xl bg-blue-50 px-4 py-3 text-left font-semibold text-blue-700 active:bg-blue-100">
                                                                <x-heroicon-o-camera class="h-5 w-5 shrink-0" /> Buka Kamera
                                                            </button>
                                                            <button type="button" @click="openGallery()"
                                                                class="flex w-full items-center gap-3 rounded-xl bg-green-50 px-4 py-3 text-left font-semibold text-green-700 active:bg-green-100">
                                                                <x-heroicon-o-photo class="h-5 w-5 shrink-0" /> Pilih dari Gallery
                                                            </button>
                                                        </div>
                                                        <div class="border-t border-gray-100 p-4">
                                                            <button type="button" @click="showDialog = false"
                                                                class="w-full rounded-xl bg-gray-100 py-2.5 font-semibold text-gray-600 active:bg-gray-200">Batal</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div x-show="uploading" x-cloak class="mt-1 flex items-center gap-1 text-[10px] text-blue-600">
                                                    <x-heroicon-o-arrow-path class="h-3 w-3 animate-spin" />
                                                    Mengunggah<span x-show="progress > 0" x-text="' ('+progress+'%)'"></span>
                                                </div>
                                                <p x-show="error" x-text="error" class="mt-1 text-[10px] font-medium text-rose-600"></p>
                                                @error('fotoUnit.'.$unit->id.'.'.$slotKey) <p class="mt-1 text-[10px] text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                        @endforeach
                                    </div>
                                    @if ($sudahLaporan)
                                        <button type="button" wire:click="simpanFotoUnitTerunggah({{ $unit->id }})" wire:loading.attr="disabled"
                                            x-data x-bind:disabled="($store.fotoUpload?.inFlight ?? 0) > 0"
                                            class="mt-2 w-full rounded-full bg-emerald-600 py-2 text-sm font-bold text-white disabled:opacity-60 active:bg-emerald-700">
                                            Simpan Foto Unit {{ $unit->unit_no }}
                                        </button>
                                    @else
                                        <p class="mt-1 text-[11px] text-gray-400">Foto ikut tersimpan saat laporan dikirim.</p>
                                    @endif
                                </div>
                            @elseif ($uv['foto']->isNotEmpty())
                                <div class="flex gap-2 overflow-x-auto pb-1">
                                    @foreach ($uv['foto'] as $f)
                                        <div class="w-20 shrink-0">
                                            <img src="{{ asset('storage/'.ltrim($f->path, '/')) }}" alt="{{ $f->slot }}"
                                                class="h-16 w-20 rounded-lg object-cover ring-1 ring-gray-100">
                                            <p class="mt-0.5 truncate text-[10px] text-gray-400">{{ \App\Support\FotoLaporanSlot::untuk($uv['item']->kategori)[$f->slot] ?? $f->slot }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">Lokasi (CK mana)</label>
                                    <input type="text" wire:model="{{ $k }}lokasi_label" class="{{ $inputCls }}">
                                    @error($k.'lokasi_label') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">Posisi</label>
                                    <input type="text" wire:model="{{ $k }}posisi" class="{{ $inputCls }}" placeholder="mis. Ruang Server">
                                    @error($k.'posisi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            @if ($unit->unit_no > 1)
                                <button type="button" wire:click="salinPosisiSebelumnya({{ $unit->id }})"
                                    class="text-xs font-semibold text-blue-600">
                                    Salin posisi dari unit sebelumnya
                                </button>
                            @endif

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">Pekerjaan</label>
                                    <input type="text" wire:model="{{ $k }}jenis_pekerjaan" class="{{ $inputCls }}">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">Bagian</label>
                                    <select wire:model="{{ $k }}bagian" class="{{ $inputCls }}">
                                        @foreach (\App\Models\OrderUnitReport::BAGIAN as $val => $lbl)
                                            <option value="{{ $val }}">{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @if ($uv['tampil'] === 'indoor_lengkap')
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-600">Suhu (°C){!! $uv['suhuWajib'] ? ' <span class="text-red-500">*</span>' : '' !!}</label>
                                        <input type="number" step="0.1" inputmode="decimal" wire:model="{{ $k }}suhu" class="{{ $inputCls }}">
                                        @error($k.'suhu') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-600">RPM <span class="font-normal text-gray-400">(opsional)</span></label>
                                        <input type="number" step="1" inputmode="numeric" wire:model="{{ $k }}rpm" class="{{ $inputCls }}">
                                        @error($k.'rpm') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            @endif

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Kondisi <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    @foreach (\App\Models\OrderUnitReport::KONDISI as $val => $lbl)
                                        <label class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-gray-50 py-2 text-sm font-medium text-gray-700 ring-1 ring-gray-200">
                                            <input type="radio" value="{{ $val }}" wire:model="{{ $k }}kondisi" class="text-blue-600"> {{ $lbl }}
                                        </label>
                                    @endforeach
                                </div>
                                @error($k.'kondisi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-600">Catatan kondisi <span class="font-normal text-gray-400">(wajib bila tidak normal)</span></label>
                                <textarea wire:model="{{ $k }}catatan_kondisi" rows="2" class="{{ $inputCls }}"></textarea>
                                @error($k.'catatan_kondisi') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <button type="button" wire:click="simpanUnit({{ $unit->id }})" wire:loading.attr="disabled"
                                class="w-full rounded-full bg-blue-600 py-2.5 text-sm font-bold text-white active:bg-blue-700">
                                Simpan Unit {{ $unit->unit_no }}
                            </button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
