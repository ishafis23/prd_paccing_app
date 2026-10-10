@php
    $data = $this->dataOmset();
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Omset Jasa &amp; Material — {{ $data['label'] }}</x-slot>

        <x-slot name="headerEnd">
            <div class="flex items-center gap-2">
                <x-filament::button size="sm" color="gray" wire:click="toggleRinci">
                    {{ $rinci ? 'Per kategori' : 'Rinci per transaksi' }}
                </x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="exportCsv">
                    Ekspor CSV
                </x-filament::button>
            </div>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm" data-omset-tabel>
                <thead>
                    <tr class="border-b text-left text-gray-500">
                        <th class="py-2 pr-4">Pekerjaan</th>
                        <th class="py-2 pr-4 text-right">Omset Jasa</th>
                        <th class="py-2 pr-4 text-right">Omset Material</th>
                        <th class="py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['rows'] as $r)
                        <tr class="border-b border-gray-100">
                            <td class="py-2 pr-4">{{ $r['label'] }} <span class="text-xs text-gray-400">({{ $r['unit'] }} unit)</span></td>
                            <td class="py-2 pr-4 text-right">{{ $rp($r['jasa']) }}</td>
                            <td class="py-2 pr-4 text-right">{{ $rp($r['material']) }}</td>
                            <td class="py-2 text-right font-medium">{{ $rp($r['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-gray-400">Belum ada omset pada periode ini.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <td class="py-2 pr-4">Total ({{ $data['total']['unit'] }} unit)</td>
                        <td class="py-2 pr-4 text-right">{{ $rp($data['total']['jasa']) }}</td>
                        <td class="py-2 pr-4 text-right">{{ $rp($data['total']['material']) }}</td>
                        <td class="py-2 text-right">{{ $rp($data['total']['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
