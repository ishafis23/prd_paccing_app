<div class="space-y-4 px-4 pt-4 pb-8">

    @if (session('status'))
        <div class="rounded-2xl bg-emerald-100 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <h1 class="text-base font-bold text-gray-900">Absensi Kantor</h1>
        <p class="mt-1 text-sm text-gray-500">{{ now()->translatedFormat('l, d F Y') }}</p>
    </div>

    @if ($this->state === 'kode_invalid')
        <div class="rounded-2xl bg-rose-50 p-5 text-center shadow-sm ring-1 ring-rose-100">
            <x-heroicon-o-exclamation-triangle class="mx-auto h-8 w-8 text-rose-500" />
            <p class="mt-2 text-sm font-semibold text-rose-700">Kode absensi tidak berlaku</p>
            <p class="mt-1 text-xs text-rose-600">Kode sudah dinonaktifkan/kedaluwarsa atau salah. Hubungi admin.</p>
        </div>
    @elseif ($this->state === 'perlu_kode')
        <div x-data="qrScanner('{{ $this->urlAbsensiBase }}')" x-on:livewire:navigating.window="stop()">
            <template x-if="!scanning">
                <div class="rounded-2xl bg-amber-50 p-5 text-center shadow-sm ring-1 ring-amber-100">
                    <x-heroicon-o-qr-code class="mx-auto h-8 w-8 text-amber-500" />
                    <p class="mt-2 text-sm font-semibold text-amber-700">Belum absen datang</p>
                    <p class="mt-1 text-xs text-amber-600">Scan kode QR yang ditempel di kantor untuk absen datang.</p>
                    <button type="button" x-on:click="start()"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-sm font-bold text-white active:bg-amber-700">
                        <x-heroicon-o-camera class="h-5 w-5" /> Scan Kode QR
                    </button>
                    <p x-show="error" x-text="error" class="mt-2 text-xs font-medium text-rose-600"></p>
                </div>
            </template>
            <template x-if="scanning">
                <div class="relative overflow-hidden rounded-2xl bg-black shadow-sm">
                    <video x-ref="qrVideo" class="h-64 w-full object-cover" playsinline muted></video>
                    <button type="button" x-on:click="stop()"
                        class="absolute right-2 top-2 rounded-full bg-black/60 px-3 py-1.5 text-xs font-bold text-white">
                        Batal
                    </button>
                </div>
            </template>
        </div>
    @elseif ($this->state === 'siap_datang')
        <form wire:submit="catatDatang" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Catat Datang</h2>
            <p class="mt-1 text-xs text-gray-500">Ambil foto bukti kehadiran di kantor.</p>

            <div x-data="{ preview: null }" class="relative mt-4 h-40 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
                <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                    <template x-if="!preview">
                        <div class="flex flex-col items-center">
                            <x-heroicon-o-camera class="h-7 w-7" />
                            <span class="mt-1 text-xs">Ketuk untuk ambil/pilih foto</span>
                        </div>
                    </template>
                    <img x-show="preview" :src="preview" alt="Pratinjau foto datang" class="absolute inset-0 h-full w-full object-cover">
                    <input type="file" wire:model="foto" accept="image/*" capture="user"
                        @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null"
                        class="absolute inset-0 cursor-pointer opacity-0">
                </label>
                <div wire:loading wire:target="foto" class="absolute inset-0 flex items-center justify-center bg-white/70">
                    <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                </div>
            </div>
            @error('foto') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <button type="submit" wire:loading.attr="disabled" wire:target="catatDatang"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-sm font-bold text-white active:bg-blue-700 disabled:opacity-60">
                Catat Datang
            </button>
        </form>
    @elseif ($this->state === 'siap_pulang')
        <div class="rounded-2xl bg-emerald-50 p-4 text-sm text-emerald-800 ring-1 ring-emerald-100">
            Datang tercatat jam <b>{{ $this->absenHariIni->jam_datang->format('H:i') }}</b>.
        </div>

        <form wire:submit="catatPulang" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Catat Pulang</h2>
            <p class="mt-1 text-xs text-gray-500">Ambil foto bukti kepulangan.</p>

            <div x-data="{ preview: null }" class="relative mt-4 h-40 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
                <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                    <template x-if="!preview">
                        <div class="flex flex-col items-center">
                            <x-heroicon-o-camera class="h-7 w-7" />
                            <span class="mt-1 text-xs">Ketuk untuk ambil/pilih foto</span>
                        </div>
                    </template>
                    <img x-show="preview" :src="preview" alt="Pratinjau foto pulang" class="absolute inset-0 h-full w-full object-cover">
                    <input type="file" wire:model="foto" accept="image/*" capture="user"
                        @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null"
                        class="absolute inset-0 cursor-pointer opacity-0">
                </label>
                <div wire:loading wire:target="foto" class="absolute inset-0 flex items-center justify-center bg-white/70">
                    <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                </div>
            </div>
            @error('foto') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <button type="submit" wire:loading.attr="disabled" wire:target="catatPulang"
                class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-sm font-bold text-white active:bg-blue-700 disabled:opacity-60">
                Catat Pulang
            </button>
        </form>
    @elseif ($this->state === 'selesai')
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <div class="flex items-center gap-2 text-emerald-600">
                <x-heroicon-o-check-circle class="h-6 w-6" />
                <p class="text-sm font-semibold">Absensi hari ini lengkap</p>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-400">Datang</p>
                    <p class="font-semibold text-gray-800">{{ $this->absenHariIni->jam_datang->format('H:i') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Pulang</p>
                    <p class="font-semibold text-gray-800">{{ $this->absenHariIni->jam_pulang->format('H:i') }}</p>
                </div>
            </div>
        </div>
    @endif

    {{-- Games 3: Catat Cuci Motor — berdiri sendiri, tidak tergantung status absen datang/pulang. --}}
    <form wire:submit="catatCuciMotor" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-sm font-semibold text-gray-900">Catat Cuci/Perawatan Motor (Games 3)</h2>
        <p class="mt-1 text-xs text-gray-500">Bisa dicatat pagi maupun sore. Maksimal 2 orang per motor.</p>

        <div class="mt-3">
            <label class="mb-1 block text-xs font-medium text-gray-700">Rekan (opsional, maks. 1 orang)</label>
            <select wire:model="rekanMotorId"
                class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500">
                <option value="">— Sendiri saja —</option>
                @foreach ($this->daftarTeknisiLain as $id => $nama)
                    <option value="{{ $id }}">{{ $nama }}</option>
                @endforeach
            </select>
        </div>

        <div x-data="{ preview: null }" class="relative mt-3 h-32 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
            <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                <template x-if="!preview">
                    <div class="flex flex-col items-center">
                        <x-heroicon-o-camera class="h-6 w-6" />
                        <span class="mt-1 text-xs">Ketuk untuk ambil/pilih foto</span>
                    </div>
                </template>
                <img x-show="preview" :src="preview" alt="Pratinjau foto cuci motor" class="absolute inset-0 h-full w-full object-cover">
                <input type="file" wire:model="fotoMotor" accept="image/*" capture="environment"
                    @change="const f = $event.target.files[0]; preview = f ? URL.createObjectURL(f) : null"
                    class="absolute inset-0 cursor-pointer opacity-0">
            </label>
            <div wire:loading wire:target="fotoMotor" class="absolute inset-0 flex items-center justify-center bg-white/70">
                <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
            </div>
        </div>
        @error('fotoMotor') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

        <button type="submit" wire:loading.attr="disabled" wire:target="catatCuciMotor"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-sm font-bold text-white active:bg-blue-700 disabled:opacity-60">
            Catat Cuci Motor
        </button>
    </form>
</div>
