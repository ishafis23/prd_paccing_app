<x-filament-panels::page>
    <div class="space-y-5">
        <div class="rounded-xl bg-white p-4 text-sm text-gray-600 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-300">
            Ambil daftar grup WhatsApp yang diikuti nomor device terpilih. Salin <b>ID Grup</b>, lalu
            tempel ke field <b>"ID Grup WA"</b> pada data Customer (menu Customer → edit) supaya
            dokumentasi pengerjaan customer itu juga dikirim ke grup ini saat admin klik
            <b>"Kirim Resi (WA)"</b>.
        </div>

        @if (empty($devices))
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900">
                <p class="text-sm text-gray-500">Belum ada device. Tambahkan dulu di menu <b>Perangkat WhatsApp</b>.</p>
            </div>
        @else
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900">
                <div class="grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Device</label>
                        <select wire:model="deviceId"
                            class="w-full rounded-lg border-0 bg-gray-50 px-3 py-2 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-100">
                            @foreach ($devices as $d)
                                <option value="{{ $d['id'] }}">{{ $d['device_name'] }} ({{ $d['nomor_wa'] ?: 'tanpa nomor' }})</option>
                            @endforeach
                        </select>
                        @error('deviceId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <x-filament::button type="button" wire:click="cekGroup" icon="heroicon-m-arrow-path" color="primary">
                        Cek Daftar Grup
                    </x-filament::button>
                </div>
            </div>

            @if ($pesanStatus)
                <div class="rounded-xl bg-gray-50 p-3 text-sm text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200">
                    {{ $pesanStatus }}
                </div>
            @endif

            @if (! empty($groups))
                <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2">Nama Grup</th>
                                <th class="px-4 py-2">ID Grup</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($groups as $g)
                                <tr>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-200">{{ $g['subject'] ?: '—' }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $g['id'] }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <button type="button"
                                            onclick="navigator.clipboard.writeText('{{ $g['id'] }}'); this.innerText='Disalin!'; setTimeout(() => this.innerText='Salin ID', 1500)"
                                            class="rounded-lg bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                            Salin ID
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
