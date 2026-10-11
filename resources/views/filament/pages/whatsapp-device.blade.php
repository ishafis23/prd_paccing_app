<x-filament-panels::page>
    <div class="space-y-5">
        @if (! $gatewayAktif)
            <div class="rounded-xl bg-warning-50 p-4 text-sm text-warning-700 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:text-warning-400">
                <b>Mode kirim nonaktif.</b> Sambungkan &amp; uji koneksi tetap bisa. Untuk mengaktifkan
                pengiriman, isi <code>WA_AKTIF=true</code> beserta kredensial gateway di <code>.env</code>.
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
                    Uji coba pengiriman. <b>Nomor tujuan bisa diedit bebas</b> (default nomor device) —
                    berguna untuk tes ke nomor sendiri/admin. Setiap kirim tercatat di Riwayat Kirim WA.
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Device pengirim</label>
                        <select wire:model="tesDeviceId"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-100">
                            @foreach ($devices as $d)
                                <option value="{{ $d['id'] }}">{{ $d['device_name'] }} ({{ $d['nomor_wa'] ?: 'tanpa nomor' }})</option>
                            @endforeach
                        </select>
                        @error('tesDeviceId') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Nomor tujuan</label>
                        <input type="tel" wire:model="tesTarget" placeholder="62812xxxxxxx"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-100">
                        @error('tesTarget') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Isi pesan</label>
                    <textarea wire:model="tesPesan" rows="5"
                        class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-100"></textarea>
                    @error('tesPesan') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>

                <button type="button" wire:click="kirimTes"
                    class="mt-3 rounded-lg bg-success-600 px-4 py-2 text-sm font-medium text-white hover:bg-success-500">
                    Kirim Tes
                </button>
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
                                        <span class="rounded-full bg-success-50 px-2 py-0.5 text-xs font-medium text-success-700">Terhubung</span>
                                    @else
                                        <span class="rounded-full bg-warning-50 px-2 py-0.5 text-xs font-medium text-warning-700">Belum terhubung</span>
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
                                <button type="button" wire:click="ambilQr({{ $d['id'] }})"
                                    class="rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-500">
                                    Ambil QR
                                </button>
                                <button type="button" wire:click="cekStatus({{ $d['id'] }})"
                                    class="rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-100">
                                    Cek Status
                                </button>
                                <button type="button" wire:click="putuskan({{ $d['id'] }})"
                                    wire:confirm="Putuskan sesi device {{ $d['device_name'] }}? Perlu scan ulang QR."
                                    class="rounded-lg bg-danger-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-danger-500">
                                    Putuskan / Reset
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
