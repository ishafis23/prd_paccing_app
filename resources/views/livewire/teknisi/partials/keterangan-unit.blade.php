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
                            @if ($uv['foto']->isNotEmpty())
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
