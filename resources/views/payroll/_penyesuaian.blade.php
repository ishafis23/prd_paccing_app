@php
    $run = $run ?? $getRecord();
    $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
    $daftar = $run->adjustments()->get();
    $bisaHapus = $run->draft() && \App\Filament\Resources\PayrollRunResource::canViewAny();
@endphp
@if ($daftar->isEmpty())
    <p class="text-sm" style="opacity:.65">Belum ada bonus/potongan lain.</p>
@else
    <table class="rincian-gaji" style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead>
            <tr style="text-align:left;font-size:11px;text-transform:uppercase;opacity:.7;">
                <th style="padding:6px 8px;">Jenis</th>
                <th style="padding:6px 8px;">Keterangan</th>
                <th style="padding:6px 8px;">Tanggal</th>
                <th style="padding:6px 8px;">Catatan</th>
                <th style="padding:6px 8px;text-align:right;">Jumlah</th>
                @if ($bisaHapus)<th></th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($daftar as $a)
                @php $bonus = $a->jenis === \App\Enums\PayrollItemJenis::Bonus; @endphp
                <tr style="border-top:1px solid rgba(128,128,128,.25);">
                    <td style="padding:6px 8px;color:{{ $bonus ? '#059669' : '#dc2626' }};font-weight:bold;">{{ $bonus ? 'Bonus' : 'Potongan' }}</td>
                    <td style="padding:6px 8px;">{{ $a->keterangan }}</td>
                    <td style="padding:6px 8px;white-space:nowrap;">{{ $a->tanggal?->format('d M Y') ?? '—' }}</td>
                    <td style="padding:6px 8px;opacity:.75;">{{ $a->catatan ?: '—' }}</td>
                    <td style="padding:6px 8px;text-align:right;white-space:nowrap;color:{{ $bonus ? '#059669' : '#dc2626' }};">{{ $bonus ? '+' : '−' }} {{ $rp($a->nominal) }}</td>
                    @if ($bisaHapus)
                        <td style="padding:6px 8px;text-align:right;">
                            <button type="button" wire:click="hapusPenyesuaian({{ $a->id }})" wire:confirm="Hapus {{ $bonus ? 'bonus' : 'potongan' }} ini?" style="color:#dc2626;font-size:12px;">Hapus</button>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
