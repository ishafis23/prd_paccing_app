{{-- Foto Per Layanan (Phase 03) - Structured photo upload --}}
<div class="space-y-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
    <div class="space-y-1">
        <div class="flex items-center justify-between">
            <h2 class="flex items-center gap-2 text-lg font-bold text-gray-900">
                <x-heroicon-o-camera class="h-5 w-5 text-blue-600" />
                Foto per Layanan
            </h2>
            {{-- Game 2 Status (Phase 03 - Task 2.3) --}}
            @if ($this->isGame2Expired())
                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-700">
                    <x-heroicon-o-x-circle class="h-3 w-3" />
                    Game 2 Expired ({{ $this->getGame2DeadlineTime() }})
                </span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-700">
                    <x-heroicon-o-check-circle class="h-3 w-3" />
                    Game 2 Aktif (batas {{ $this->getGame2DeadlineTime() }})
                </span>
            @endif
        </div>
        <p class="text-xs text-gray-500">
            Unggah foto sesuai layanan yang dilakukan (JPG/PNG maks 5 MB)
        </p>
    </div>

    {{-- Game 2 Warning (Phase 03 - Task 2.3) --}}
    @if ($this->isGame2Expired())
        <div class="rounded-lg bg-red-50 p-3 ring-1 ring-red-200">
            <p class="flex items-start gap-2 text-xs text-red-700">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 flex-shrink-0" />
                <span>
                    <strong>Batas waktu Game 2 sudah lewat ({{ $this->getGame2DeadlineTime() }}).</strong> Anda tidak bisa mendapat bonus untuk upload foto Game 2 lagi hari ini.
                </span>
            </p>
        </div>
    @endif

    {{-- Progress indicator --}}
    <div class="space-y-2">
        <div class="flex items-center justify-between text-xs">
            <span class="font-medium text-gray-600">Progres</span>
            <span class="text-gray-500">{{ $this->fotoPerLayananProgress['uploaded'] }}/{{ $this->fotoPerLayananProgress['total'] }} foto</span>
        </div>
        <div class="h-2 overflow-hidden rounded-full bg-gray-200">
            <div
                class="h-full bg-blue-600 transition-all duration-300"
                style="width: {{ $this->fotoPerLayananProgress['percent'] }}%">
            </div>
        </div>
    </div>

    {{-- Foto sections (Lokasi, Cuci, Service) --}}
    <div class="space-y-4">
        @foreach ($this->fotoPerLayananStructure as $type => $struktur)
            <div class="space-y-2 rounded-xl bg-gray-50 p-3">
                {{-- Section header dengan checkbox skip --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-800">{{ $struktur['label'] }}</h3>
                        @if ($type !== 'lokasi')
                            <span class="text-xs font-medium text-gray-400">
                                ({{ count($struktur['positions']) }} foto/unit)
                            </span>
                        @endif
                    </div>
                    <label class="flex items-center gap-2 text-sm font-medium text-gray-600">
                        <input
                            type="checkbox"
                            wire:change="toggleSkipSection('{{ $type }}')"
                            @checked($this->isSectionSkipped($type))
                            class="rounded border-gray-300 text-red-600">
                        Tidak ada {{ strtolower($struktur['label']) }}
                    </label>
                </div>

                {{-- Show units section if not skipped --}}
                @if (!$this->isSectionSkipped($type))
                    <div class="space-y-3">
                        {{-- Units loop --}}
                        @foreach (range(1, $this->unitCounts[$type] ?? 1) as $unit)
                            <div class="space-y-2 rounded-lg bg-white p-2">
                                {{-- Unit header (hanya tampil jika repeatable) --}}
                                @if ($struktur['repeatable'])
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-gray-700">
                                            Unit {{ $unit }}
                                        </span>
                                        @if ($unit > 1)
                                            <button
                                                type="button"
                                                wire:click="removeUnit('{{ $type }}', {{ $unit }})"
                                                class="text-xs text-red-600 hover:text-red-700">
                                                Hapus
                                            </button>
                                        @endif
                                    </div>
                                @endif

                                {{-- Photo slots grid (2 columns untuk responsive) --}}
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach ($struktur['positions'] as $posKey => $position)
                                        <div
                                            x-data="cameraUpload('fotoPerLayanan.{{ $type }}.{{ $unit }}.{{ $posKey }}')"
                                            class="relative">
                                            {{-- Photo upload card --}}
                                            <label
                                                class="relative flex h-32 flex-col items-center justify-center overflow-hidden rounded-lg border-2 border-dashed border-gray-300 bg-white text-center transition hover:border-blue-400">
                                                {{-- Empty state --}}
                                                <template x-if="!preview">
                                                    <div class="flex flex-col items-center gap-1 px-2">
                                                        <x-heroicon-o-camera class="h-5 w-5 text-blue-600" />
                                                        {{-- Instruction in blue text (Task 2.4) --}}
                                                        <p class="text-[10px] font-medium text-blue-600">
                                                            {{ $position['instruction'] }}
                                                        </p>
                                                        <p class="text-[9px] text-gray-400">
                                                            Ketuk untuk upload
                                                        </p>
                                                    </div>
                                                </template>

                                                {{-- Preview state --}}
                                                <img
                                                    x-show="preview"
                                                    :src="preview"
                                                    alt="{{ $position['label'] }}"
                                                    class="absolute inset-0 h-full w-full object-cover">
                                                <span
                                                    x-show="preview"
                                                    x-cloak
                                                    class="absolute bottom-1 left-1 right-1 truncate rounded bg-black/60 px-1.5 py-0.5 text-[8px] leading-tight text-white">
                                                    {{ $position['label'] }}
                                                </span>

                                                {{-- Upload progress --}}
                                                <div x-show="uploading" class="absolute inset-0 flex items-center justify-center bg-white/70">
                                                    <div class="flex flex-col items-center gap-1">
                                                        <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin text-blue-600" />
                                                        <span
                                                            x-show="progress > 0"
                                                            x-text="progress + '%'"
                                                            class="text-[8px] font-bold text-blue-600"></span>
                                                    </div>
                                                </div>

                                                {{-- File input --}}
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    capture="environment"
                                                    x-on:change="onFile($event)"
                                                    class="absolute inset-0 cursor-pointer opacity-0">
                                            </label>

                                            {{-- Error message --}}
                                            <p x-show="error" x-text="error" class="mt-1 text-[9px] font-medium text-rose-600"></p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        {{-- Add Unit button (untuk repeatable types) --}}
                        @if ($struktur['repeatable'])
                            <button
                                type="button"
                                wire:click="addUnit('{{ $type }}')"
                                class="w-full rounded-lg border-2 border-dashed border-gray-300 py-2 text-sm font-medium text-gray-600 transition hover:border-blue-400 hover:text-blue-600">
                                + Tambah Unit
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Validation error for foto per layanan --}}
    @error('fotoPerLayanan')
        <p class="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-600">
            {{ $message }}
        </p>
    @enderror
</div>
