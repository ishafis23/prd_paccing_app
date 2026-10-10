{{-- Kop laporan (logo + judul + subjudul). Div + posisi absolut, bukan tabel. --}}
<div class="lp-kop">
    @if ($logo)
        <img src="{{ $logo }}" alt="">
    @endif
    <div class="teks {{ $logo ? 'dgn-logo' : '' }}">
        <div class="judul">{{ $dokumen['judul'] }}</div>
        @if (! empty($dokumen['subjudul']))
            <div class="subjudul">{{ $dokumen['subjudul'] }}</div>
        @endif
    </div>
</div>
