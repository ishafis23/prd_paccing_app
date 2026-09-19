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
            <div x-show="!scanning" class="rounded-2xl bg-amber-50 p-5 text-center shadow-sm ring-1 ring-amber-100">
                <x-heroicon-o-qr-code class="mx-auto h-8 w-8 text-amber-500" />
                <p class="mt-2 text-sm font-semibold text-amber-700">Belum absen datang</p>
                <p class="mt-1 text-xs text-amber-600">Scan kode QR yang ditempel di kantor untuk absen datang.</p>
                <button type="button" x-on:click="start()"
                    class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-amber-600 py-3 text-sm font-bold text-white active:bg-amber-700">
                    <x-heroicon-o-camera class="h-5 w-5" /> Scan Kode QR
                </button>
                <p x-show="error" x-text="error" class="mt-2 text-xs font-medium text-rose-600"></p>
            </div>
            {{-- video SELALU ada di DOM (disembunyikan lewat x-show, bukan
                 x-if) — x-ref harus tersedia sejak awal, x-if sempat bikin
                 elemen video baru terbentuk setelah start() jalan (race,
                 $refs.qrVideo masih undefined). --}}
            <div x-show="scanning" class="relative overflow-hidden rounded-2xl bg-black shadow-sm">
                <video x-ref="qrVideo" class="h-64 w-full object-cover" playsinline muted autoplay></video>
                <div x-show="!videoReady && !decoded" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-white">
                    <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin" />
                    <span class="text-xs">Membuka kamera…</span>
                </div>
                {{-- Konfirmasi visual begitu QR terbaca, sebelum redirect
                     (dulu langsung pindah halaman diam2 — user kira macet,
                     keluhan 19 Sep). --}}
                <div x-show="decoded" class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-emerald-600 text-white">
                    <x-heroicon-o-check-circle class="h-10 w-10" />
                    <span class="text-sm font-semibold">Kode terdeteksi — memproses…</span>
                </div>
                <button type="button" x-on:click="stop()" x-show="!decoded"
                    class="absolute right-2 top-2 rounded-full bg-black/60 px-3 py-1.5 text-xs font-bold text-white">
                    Batal
                </button>
            </div>
        </div>
    @elseif ($this->state === 'siap_datang')
        <form wire:submit="catatDatang" class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">Catat Datang</h2>
            <p class="mt-1 text-xs text-gray-500">Ambil foto bukti kehadiran di kantor.</p>

            <div x-data="cameraUpload('foto')" class="relative mt-4 h-40 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
                <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                    <template x-if="!preview">
                        <div class="flex flex-col items-center">
                            <x-heroicon-o-camera class="h-7 w-7" />
                            <span class="mt-1 text-xs">Ketuk untuk ambil/pilih foto</span>
                        </div>
                    </template>
                    <img x-show="preview" :src="preview" alt="Pratinjau foto datang" class="absolute inset-0 h-full w-full object-cover">
                    <input type="file" accept="image/*" capture="user"
                        x-on:change="onFile($event)"
                        class="absolute inset-0 cursor-pointer opacity-0">
                </label>
                <div x-show="uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-white/70">
                    <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                    <span x-show="progress > 0" x-text="progress + '%'" class="text-[10px] font-bold text-blue-600"></span>
                </div>
                <p x-show="error" x-text="error" class="absolute inset-x-2 bottom-1 text-center text-[10px] font-medium text-rose-600"></p>
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

            <div x-data="cameraUpload('foto')" class="relative mt-4 h-40 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
                <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                    <template x-if="!preview">
                        <div class="flex flex-col items-center">
                            <x-heroicon-o-camera class="h-7 w-7" />
                            <span class="mt-1 text-xs">Ketuk untuk ambil/pilih foto</span>
                        </div>
                    </template>
                    <img x-show="preview" :src="preview" alt="Pratinjau foto pulang" class="absolute inset-0 h-full w-full object-cover">
                    <input type="file" accept="image/*" capture="user"
                        x-on:change="onFile($event)"
                        class="absolute inset-0 cursor-pointer opacity-0">
                </label>
                <div x-show="uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-white/70">
                    <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                    <span x-show="progress > 0" x-text="progress + '%'" class="text-[10px] font-bold text-blue-600"></span>
                </div>
                <p x-show="error" x-text="error" class="absolute inset-x-2 bottom-1 text-center text-[10px] font-medium text-rose-600"></p>
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

    {{-- Games 3: Catat Cuci Motor — berdiri sendiri, tidak tergantung status absen datang/pulang.
         Banner ilustrasi (icon + gradient) dulu sblm form, biar lebih menarik —
         bukan foto editorial hasil hotlink (risiko hak cipta/link mati). --}}
    <form wire:submit="catatCuciMotor" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-100">
        <div class="relative overflow-hidden bg-gradient-to-br from-sky-500 via-cyan-500 to-blue-600 px-5 py-6">
            <div class="absolute -right-4 -top-6 h-24 w-24 rounded-full bg-white/10"></div>
            <div class="absolute -bottom-8 right-12 h-16 w-16 rounded-full bg-white/10"></div>
            <div class="absolute bottom-3 left-10 h-7 w-7 rounded-full bg-white/10"></div>
            <div class="relative flex items-center gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/20 ring-1 ring-white/30">
                    <x-heroicon-o-sparkles class="h-6 w-6 text-white" />
                </span>
                <div>
                    <h2 class="text-base font-extrabold text-white">Cuci &amp; Rawat Motor</h2>
                    <p class="text-xs text-sky-50">Games 3 — bonus insentif kebersihan motor</p>
                </div>
            </div>
        </div>

        <div class="p-5">
        <p class="text-xs text-gray-500">Bisa dicatat pagi maupun sore. Maksimal 2 orang per motor.</p>

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

        <div x-data="cameraUpload('fotoMotor')" class="relative mt-3 h-32 overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50">
            <label class="absolute inset-0 flex cursor-pointer flex-col items-center justify-center text-gray-400">
                <template x-if="!preview">
                    <div class="flex flex-col items-center">
                        <x-heroicon-o-camera class="h-6 w-6" />
                        <span class="mt-1 text-xs">Ketuk untuk ambil/pilih foto</span>
                    </div>
                </template>
                <img x-show="preview" :src="preview" alt="Pratinjau foto cuci motor" class="absolute inset-0 h-full w-full object-cover">
                <input type="file" accept="image/*" capture="environment"
                    x-on:change="onFile($event)"
                    class="absolute inset-0 cursor-pointer opacity-0">
            </label>
            <div x-show="uploading" class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-white/70">
                <x-heroicon-o-arrow-path class="h-6 w-6 animate-spin text-blue-600" />
                <span x-show="progress > 0" x-text="progress + '%'" class="text-[10px] font-bold text-blue-600"></span>
            </div>
            <p x-show="error" x-text="error" class="absolute inset-x-2 bottom-1 text-center text-[10px] font-medium text-rose-600"></p>
        </div>
        @error('fotoMotor') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

        <button type="submit" wire:loading.attr="disabled" wire:target="catatCuciMotor"
            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 py-3 text-sm font-bold text-white active:bg-blue-700 disabled:opacity-60">
            Catat Cuci Motor
        </button>
        </div>
    </form>
</div>
