<style>
    .rincian-gaji { width: 100%; border-collapse: collapse; font-size: 14px; }
    .rincian-gaji th, .rincian-gaji td { padding: 6px 8px; border-bottom: 1px solid rgba(128,128,128,.25); text-align: left; }
    .rincian-gaji th { font-size: 11px; text-transform: uppercase; letter-spacing: .03em; opacity: .7; }
    .rincian-gaji td.angka { text-align: right; white-space: nowrap; }
    .rincian-gaji .muted { opacity: .65; }
    .rincian-gaji tr.grup td { background: rgba(128,128,128,.12); font-size: 11px; font-weight: bold; letter-spacing: .04em; }
    .rincian-gaji tr.sub td { font-weight: bold; border-top: 1px solid rgba(128,128,128,.6); }
    .rincian-gaji tr.total td { border-top: 2px solid currentColor; font-weight: bold; font-size: 16px; }
    .rincian-gaji .bonus { color: #059669; }
    .rincian-gaji .potongan { color: #dc2626; }
</style>
@php $run = $run ?? $getRecord(); $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.'); $b = \App\Enums\PayrollItemJenis::Bonus; @endphp
    @php
        $bonusItems = $run->items->filter(fn ($i) => $i->jenis === $b && $i->kategori !== 'bonus_mingguan');
        $mingguan = $run->items->firstWhere('kategori', 'bonus_mingguan');
        $potItems = $run->items->filter(fn ($i) => $i->jenis !== $b && $i->kategori !== 'dibayar_mingguan');
        $dibayarMingguan = $run->items->firstWhere('kategori', 'dibayar_mingguan');
        $kuota = \App\Services\PayrollService::KUOTA_OFF;
        $totalBonus = $bonusItems->sum('nominal');
        $totalPot = $potItems->sum('nominal');
    @endphp
    <table class="rincian-gaji">
        <thead>
            <tr><th style="width:42%">Komponen</th><th style="width:26%">Keterangan</th><th style="width:32%;text-align:right;">Jumlah</th></tr>
        </thead>
        <tbody>
            <tr class="grup"><td colspan="3">PENDAPATAN</td></tr>
            <tr><td>Gaji Pokok</td><td class="muted">per periode</td><td class="angka">{{ $rp($run->gaji_pokok) }}</td></tr>
            @forelse ($bonusItems as $item)
                <tr><td>{{ $item->label }}</td><td class="muted">bonus</td><td class="angka bonus">+ {{ $rp($item->nominal) }}</td></tr>
            @empty
                <tr><td class="muted">Bonus</td><td class="muted">—</td><td class="angka muted">{{ $rp(0) }}</td></tr>
            @endforelse
            <tr class="sub"><td colspan="2">Total Pendapatan</td><td class="angka">{{ $rp((float) $run->gaji_pokok + $totalBonus) }}</td></tr>

            <tr class="grup"><td colspan="3">POTONGAN</td></tr>
            <tr>
                <td>Denda keterlambatan</td>
                <td class="muted">{{ $run->jumlah_hari_telat }} hari</td>
                <td class="angka potongan">− {{ $rp($run->total_denda_telat) }}</td>
            </tr>
            <tr>
                <td>Potongan OFF</td>
                <td class="muted">{{ $run->jumlah_hari_off }} hari (jatah {{ $kuota }})</td>
                <td class="angka potongan">− {{ $rp($run->total_potongan_off) }}</td>
            </tr>
            <tr>
                <td>Pulang cepat / setengah hari</td>
                <td class="muted">{{ $run->jumlah_setengah_hari }}×</td>
                <td class="angka potongan">− {{ $rp($run->total_potongan_setengah_hari) }}</td>
            </tr>
            <tr>
                <td>BPJS TK</td>
                <td class="muted">—</td>
                <td class="angka potongan">− {{ $rp($run->potongan_bpjs_tk) }}</td>
            </tr>
            <tr class="sub"><td colspan="2">Total Potongan</td><td class="angka">− {{ $rp((float) $run->total_denda_telat + (float) $run->total_potongan_off + (float) $run->total_potongan_setengah_hari + (float) $run->potongan_bpjs_tk) }}</td></tr>

            @if ($mingguan)
                <tr class="grup"><td colspan="3">BONUS MINGGUAN (SUDAH DIBAYAR PEKANAN)</td></tr>
                <tr><td>{{ $mingguan->label }}</td><td class="muted">info</td><td class="angka">{{ $rp($mingguan->nominal) }}</td></tr>
            @endif

            <tr class="total">
                <td colspan="2">TOTAL GAJI DITERIMA</td>
                <td class="angka">{{ $rp($run->gaji_bersih) }}</td>
            </tr>
        </tbody>
    </table>

