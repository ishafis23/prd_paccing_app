<x-filament-panels::page>
    <div class="space-y-5">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
            <p class="text-sm text-gray-600">
                Seluruh ambang jam &amp; nominal skema absensi dan insentif "Games" (dev-plan/15).
                Perubahan di sini <b>langsung berlaku</b>, tanpa perlu deploy ulang.
            </p>
        </div>

        <form wire:submit="simpan" class="space-y-5">
            @php
                $field = function (string $label, string $model, string $type = 'text', ?string $suffix = null, ?string $help = null) {
                    return compact('label', 'model', 'type', 'suffix', 'help');
                };
            @endphp

            {{-- Games 1 + denda + toleransi --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Games 1 — Kehadiran, Denda Telat &amp; Toleransi Lembur</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        $field('Batas jam hadir (dapat Games 1)', 'jamGames1Batas', 'time'),
                        $field('Bonus Games 1', 'nominalGames1', 'number', 'Rp'),
                        $field('Batas jam normal (sebelum kena denda)', 'jamNormalSelesai', 'time'),
                        $field('Nominal denda telat', 'nominalDendaTelat', 'number', 'Rp'),
                        $field('Ambang "lembur" hari sebelumnya', 'jamToleransiLemburMulai', 'time', null, 'Jam pulang absen kantor hari sebelumnya ≥ ini = dianggap lembur.'),
                        $field('Batas denda mundur jadi (toleransi)', 'jamToleransiBatasDenda', 'time'),
                    ] as $f)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ $f['label'] }}</label>
                            <div class="flex items-center gap-2">
                                @if ($f['suffix'])
                                    <span class="text-sm text-gray-500">{{ $f['suffix'] }}</span>
                                @endif
                                <input type="{{ $f['type'] }}" wire:model="{{ $f['model'] }}"
                                    class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500" />
                            </div>
                            @if ($f['help'])
                                <p class="mt-1 text-xs text-gray-400">{{ $f['help'] }}</p>
                            @endif
                            @error($f['model']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Games 2 --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Games 2 — Titik Pertama</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        $field('Batas jam sampai titik pertama', 'jamGames2Batas', 'time'),
                        $field('Bonus Games 2', 'nominalGames2', 'number', 'Rp'),
                    ] as $f)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ $f['label'] }}</label>
                            <div class="flex items-center gap-2">
                                @if ($f['suffix'])<span class="text-sm text-gray-500">{{ $f['suffix'] }}</span>@endif
                                <input type="{{ $f['type'] }}" wire:model="{{ $f['model'] }}"
                                    class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500" />
                            </div>
                            @error($f['model']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Games 3 --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Games 3 — Cuci/Perawatan Motor</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Bonus Games 3 (per orang)</label>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">Rp</span>
                            <input type="number" wire:model="nominalGames3"
                                class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500" />
                        </div>
                        @error('nominalGames3') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Games 4 --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Games 4 — Kepulangan (Titik &amp; Jam)</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        $field('Batas jam sudah di kantor', 'jamGames4Batas', 'time'),
                        $field('Minimal titik (jalan berdua)', 'minimalTitikBerdua', 'number'),
                        $field('Minimal titik (jalan sendiri)', 'minimalTitikSendiri', 'number'),
                        $field('Bonus (jalan berdua, per orang)', 'nominalGames4Berdua', 'number', 'Rp'),
                        $field('Bonus (jalan sendiri)', 'nominalGames4Sendiri', 'number', 'Rp'),
                    ] as $f)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ $f['label'] }}</label>
                            <div class="flex items-center gap-2">
                                @if ($f['suffix'])<span class="text-sm text-gray-500">{{ $f['suffix'] }}</span>@endif
                                <input type="{{ $f['type'] }}" wire:model="{{ $f['model'] }}"
                                    class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500" />
                            </div>
                            @error($f['model']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Games 5 --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Games 5 — Omset Tim ("Bracci2")</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        $field('Minimal omset (jalan berdua)', 'omsetGames5Minimal', 'number', 'Rp'),
                        $field('Bonus (jalan berdua, per orang)', 'nominalGames5Berdua', 'number', 'Rp'),
                        $field('Bonus (jalan sendiri)', 'nominalGames5Sendiri', 'number', 'Rp'),
                    ] as $f)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ $f['label'] }}</label>
                            <div class="flex items-center gap-2">
                                @if ($f['suffix'])<span class="text-sm text-gray-500">{{ $f['suffix'] }}</span>@endif
                                <input type="{{ $f['type'] }}" wire:model="{{ $f['model'] }}"
                                    class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500" />
                            </div>
                            @error($f['model']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-amber-600">
                    Catatan: definisi persis "omset jasa" (exclude sparepart atau tidak) masih menunggu
                    konfirmasi (dev-plan/15 §7) — otomatisasi penuh Games 5 menyusul.
                </p>
            </div>

            {{-- Games 6 --}}
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <h2 class="text-sm font-semibold text-gray-950">Games 6 — Unit Selesai</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        $field('Minimal unit (jalan berdua)', 'unitGames6Berdua', 'number'),
                        $field('Minimal unit (jalan sendiri)', 'unitGames6Sendiri', 'number'),
                        $field('Bonus (per orang)', 'nominalGames6', 'number', 'Rp'),
                    ] as $f)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">{{ $f['label'] }}</label>
                            <div class="flex items-center gap-2">
                                @if ($f['suffix'])<span class="text-sm text-gray-500">{{ $f['suffix'] }}</span>@endif
                                <input type="{{ $f['type'] }}" wire:model="{{ $f['model'] }}"
                                    class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500" />
                            </div>
                            @error($f['model']) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5">
                <x-filament::button type="submit" icon="heroicon-m-check-circle" wire:loading.attr="disabled">
                    Simpan Pengaturan
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
