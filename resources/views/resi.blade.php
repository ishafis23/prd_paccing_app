@php
    $bisnisResi = app(\App\Services\BusinessInfoService::class);
    $namaUsaha = $bisnisResi->namaUsaha();
    $logoUrl = $bisnisResi->logoUrl();
    $alamatUsaha = $bisnisResi->alamatTampil();
    $kontakWaResi = $bisnisResi->kontakWaTampil();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resi #{{ $order->id }} — {{ $namaUsaha }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            body { background: #fff !important; }
            main { box-shadow: none !important; margin: 0 !important; max-width: 100% !important; min-height: auto !important; }
        }
        .no-print { display: none; }
    </style>
</head>
<body class="bg-gray-100 text-gray-900 antialiased">
@php
    $customer = $order->customer;
    $katalog = $order->serviceCatalog;
    $teknisi = $order->teknisi;
    $pembayaran = $order->latestPayment;
    $laporan = $order->workReports()->orderByDesc('waktu_selesai')->first();
    $lunas = optional($pembayaran)->status === \App\Enums\PaymentStatus::Lunas;
    $channelAktif = app(\App\Services\PaymentChannelService::class)->daftarAktif();
    $metodeDipilih = $order->metode_dipilih?->value;
    $layananNama = $katalog?->jenis_layanan?->value
        ? \Illuminate\Support\Str::headline($katalog->jenis_layanan->value)
        : '—';
    $unitNama = $katalog?->jenis_unit?->value
        ? \Illuminate\Support\Str::headline($katalog->jenis_unit->value)
        : null;
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
            @else
                <p class="text-xs text-gray-500 mt-1">Perawatan AC Profesional</p>
            @endif
            @if ($kontakWaResi)
                <p class="text-[11px] text-gray-400">WA: {{ $kontakWaResi }}</p>
            @endif
        </div>
        <div class="text-right">
            <p class="text-lg font-bold">Resi Pengerjaan</p>
            <p class="text-xs text-gray-500">No. Order: #{{ $order->id }}</p>
        </div>
    </div>

    {{-- Info order --}}
    <dl class="mt-4 space-y-2 text-sm">
        <div class="flex justify-between gap-4">
            <dt class="text-gray-500 shrink-0">Customer</dt>
            <dd class="text-right font-semibold">{{ $customer?->nama ?? '—' }}</dd>
        </div>
        @if (filled($customer?->alamat))
            <div class="flex justify-between gap-4">
                <dt class="text-gray-500 shrink-0">Alamat</dt>
                <dd class="text-right">{{ $customer->alamat }}</dd>
            </div>
        @endif
        <div class="flex justify-between gap-4">
            <dt class="text-gray-500 shrink-0">Layanan</dt>
            <dd class="text-right">{{ $layananNama }}{{ $unitNama ? ' — ' . $unitNama : '' }} &times; {{ $order->jumlah_unit }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-gray-500 shrink-0">Teknisi</dt>
            <dd class="text-right">{{ $teknisi?->name ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4">
            <dt class="text-gray-500 shrink-0">Selesai</dt>
            <dd class="text-right">{{ $laporan?->waktu_selesai?->format('d M Y H:i') ?? '—' }}</dd>
        </div>
        <div class="flex justify-between gap-4 border-t border-gray-200 pt-2">
            <dt class="font-semibold">Total Tagihan</dt>
            <dd class="font-bold">Rp{{ number_format($order->total(), 0, ',', '.') }}</dd>
        </div>
    </dl>

    {{-- Status pembayaran --}}
    @php
        $statusBayar = $pembayaran?->status;
        $labelBayar = match (true) {
            $statusBayar === \App\Enums\PaymentStatus::Lunas => 'Lunas',
            $statusBayar === \App\Enums\PaymentStatus::Dp => 'DP (Sebagian)',
            default => 'Belum bayar',
        };
        $warnaBayar = match (true) {
            $statusBayar === \App\Enums\PaymentStatus::Lunas => 'bg-green-100 text-green-800',
            $statusBayar === \App\Enums\PaymentStatus::Dp => 'bg-amber-100 text-amber-800',
            default => 'bg-red-100 text-red-700',
        };
    @endphp
    <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
        <div class="flex items-center justify-between">
            <span class="text-gray-600">Status Pembayaran</span>
            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $warnaBayar }}">{{ $labelBayar }}</span>
        </div>
        @if ($pembayaran?->tanggal_bayar && $lunas)
            <p class="mt-1 text-xs text-gray-500">Dibayar {{ \Illuminate\Support\Carbon::parse($pembayaran->tanggal_bayar)->format('d M Y') }} — Rp{{ number_format((float) $pembayaran->jumlah_dibayar, 0, ',', '.') }}</p>
        @endif
        @if ($metodeDipilih)
            <p class="mt-1 text-xs text-gray-500">Metode dipilih customer: <span class="font-semibold">{{ ucfirst($metodeDipilih) }}</span></p>
        @endif
    </div>

    {{-- Belum lunas: instruksi pembayaran + channel aktif --}}
    @unless ($lunas)
        <div class="mt-4">
            <h3 class="text-sm font-bold text-gray-700">Cara Pembayaran</h3>
            <p class="text-xs text-gray-500 mt-1">Silakan selesaikan pembayaran melalui salah satu channel di bawah ini:</p>

            @forelse ($channelAktif as $channel)
                <div class="mt-3 rounded-lg border border-gray-200 p-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold">{{ $channel->nama }}</p>
                            @if ($channel->jenis === \App\Enums\PaymentChannelType::Qris)
                                <p class="text-xs text-gray-500 mt-1">QRIS — scan untuk membayar</p>
                            @else
                                <p class="text-xs text-gray-500 mt-1">{{ $channel->nama_bank }}</p>
                                <p class="text-sm font-mono font-semibold mt-0.5">{{ $channel->nomor_rekening }}</p>
                            @endif
                            @if (filled($channel->atas_nama))
                                <p class="text-xs text-gray-500 mt-1">a.n. {{ $channel->atas_nama }}</p>
                            @endif
                        </div>
                        @if ($channel->jenis === \App\Enums\PaymentChannelType::Qris && filled($channel->gambar))
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($channel->gambar) }}"
                                 alt="QRIS {{ $channel->nama }}"
                                 class="h-28 w-28 rounded object-contain border border-gray-200 bg-white shrink-0">
                        @endif
                    </div>
                </div>
            @empty
                <p class="mt-2 text-xs text-gray-500">Channel pembayaran belum tersedia — hubungi admin Paccing.</p>
            @endforelse

            <p class="mt-3 text-xs text-gray-500">* Tunai dapat dibayarkan langsung ke teknisi saat pengerjaan selesai.</p>
        </div>
    @else
        <div class="mt-4 rounded-lg bg-green-50 p-3 text-center">
            <p class="text-sm font-bold text-green-700">✓ Pembayaran LUNAS</p>
            <p class="text-xs text-green-600 mt-1">Terima kasih, pembayaran order ini telah diterima.</p>
        </div>
    @endunless

    {{-- Dokumentasi pengerjaan (foto dari laporan teknisi) --}}
    @if ($laporan && (filled($laporan->foto_sebelum) || filled($laporan->foto_sesudah)))
        <div class="mt-5">
            <h3 class="text-sm font-bold text-gray-700">Dokumentasi Pengerjaan</h3>
            <div class="mt-2 grid grid-cols-2 gap-3">
                @if (filled($laporan->foto_sebelum))
                    <figure>
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($laporan->foto_sebelum) }}"
                             alt="Foto sebelum pengerjaan" class="w-full rounded-lg border border-gray-200 object-cover">
                        <figcaption class="mt-1 text-center text-xs text-gray-500">Sebelum</figcaption>
                    </figure>
                @endif
                @if (filled($laporan->foto_sesudah))
                    <figure>
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($laporan->foto_sesudah) }}"
                             alt="Foto sesudah pengerjaan" class="w-full rounded-lg border border-gray-200 object-cover">
                        <figcaption class="mt-1 text-center text-xs text-gray-500">Sesudah</figcaption>
                    </figure>
                @endif
            </div>
        </div>
    @endif

    {{-- Footer --}}
    <footer class="mt-6 border-t border-gray-200 pt-3 text-center">
        <p class="text-xs font-semibold text-sky-700">Paccing — Perawatan AC Profesional</p>
        <p class="text-[10px] text-gray-400 mt-1">Dokumen ini sah tanpa tanda tangan.</p>
    </footer>
</main>
</body>
</html>
