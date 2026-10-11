<div class="space-y-4 p-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <h2 class="text-base font-bold text-gray-900">Pekerjaan</h2>
        <p class="mt-0.5 text-xs text-gray-500">Riwayat & jadwal pengerjaan unit AC Anda.</p>

        <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-3">
            <select wire:model.live="bulan" class="rounded-xl border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua bulan</option>
                @for ($i = 0; $i < 12; $i++)
                    @php $opt = now()->subMonthsNoOverflow($i); @endphp
                    <option value="{{ $opt->format('Y-m') }}">{{ $opt->translatedFormat('F Y') }}</option>
                @endfor
            </select>
            <select wire:model.live="cabang" class="rounded-xl border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua cabang</option>
                @foreach ($alamatOptions as $alamat)
                    <option value="{{ $alamat->id }}">{{ $alamat->nama_lokasi ?? $alamat->alamat }}</option>
                @endforeach
            </select>
            <select wire:model.live="jenis" class="rounded-xl border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua jenis</option>
                @foreach ($jenisOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @forelse ($orders as $order)
        <a href="{{ route('portal.pekerjaan.detail', $order) }}"
            class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 transition active:bg-gray-50">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">
                        {{ $order->tanggal_jadwal?->translatedFormat('d F Y') ?? '—' }}
                        @if ($order->jam_jadwal) <span class="font-medium text-gray-500">· {{ $order->jam_jadwal }}</span> @endif
                    </p>
                    <p class="mt-0.5 truncate text-xs text-gray-500">
                        {{ $order->customerAddress?->nama_lokasi ?? $order->alamat_pengerjaan ?? '—' }}
                    </p>
                </div>
                <span @class([
                    'shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                    'bg-green-100 text-green-700' => $order->status->value === 'selesai',
                    'bg-blue-100 text-blue-700' => in_array($order->status->value, ['terjadwal', 'menuju_lokasi', 'dikerjakan'], true),
                    'bg-amber-100 text-amber-700' => $order->status->value === 'butuh_followup',
                    'bg-red-100 text-red-700' => $order->status->value === 'terkendala',
                    'bg-gray-100 text-gray-600' => ! in_array($order->status->value, ['selesai', 'terjadwal', 'menuju_lokasi', 'dikerjakan', 'butuh_followup', 'terkendala'], true),
                ])>{{ str($order->status->value)->headline() }}</span>
            </div>

            <div class="mt-2.5 border-t border-gray-100 pt-2.5 text-xs text-gray-600">
                <p><span class="font-semibold text-gray-800">{{ $order->ringkasanLayanan() }}</span></p>
                <p class="mt-0.5 text-gray-400">
                    Teknisi: {{ $order->teknisi?->name ?? ($order->timTeknisi->pluck('name')->implode(', ') ?: '—') }}
                    @isset($nomorInvoice[$order->id])
                        · Invoice: {{ $nomorInvoice[$order->id] }}
                    @endisset
                </p>
            </div>
        </a>
    @empty
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
            <p class="text-sm text-gray-400">Belum ada pekerjaan untuk filter ini.</p>
        </div>
    @endforelse

    <div class="pt-1">
        {{ $orders->links() }}
    </div>
</div>
