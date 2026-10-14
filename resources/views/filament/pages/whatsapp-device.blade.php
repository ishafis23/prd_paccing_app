<x-filament-panels::page>
    <div class="space-y-5">
        @if (! $gatewayAktif)
            <div class="flex items-start gap-2 rounded-xl bg-gray-50 p-4 text-sm text-gray-700 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-200">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                <div>
                    <b>Mode kirim nonaktif.</b> Sambungkan &amp; uji koneksi tetap bisa. Untuk mengaktifkan
                    pengiriman, isi <code>WA_AKTIF=true</code> beserta kredensial gateway di <code>.env</code>.
                </div>
            </div>
        @endif

        <div class="rounded-xl bg-white p-4 text-sm text-gray-600 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-300">
            Sambungkan nomor WA lewat <b>scan QR (barcode)</b>, cek status koneksi nyata, dan
            <b>putuskan/reset</b> sesi bila QR basi. Kelola &amp; tugaskan device di menu
            <b>Perangkat WhatsApp</b>.
        </div>

        @if ($qrMessage || $qrImage)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-gray-100">Sambungkan WhatsApp</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $qrMessage }}</p>

                @if ($qrImage)
                    <div class="mt-4 flex justify-center">
                        <img src="{{ $qrImage }}" alt="QR WhatsApp" class="h-64 w-64 rounded-lg bg-white p-2 ring-1 ring-gray-200">
                    </div>
                    <p class="mt-2 text-center text-xs text-gray-400">QR tidak tampil? Klik "Ambil QR" lagi, atau klik "Cek Status".</p>
                @endif

                @if ($qrDetail)
                    <p class="mt-2 break-all text-xs text-gray-400">Respons gateway: {{ $qrDetail }}</p>
                @endif
            </div>
        @endif

        @if ($statusMessage)
            <div class="rounded-xl bg-gray-50 p-3 text-sm text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200">
                {{ $statusMessage }}
            </div>
        @endif

        {{-- Uji kirim teks (fase 23-B) --}}
        @if (! empty($devices))
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-gray-100">Tes Kirim Pesan</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Uji coba pengiriman. <b>Tujuan bisa nomor atau ID grup</b> — isi nomor (mis.
                    62812xxxxxxx) untuk chat pribadi, atau JID grup (mis. 1203630xxxxx@g.us) untuk
                    kirim ke grup. Default nomor device. Setiap kirim tercatat di Riwayat Kirim WA.
                </p>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Jenis tujuan</label>
                    <div class="flex flex-wrap gap-4">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="radio" wire:model.live="tesJenis" value="pribadi" class="text-blue-600">
                            Nomor Pribadi
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input type="radio" wire:model.live="tesJenis" value="grup" class="text-blue-600">
                            Chat Grup
                        </label>
                    </div>
                    @error('tesJenis') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Device pengirim</label>
                        <select wire:model="tesDeviceId"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-100">
                            @foreach ($devices as $d)
                                <option value="{{ $d['id'] }}">{{ $d['device_name'] }} ({{ $d['nomor_wa'] ?: 'tanpa nomor' }})</option>
                            @endforeach
                        </select>
                        @error('tesDeviceId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    @if ($tesJenis === 'grup')
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">ID Grup tujuan</label>
                            <input type="text" wire:model="tesGrup" placeholder="1203630xxxxx@g.us"
                                class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-100">
                            @error('tesGrup') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @else
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Nomor tujuan</label>
                            <input type="tel" wire:model="tesTarget" placeholder="62812xxxxxxx"
                                class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-100">
                            @error('tesTarget') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Isi pesan</label>
                    <textarea wire:model="tesPesan" rows="5"
                        class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-100"></textarea>
                    @error('tesPesan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-3">
                    <x-filament::button type="button" wire:click="kirimTes" icon="heroicon-m-paper-airplane" color="success">
                        Kirim Tes
                    </x-filament::button>
                </div>
            </div>
        @endif

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900">
            <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-gray-100">Daftar Device</h2>
            </div>

            @if (empty($devices))
                <p class="p-4 text-sm text-gray-500">Belum ada device. Tambahkan dulu di menu <b>Perangkat WhatsApp</b>.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($devices as $d)
                        <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $d['device_name'] }}</span>
                                    @if ($d['terhubung'])
                                        <x-filament::badge color="success">Terhubung</x-filament::badge>
                                    @else
                                        <x-filament::badge color="warning">Belum terhubung</x-filament::badge>
                                    @endif
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ $d['nomor_wa'] ?: 'Nomor belum diisi' }}
                                    · {{ $d['provider'] ?: 'akun gateway belum dipilih' }}
                                    · pemakai: {{ $d['pemakai'] ?: '-' }}
                                    @if ($d['last_checked']) · dicek {{ $d['last_checked'] }} @endif
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <x-filament::button type="button" wire:click="ambilQr({{ $d['id'] }})" icon="heroicon-m-qr-code" size="sm">
                                    Ambil QR
                                </x-filament::button>
                                <x-filament::button type="button" wire:click="cekStatus({{ $d['id'] }})" icon="heroicon-m-signal" color="gray" size="sm">
                                    Cek Status
                                </x-filament::button>
                                <x-filament::button type="button" wire:click="putuskan({{ $d['id'] }})"
                                    wire:confirm="Putuskan sesi device {{ $d['device_name'] }}? Perlu scan ulang QR."
                                    icon="heroicon-m-power" color="danger" size="sm">
                                    Putuskan / Reset
                                </x-filament::button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
