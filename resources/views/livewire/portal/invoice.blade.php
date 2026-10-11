<div class="space-y-4 p-4">
    <div class="rounded-2xl bg-gradient-to-r from-blue-600 to-blue-700 p-4 text-white shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-blue-100">Total saldo belum lunas</p>
        <p class="mt-1 text-2xl font-extrabold">Rp {{ number_format($totalSaldo, 0, ',', '.') }}</p>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-base font-bold text-gray-900">Invoice</h2>
            <select wire:model.live="status" class="rounded-xl border border-gray-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                <option value="belum">Belum lunas</option>
                <option value="lunas">Lunas</option>
            </select>
        </div>
    </div>

    @forelse ($daftar as $inv)
        <a href="{{ route('portal.invoice.detail', $inv) }}"
            class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100 transition active:bg-gray-50">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm font-bold text-gray-900">{{ $inv->nomor }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ $inv->tanggal?->translatedFormat('d M Y') }}
                        · Jatuh tempo {{ $inv->jatuh_tempo?->translatedFormat('d M Y') ?? '—' }}
                    </p>
                </div>
                <span @class([
                    'shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold',
                    'bg-green-100 text-green-700' => $inv->status === \App\Enums\InvoiceStatus::Lunas,
                    'bg-amber-100 text-amber-700' => $inv->status === \App\Enums\InvoiceStatus::Terkirim,
                ])>{{ $inv->status === \App\Enums\InvoiceStatus::Lunas ? 'Lunas' : 'Belum lunas' }}</span>
            </div>

            <div class="mt-2.5 flex items-end justify-between border-t border-gray-100 pt-2.5">
                <div class="text-xs text-gray-500">
                    Total: <span class="font-semibold text-gray-700">Rp {{ number_format((float) $inv->total, 0, ',', '.') }}</span>
                </div>
                <div class="text-right">
                    <p class="text-[11px] text-gray-400">Saldo</p>
                    <p class="text-sm font-bold {{ $inv->saldo() > 0 ? 'text-red-600' : 'text-green-600' }}">
                        Rp {{ number_format($inv->saldo(), 0, ',', '.') }}
                    </p>
                </div>
            </div>

            @if ($inv->lewatJatuhTempo())
                <p class="mt-1.5 text-[11px] font-semibold text-red-600">Lewat jatuh tempo</p>
            @endif
        </a>
    @empty
        <div class="rounded-2xl bg-white p-6 text-center shadow-sm ring-1 ring-gray-100">
            <p class="text-sm text-gray-400">Belum ada invoice.</p>
        </div>
    @endforelse
</div>
