@props([
    'fieldName',     // fotoSebelum, fotoSesudah, dll
    'label',         // Label untuk display
    'orderId',       // Order ID untuk upload
    'showRemoveBtn' => true, // Tampilkan tombol remove
    'height' => 'h-44', // Tinggi box
])

<div x-data="photoUpload('{{ $fieldName }}', {{ $orderId }})">
    {{-- Tombol membuka dialog --}}
    <button
        type="button"
        @click="openDialog()"
        class="relative flex {{ $height }} w-full flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 text-center text-gray-400 transition hover:border-blue-400 hover:bg-blue-50"
    >
        <template x-if="!preview">
            <div class="flex flex-col items-center">
                <x-heroicon-o-camera class="h-6 w-6" />
                <span class="mt-1 text-xs font-medium">{{ $label }}</span>
                <span class="mt-0.5 px-2 text-center text-[10px] text-gray-300">
                    Ketuk untuk buka kamera atau pilih
                </span>
            </div>
        </template>
        <img x-show="preview" :src="preview" alt="{{ $label }}"
            class="absolute inset-0 h-full w-full object-cover">
        <span x-show="preview" x-cloak
            class="absolute inset-x-1 bottom-1 truncate rounded bg-black/60 px-1.5 py-0.5 text-center text-[9px] leading-tight text-white"
            x-text="nama">
        </span>
    </button>

    {{-- Dialog Pilihan Kamera/Gallery --}}
    <div x-show="showDialog" x-cloak
        class="fixed inset-0 z-50 flex items-end bg-black/40 transition"
        @click="showDialog = false"
    >
        <div class="w-full rounded-t-2xl bg-white shadow-xl"
            @click.stop
        >
            {{-- Header --}}
            <div class="border-b border-gray-100 px-4 py-3">
                <h3 class="text-sm font-bold text-gray-900">{{ $label }}</h3>
                <p class="mt-0.5 text-xs text-gray-500">Pilih sumber foto</p>
            </div>

            {{-- Options --}}
            <div class="space-y-2 p-4">
                <button
                    type="button"
                    @click="openCamera()"
                    class="flex w-full items-center gap-3 rounded-xl bg-blue-50 px-4 py-3 text-left font-medium text-blue-700 active:bg-blue-100"
                >
                    <x-heroicon-o-camera class="h-5 w-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Buka Kamera</p>
                        <p class="text-xs text-blue-600">Ambil foto langsung</p>
                    </div>
                </button>

                <button
                    type="button"
                    @click="openGallery()"
                    class="flex w-full items-center gap-3 rounded-xl bg-green-50 px-4 py-3 text-left font-medium text-green-700 active:bg-green-100"
                >
                    <x-heroicon-o-photo class="h-5 w-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Pilih dari Gallery</p>
                        <p class="text-xs text-green-600">Dari galeri perangkat</p>
                    </div>
                </button>
            </div>

            {{-- Batal --}}
            <div class="border-t border-gray-100 p-4">
                <button
                    type="button"
                    @click="showDialog = false"
                    class="w-full rounded-xl bg-gray-100 py-2.5 font-semibold text-gray-600 active:bg-gray-200"
                >
                    Batal
                </button>
            </div>
        </div>
    </div>

    {{-- Loading indicator --}}
    <div x-show="uploading" x-cloak class="mt-2 flex items-center gap-2 text-xs text-blue-600">
        <x-heroicon-o-arrow-path class="h-4 w-4 animate-spin" />
        <span>Mengunggah<span x-show="progress > 0" x-text="' ('+progress+'%)'"></span>...</span>
    </div>

    {{-- Error message --}}
    <p x-show="error" x-text="error" class="mt-2 text-xs text-red-600"></p>

    {{-- Remove button (jika ada preview) --}}
    <button
        type="button"
        @click="removePhoto()"
        x-show="preview && {{ $showRemoveBtn ? 'true' : 'false' }}"
        x-cloak
        class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-red-500 hover:text-red-600"
    >
        <x-heroicon-o-x-mark class="h-4 w-4" />
        Hapus foto
    </button>
</div>
