<div class="space-y-4 p-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Selamat datang</p>
        <h2 class="mt-0.5 text-lg font-bold text-gray-900">{{ $customer->nama }}</h2>
        @if (filled($customer->alamat))
            <p class="mt-1 text-sm text-gray-500">{{ $customer->alamat }}</p>
        @endif
    </div>

    @if ($reminder)
        <div class="rounded-2xl bg-blue-50 p-4 ring-1 ring-blue-100">
            <p class="flex items-center gap-1.5 text-sm font-bold text-blue-800">
                <x-heroicon-o-bell-alert class="h-4 w-4" /> Jadwal Servis Berikutnya
            </p>
            <p class="mt-1 text-sm text-blue-900">
                {{ $reminder->tanggal_servis_berikutnya->translatedFormat('d F Y') }}
                <span class="text-blue-500">(tiap {{ $reminder->interval_bulan }} bulan)</span>
            </p>
        </div>
    @endif

    <div>
        <h3 class="mb-2 px-1 text-sm font-bold text-gray-700">Unit AC Anda</h3>

        @if ($customer->acUnits->isEmpty())
            <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
                <p class="text-sm text-gray-400">Belum ada data unit AC terdaftar.</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($customer->acUnits as $unit)
                    @php
                        $terakhir = $unit->latestOrderItem;
                    @endphp
                    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="font-bold text-gray-900">{{ $unit->kode_unit }}</p>
                                <p class="text-sm text-gray-500">{{ $unit->kode_ruangan }}</p>
                            </div>
                            @if ($unit->pk)
                                <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{{ $unit->pk }}</span>
                            @endif
                        </div>

                        <div class="mt-3 border-t border-gray-100 pt-3 text-sm">
                            @if ($terakhir)
                                <p class="text-gray-600">
                                    <span class="font-semibold text-gray-800">{{ $terakhir->nama_layanan }}</span>
                                    — {{ $terakhir->order?->tanggal_jadwal?->translatedFormat('d M Y') ?? '—' }}
                                </p>
                                <p class="mt-0.5 text-xs text-gray-400">
                                    Teknisi: {{ $terakhir->order?->teknisi?->name ?? '—' }}
                                    &middot; Status: {{ str($terakhir->order?->status?->value ?? '—')->headline() }}
                                </p>
                            @else
                                <p class="text-xs text-gray-400">Belum pernah dikerjakan.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
