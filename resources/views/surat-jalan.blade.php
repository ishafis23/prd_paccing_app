@php
    $bisnisSj = app(\App\Services\BusinessInfoService::class);
    $namaUsaha = $bisnisSj->namaUsaha();
    $logoUrl = $bisnisSj->logoUrl();
    $alamatUsaha = $bisnisSj->alamatTampil();
    $kontakWaSj = $bisnisSj->kontakWaTampil();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Surat Jalan #{{ $order->id }} — {{ $namaUsaha }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            body { background: #fff !important; }
            main { box-shadow: none !important; margin: 0 !important; max-width: 100% !important; min-height: auto !important; }
            .no-print { display: none; }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
@php
    $customer = $order->customer;
    $pic = $order->teknisi;
    $tim = $order->timTeknisi->map(fn ($u) => (int) $u->id === (int) $order->teknisi_id ? $u->name.' (PIC)' : $u->name);
    if ($tim->isEmpty() && $pic) {
        $tim = collect([$pic->name.' (PIC)']);
    }
@endphp

<main class="mx-auto max-w-md bg-white p-6 shadow-sm min-h-screen">
    {{-- Kop --}}
    <div class="flex items-center justify-between border-b border-gray-200 pb-4">
        <div>
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $namaUsaha }}" class="h-10 w-auto">
            @elseif (file_exists(public_path('assets/logo.png')))
                <img src="{{ asset('assets/logo.png') }}" alt="{{ $namaUsaha }}" class="h-10 w-auto">
            @else
                <span class="text-2xl font-extrabold tracking-tight text-sky-700">{{ $namaUsaha }}</span>
            @endif
            @if ($alamatUsaha)
                <p class="mt-1 text-xs text-gray-500">{{ $alamatUsaha }}</p>
            @endif
            @if ($kontakWaSj)
                <p class="text-[11px] text-gray-400">WA: {{ $kontakWaSj }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-lg font-bold">Surat Jalan</p>
            <p class="text-xs text-gray-500">No. Order: #{{ $order->id }}</p>
        </div>
    </div>

    {{-- Info kunjungan --}}
    <dl class="mt-4 space-y-2 text-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-gray-500 shrink-0">Customer</dt>
            <dd class="text-right font-semibold">{{ $customer?->nama ?? '—' }}</dd>
        </div>
        @if (filled($order->alamat_pengerjaan))
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 shrink-0">Lokasi</dt>
                <dd class="text-right">{{ $order->alamat_pengerjaan }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-4">
            <dt class="text-gray-500 shrink-0">Jadwal</dt>
            <dd class="text-right">
                {{ $order->tanggal_jadwal?->translatedFormat('d M Y') ?? '—' }}
                @if ($order->jam_jadwal)
                    {{ \Illuminate\Support\Carbon::parse($order->jam_jadwal)->format('H:i') }}
                @endif
            </dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-gray-500 shrink-0">Tim Teknisi</dt>
            <dd class="text-right">{{ $tim->isNotEmpty() ? $tim->join(', ') : '— belum di-assign —' }}</dd>
        </div>
    </dl>

    {{-- Daftar pekerjaan --}}
    <div class="mt-5">
        <h3 class="text-sm font-bold text-gray-700">Daftar Pekerjaan</h3>
        <table class="mt-2 w-full text-xs">
            <thead>
                <tr class="border-b border-gray-200 text-left text-gray-500">
                    <th class="py-1.5 pr-2">Layanan</th>
                    <th class="py-1.5 pr-2">Kategori</th>
                    <th class="py-1.5 text-right">Jumlah</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($order->orderItems as $item)
                    <tr>
                        <td class="py-1.5 pr-2">{{ $item->nama_layanan }}</td>
                        <td class="py-1.5 pr-2 text-gray-500">{{ $item->kategori ? \Illuminate\Support\Str::headline($item->kategori->value) : '—' }}</td>
                        <td class="py-1.5 text-right">{{ $item->jumlah }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-2 text-center text-gray-400">Belum ada rincian layanan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (filled($order->catatan_admin))
        <div class="mt-4">
            <h3 class="text-sm font-bold text-gray-700">Catatan</h3>
            <p class="mt-1 whitespace-pre-line text-xs text-gray-600">{{ $order->catatan_admin }}</p>
        </div>
    @endif

    {{-- Tanda tangan --}}
    <div class="mt-8 grid grid-cols-2 gap-4 text-center text-xs">
        <div>
            <p class="text-gray-500">Yang Menyerahkan (Teknisi)</p>
            <div class="mt-10 border-t border-gray-300 pt-1">{{ $pic?->name ?? '—' }}</div>
        </div>
        <div>
            <p class="text-gray-500">Yang Menerima (Customer)</p>
            <div class="mt-10 border-t border-gray-300 pt-1">&nbsp;</div>
        </div>
    </div>

    {{-- Footer --}}
    <footer class="mt-6 border-t border-gray-200 pt-3 text-center">
        <p class="text-xs font-semibold text-sky-700">{{ $namaUsaha }}</p>
        <p class="text-[10px] text-gray-400 mt-1">Dokumen ini sah tanpa cap perusahaan.</p>
    </footer>

    <button type="button" onclick="window.print()"
        class="no-print mt-6 w-full rounded-xl bg-sky-600 py-2.5 text-sm font-bold text-white active:bg-sky-700">
        Cetak / Simpan PDF
    </button>
</main>
</body>
</html>
