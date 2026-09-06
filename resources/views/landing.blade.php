@php
    $bisnis = app(\App\Services\BusinessInfoService::class);
    $namaUsaha = $bisnis->namaUsaha();
    $logoUrl = $bisnis->logoUrl();
    $alamatUsaha = $bisnis->alamatTampil();
    $kontakWa = $bisnis->kontakWaTampil();
    $namaPemilik = $bisnis->data()->nama_pemilik;
    $waClean = preg_replace('/\D/', '', (string) $kontakWa);
    $waLink = $waClean ? 'https://wa.me/'.$waClean : null;

    $fmtRp = fn (float $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $labelJenis = fn ($item): string => match ($item->jenis_layanan?->value) {
        'cuci_ac' => 'Cuci AC',
        'service_ac' => 'Service AC',
        'pengadaan_ac' => 'Pengadaan AC',
        default => str($item->jenis_layanan?->value ?? '')->headline()->toString(),
    };
    $gambarUrl = fn (?string $path): ?string => $path ? asset('storage/'.ltrim($path, '/')) : null;
    $area = $areaLayanan ?? ['Makassar', 'Gowa', 'Maros'];
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0284c7">
    <title>{{ $namaUsaha }} — Cuci AC & Service AC {{ implode(', ', $area) }}</title>
    <meta name="description" content="{{ $namaUsaha }} melayani cuci AC, service AC, dan pengadaan unit AC di {{ implode(', ', $area) }}. Teknisi berpengalaman, tepat waktu, bergaransi.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <style>
        @media (prefers-reduced-motion: no-preference) {
            .reveal { opacity: 0; transform: translateY(1.25rem); transition: opacity .7s ease-out, transform .7s ease-out; }
            .reveal.is-visible { opacity: 1; transform: translateY(0); }
        }
        .bg-grid {
            background-image: radial-gradient(circle at 1px 1px, rgba(255,255,255,.14) 1px, transparent 0);
            background-size: 22px 22px;
        }
    </style>
</head>
<body class="bg-white text-gray-900 antialiased selection:bg-sky-200 selection:text-sky-900">

    {{-- ═══ Topbar ═══ --}}
    @if ($settings->jam_operasional || $waLink)
        <div class="bg-gray-950 text-gray-300">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-1.5 text-xs">
                <p class="flex items-center gap-1.5">
                    <x-heroicon-o-clock class="h-3.5 w-3.5 text-sky-400" />
                    {{ $settings->jam_operasional ?: 'Hubungi kami' }}
                </p>
                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener" class="flex items-center gap-1.5 font-semibold text-emerald-300 transition hover:text-emerald-200">
                        <x-heroicon-o-chat-bubble-left-right class="h-3.5 w-3.5" /> {{ $kontakWa }}
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- ═══ Header ═══ --}}
    <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/80 backdrop-blur-md">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
            <a href="#beranda" class="flex min-w-0 items-center gap-2">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $namaUsaha }}" class="h-9 w-auto">
                @endif
                <span class="truncate text-lg font-extrabold tracking-tight text-gray-900">{{ $namaUsaha }}</span>
            </a>

            <nav class="hidden items-center gap-1 text-sm font-semibold text-gray-600 md:flex">
                <a href="#layanan" class="group relative rounded-full px-3 py-2 transition hover:text-sky-700">
                    Layanan
                    <span class="absolute inset-x-3 -bottom-0.5 h-0.5 scale-x-0 rounded-full bg-sky-600 transition-transform duration-300 group-hover:scale-x-100"></span>
                </a>
                <a href="#cara-kerja" class="group relative rounded-full px-3 py-2 transition hover:text-sky-700">
                    Cara Kerja
                    <span class="absolute inset-x-3 -bottom-0.5 h-0.5 scale-x-0 rounded-full bg-sky-600 transition-transform duration-300 group-hover:scale-x-100"></span>
                </a>
                <a href="#area" class="group relative rounded-full px-3 py-2 transition hover:text-sky-700">
                    Area
                    <span class="absolute inset-x-3 -bottom-0.5 h-0.5 scale-x-0 rounded-full bg-sky-600 transition-transform duration-300 group-hover:scale-x-100"></span>
                </a>
                <a href="#kontak" class="group relative rounded-full px-3 py-2 transition hover:text-sky-700">
                    Kontak
                    <span class="absolute inset-x-3 -bottom-0.5 h-0.5 scale-x-0 rounded-full bg-sky-600 transition-transform duration-300 group-hover:scale-x-100"></span>
                </a>
            </nav>

            <div class="flex items-center gap-2">
                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm shadow-emerald-600/30 transition hover:-translate-y-0.5 hover:bg-emerald-700 hover:shadow-md hover:shadow-emerald-600/40">
                        <x-heroicon-o-chat-bubble-left-right class="h-4 w-4" /> Pesan via WhatsApp
                    </a>
                @else
                    <a href="#kontak" class="inline-flex items-center gap-1.5 rounded-full bg-sky-600 px-4 py-2 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-sky-700">Hubungi Kami</a>
                @endif
            </div>
        </div>
    </header>

    {{-- ═══ Hero / Carousel ═══ --}}
    @if ($slides->isNotEmpty())
        @php $totalSlide = $slides->count(); @endphp
        <section id="beranda" class="relative overflow-hidden bg-gray-950" data-hero-carousel data-autoplay="6000">
            <div class="relative h-[440px] sm:h-[560px]">
                @foreach ($slides as $idx => $slide)
                    <div data-slide="{{ $idx }}"
                        class="absolute inset-0 transition-opacity duration-1000 ease-out {{ $idx === 0 ? 'opacity-100 z-10' : 'opacity-0 z-0' }}">
                        <img src="{{ $gambarUrl($slide->gambar) }}" alt="{{ $slide->judul ?: 'Slide ' . ($idx + 1) }}"
                            class="h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-950/90 via-gray-950/45 to-gray-950/10"></div>
                        @if ($slide->judul || $slide->subjudul)
                            <div class="absolute inset-x-0 bottom-0">
                                <div class="mx-auto max-w-6xl px-4 pb-14 sm:pb-20">
                                    @if ($slide->judul)
                                        <h1 class="max-w-2xl text-3xl font-extrabold leading-tight text-white drop-shadow-sm sm:text-5xl">{{ $slide->judul }}</h1>
                                    @endif
                                    @if ($slide->subjudul)
                                        <p class="mt-3 max-w-xl text-sm text-gray-200 sm:text-base">{{ $slide->subjudul }}</p>
                                    @endif
                                    @if ($slide->tombol_teks && $slide->tombol_url)
                                        <a href="{{ $slide->tombol_url }}" target="_blank" rel="noopener"
                                            class="mt-6 inline-flex items-center gap-2 rounded-full bg-emerald-500 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-500/30 transition hover:-translate-y-0.5 hover:bg-emerald-600">
                                            {{ $slide->tombol_teks }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($totalSlide > 1)
                <button type="button" data-hero-prev aria-label="Slide sebelumnya"
                    class="absolute left-3 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 sm:flex">
                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                </button>
                <button type="button" data-hero-next aria-label="Slide berikutnya"
                    class="absolute right-3 top-1/2 z-20 hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white backdrop-blur transition hover:bg-white/20 sm:flex">
                    <x-heroicon-o-chevron-right class="h-5 w-5" />
                </button>

                <div class="absolute bottom-5 left-1/2 z-20 flex -translate-x-1/2 gap-1.5">
                    @foreach ($slides as $idx => $slide)
                        <button type="button" data-hero-dot="{{ $idx }}" aria-label="Ke slide {{ $idx + 1 }}"
                            class="h-2.5 rounded-full transition-all {{ $idx === 0 ? 'w-6 bg-white' : 'w-2.5 bg-white/50 hover:bg-white/80' }}"></button>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        {{-- Fallback hero gradasi (bila belum ada slide). --}}
        <section id="beranda" class="relative overflow-hidden bg-gray-950 text-white">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-sky-600/40 blur-3xl"></div>
                <div class="absolute -right-16 top-1/3 h-80 w-80 rounded-full bg-blue-500/30 blur-3xl"></div>
                <div class="absolute inset-0 bg-grid opacity-40"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-gray-950/40 via-gray-950/70 to-gray-950"></div>
            </div>
            <div class="relative mx-auto max-w-6xl px-4 py-20 text-center sm:py-32">
                <span class="mx-auto inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-semibold text-sky-200 backdrop-blur">
                    <x-heroicon-o-sparkles class="h-3.5 w-3.5" /> Terpercaya di {{ implode(', ', $area) }}
                </span>
                <h1 class="mx-auto mt-6 max-w-2xl text-4xl font-extrabold leading-tight tracking-tight sm:text-6xl">Satu Sistem untuk Cuci &amp; Service AC Anda</h1>
                <p class="mx-auto mt-5 max-w-xl text-sm text-gray-300 sm:text-base">
                    {{ $namaUsaha }} melayani cuci AC, service AC, dan pengadaan unit AC di {{ implode(', ', $area) }}.
                    Teknisi berpengalaman, datang tepat waktu, pekerjaan terdokumentasi.
                </p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                    <a href="#layanan" class="rounded-full bg-white px-6 py-2.5 text-sm font-bold text-sky-700 shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:bg-sky-50">Lihat Layanan</a>
                    @if ($waLink)
                        <a href="{{ $waLink }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full border border-white/25 px-6 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-white/10">
                            <x-heroicon-o-chat-bubble-left-right class="h-4 w-4" /> Chat WhatsApp
                        </a>
                    @endif
                </div>
            </div>
        </section>
    @endif

    {{-- ═══ Keunggulan ═══ --}}
    <section class="relative z-10 -mt-8 sm:-mt-10">
        <div class="mx-auto grid max-w-6xl grid-cols-2 gap-3 px-4 sm:grid-cols-4 sm:gap-4">
            @foreach ([
                ['wrench-screwdriver', 'Teknisi Berpengalaman', 'Ditangani teknisi terlatih'],
                ['clock', 'Tepat Waktu', 'Datang sesuai jadwal'],
                ['shield-check', 'Garansi Pengerjaan', 'Tenang setelah servis'],
                ['map-pin', 'Area ' . implode(' & ', $area), 'Cakupan layanan jelas'],
            ] as [$ikon, $judulKe, $subKe])
                <div class="reveal rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm shadow-gray-200/60 transition hover:-translate-y-1 hover:shadow-lg hover:shadow-sky-100 sm:p-5">
                    <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-blue-600 text-white shadow-md shadow-sky-500/30">
                        <x-dynamic-component :component="'heroicon-o-' . $ikon" class="h-6 w-6" />
                    </span>
                    <p class="mt-3 text-sm font-bold text-gray-900">{{ $judulKe }}</p>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $subKe }}</p>
                </div>
            @endforeach
        </div>
    </section>

    @if ($settings->tampil_layanan)
        {{-- ═══ Layanan ═══ --}}
        <section id="layanan" class="mx-auto max-w-6xl px-4 py-16 sm:py-24">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-600">Layanan Kami</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">Pilih Layanan Sesuai Kebutuhan</h2>
                <p class="mx-auto mt-3 max-w-xl text-sm text-gray-500">Harga transparan mengikuti katalog resmi {{ $namaUsaha }}. Konsultasi gratis via WhatsApp.</p>
            </div>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($layanan as $item)
                    <article class="reveal group relative overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm shadow-gray-200/60 transition duration-300 hover:-translate-y-1.5 hover:border-sky-100 hover:shadow-xl hover:shadow-sky-100/70">
                        @if ($gambarUrl($item->gambar))
                            <div class="aspect-[16/9] overflow-hidden bg-gray-100">
                                <img src="{{ $gambarUrl($item->gambar) }}" alt="{{ $labelJenis($item) }}" loading="lazy"
                                    class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            </div>
                        @else
                            <div class="flex aspect-[16/9] items-center justify-center bg-gradient-to-br from-sky-50 to-blue-100/60 text-sky-300">
                                <x-heroicon-o-wrench-screwdriver class="h-14 w-14" />
                            </div>
                        @endif
                        <div class="p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-bold text-gray-900">{{ $labelJenis($item) }}</h3>
                                    @if ($item->jenis_unit || $item->pk)
                                        <p class="mt-0.5 text-xs uppercase tracking-wide text-gray-400">
                                            {{ collect([$item->jenis_unit?->value, $item->pk])->filter()->implode(' · ') }}
                                        </p>
                                    @endif
                                </div>
                                <p class="shrink-0 rounded-full bg-sky-50 px-3 py-1 text-sm font-extrabold text-sky-700">{{ $fmtRp((float) $item->harga) }}</p>
                            </div>
                            @if ($item->deskripsi)
                                <p class="mt-3 text-sm leading-relaxed text-gray-500">{{ $item->deskripsi }}</p>
                            @endif
                            @if ($item->interval_bulan)
                                <p class="mt-2 text-xs text-gray-400">Disarankan rutin tiap {{ $item->interval_bulan }} bulan</p>
                            @endif
                            <div class="mt-4 flex items-center justify-between border-t border-gray-50 pt-3">
                                <span class="text-xs text-gray-400">Mulai dari harga di atas</span>
                                @if ($waLink)
                                    <a href="{{ $waLink }}?text={{ urlencode('Halo ' . $namaUsaha . ', saya mau pesan layanan: ' . $labelJenis($item)) }}" target="_blank" rel="noopener"
                                        class="inline-flex items-center gap-1 rounded-full bg-sky-600 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-sky-700">
                                        Pesan
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="col-span-full rounded-2xl bg-gray-50 py-10 text-center text-sm text-gray-400">Katalog layanan belum tersedia.</p>
                @endforelse
            </div>
        </section>
    @endif

    @if ($settings->tampil_cara_kerja)
        {{-- ═══ Cara Kerja ═══ --}}
        <section id="cara-kerja" class="bg-gray-50/70 py-16 sm:py-24">
            <div class="mx-auto max-w-6xl px-4">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-600">Cara Kerja</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">4 Langkah Mudah</h2>
                </div>
                <div class="relative mt-14 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="pointer-events-none absolute inset-x-0 top-5 hidden border-t-2 border-dashed border-sky-200 lg:block"></div>
                    @foreach ([
                        ['1', 'Hubungi Kami', 'Chat WhatsApp atau telepon — sampaikan kebutuhan cuci/service AC.'],
                        ['2', 'Atur Jadwal', 'Kami konfirmasi hari & jam teknisi datang ke lokasi.'],
                        ['3', 'Teknisi Datang', 'Pengerjaan rapi, dokumentasi foto sebelum & sesudah.'],
                        ['4', 'Beres & Garansi', 'Pembayaran fleksibel (QRIS/rekening/tunai), resi digital dikirim.'],
                    ] as [$no, $judulLangkah, $isiLangkah])
                        <div class="reveal relative rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-100 transition hover:-translate-y-1 hover:shadow-lg">
                            <span class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-sky-500 to-blue-600 text-sm font-extrabold text-white shadow-md shadow-sky-500/30">{{ $no }}</span>
                            <h3 class="mt-4 font-bold text-gray-900">{{ $judulLangkah }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-gray-500">{{ $isiLangkah }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ═══ Area + Peta ═══ --}}
    @if ($settings->tampil_area || ($settings->tampil_peta && $settings->maps_embed))
        <section id="area" class="mx-auto max-w-6xl px-4 py-16 sm:py-24">
            @if ($settings->tampil_area)
                <div class="reveal mx-auto max-w-2xl text-center">
                    <p class="text-xs font-bold uppercase tracking-[0.2em] text-sky-600">Area Layanan</p>
                    <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">Melayani Sekitar Anda</h2>
                    <div class="mt-6 flex flex-wrap justify-center gap-2">
                        @foreach ($area as $namaArea)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-5 py-2 text-sm font-semibold text-sky-700 ring-1 ring-sky-100">
                                <x-heroicon-o-map-pin class="h-4 w-4" /> {{ $namaArea }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($settings->tampil_peta && $settings->maps_embed)
                <div class="reveal mt-10 overflow-hidden rounded-3xl bg-white p-2 shadow-xl shadow-gray-200/70 ring-1 ring-gray-100">
                    <iframe src="{{ $settings->maps_embed }}" title="Peta lokasi {{ $namaUsaha }}" loading="lazy"
                        class="h-[380px] w-full rounded-2xl border-0" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            @endif
        </section>
    @endif

    {{-- ═══ CTA kontak ═══ --}}
    <section id="kontak" class="mx-auto max-w-6xl px-4 pb-16 sm:pb-24">
        <div class="reveal relative overflow-hidden rounded-3xl bg-gradient-to-br from-sky-600 via-blue-600 to-blue-700 p-8 text-center text-white shadow-xl shadow-blue-600/20 sm:p-14">
            <div class="pointer-events-none absolute inset-0 bg-grid opacity-30"></div>
            <div class="pointer-events-none absolute -right-10 -top-10 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-14 -left-10 h-56 w-56 rounded-full bg-emerald-400/20 blur-3xl"></div>
            <div class="relative">
                <h2 class="text-2xl font-extrabold sm:text-4xl">Butuh cuci atau service AC?</h2>
                @if ($alamatUsaha)
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-sm text-sky-100">
                        <x-heroicon-o-map-pin class="h-4 w-4" /> {{ $alamatUsaha }}
                    </p>
                @endif
                @if ($settings->jam_operasional)
                    <p class="mt-1 text-sm text-sky-100">{{ $settings->jam_operasional }}</p>
                @endif
                @if ($waLink)
                    <a href="{{ $waLink }}" target="_blank" rel="noopener"
                        class="mt-7 inline-flex items-center gap-2 rounded-full bg-emerald-500 px-8 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-900/20 transition hover:-translate-y-0.5 hover:bg-emerald-600">
                        <x-heroicon-o-chat-bubble-left-right class="h-5 w-5" /> Chat WhatsApp Sekarang
                    </a>
                @else
                    <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                        <a href="#layanan" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-2.5 text-sm font-bold text-sky-700 transition hover:-translate-y-0.5 hover:bg-sky-50">Lihat Layanan</a>
                        <a href="#area" class="inline-flex items-center gap-2 rounded-full border border-white/40 px-6 py-2.5 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-white/10">Cek Area Layanan</a>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ═══ Footer ═══ --}}
    <footer class="border-t border-gray-100 bg-gray-950 text-gray-400">
        <div class="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <div class="flex items-center gap-2">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $namaUsaha }}" class="h-8 w-auto brightness-0 invert">
                    @endif
                    <p class="text-base font-extrabold text-white">{{ $namaUsaha }}</p>
                </div>
                <p class="mt-3 max-w-sm text-sm leading-relaxed">
                    Jasa cuci AC, service AC, dan pengadaan unit AC yang rapi & terdokumentasi — satu sistem untuk kebutuhan AC Anda.
                </p>
                @if ($namaPemilik)
                    <p class="mt-3 text-xs text-gray-500">Owner: {{ $namaPemilik }}</p>
                @endif
            </div>
            <div>
                <p class="text-sm font-bold text-white">Kontak</p>
                <div class="mt-3 space-y-2.5 text-sm">
                    @if ($alamatUsaha)
                        <p class="flex items-start gap-2"><x-heroicon-o-map-pin class="mt-0.5 h-4 w-4 shrink-0 text-sky-500" /> {{ $alamatUsaha }}</p>
                    @endif
                    @if ($kontakWa)
                        <p class="flex items-center gap-2"><x-heroicon-o-chat-bubble-left-right class="h-4 w-4 shrink-0 text-sky-500" /> {{ $kontakWa }}</p>
                    @endif
                    @if ($settings->jam_operasional)
                        <p class="flex items-center gap-2"><x-heroicon-o-clock class="h-4 w-4 shrink-0 text-sky-500" /> {{ $settings->jam_operasional }}</p>
                    @endif
                </div>
            </div>
            <div>
                <p class="text-sm font-bold text-white">Ikuti Kami</p>
                <div class="mt-3 space-y-2 text-sm">
                    @if ($settings->sosmed_instagram)
                        <a href="{{ $settings->sosmed_instagram }}" target="_blank" rel="noopener" class="flex items-center gap-2 transition hover:text-white">
                            <x-heroicon-o-camera class="h-4 w-4" /> Instagram
                        </a>
                    @endif
                    @if ($settings->sosmed_facebook)
                        <a href="{{ $settings->sosmed_facebook }}" target="_blank" rel="noopener" class="flex items-center gap-2 transition hover:text-white">
                            <x-heroicon-o-user-group class="h-4 w-4" /> Facebook
                        </a>
                    @endif
                    @if (! $settings->sosmed_instagram && ! $settings->sosmed_facebook)
                        <p class="text-sm text-gray-500">Segera hadir.</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="border-t border-white/10 py-4 text-center text-xs">
            © {{ date('Y') }} {{ $namaUsaha }} — One Gate System Paccing
        </div>
    </footer>

    {{-- ═══ Floating WhatsApp CTA ═══ --}}
    @if ($waLink)
        <a href="{{ $waLink }}" target="_blank" rel="noopener" aria-label="Chat WhatsApp"
            class="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-900/30 transition hover:-translate-y-0.5 hover:bg-emerald-600">
            <x-heroicon-o-chat-bubble-left-right class="h-6 w-6" />
        </a>
    @endif

    <script>
        (function () {
            // Hero carousel (plain JS — Alpine isn't bundled on this page)
            var hero = document.querySelector('[data-hero-carousel]');
            if (hero) {
                var slides = Array.prototype.slice.call(hero.querySelectorAll('[data-slide]'));
                var dots = Array.prototype.slice.call(hero.querySelectorAll('[data-hero-dot]'));
                var index = 0;
                var timer = null;

                function show(i) {
                    index = (i + slides.length) % slides.length;
                    slides.forEach(function (slide, idx) {
                        var active = idx === index;
                        slide.classList.toggle('opacity-100', active);
                        slide.classList.toggle('z-10', active);
                        slide.classList.toggle('opacity-0', !active);
                        slide.classList.toggle('z-0', !active);
                    });
                    dots.forEach(function (dot, idx) {
                        var active = idx === index;
                        dot.classList.toggle('w-6', active);
                        dot.classList.toggle('bg-white', active);
                        dot.classList.toggle('w-2.5', !active);
                        dot.classList.toggle('bg-white/50', !active);
                    });
                }

                function restart() {
                    var delay = parseInt(hero.getAttribute('data-autoplay'), 10) || 6000;
                    clearInterval(timer);
                    if (slides.length > 1) {
                        timer = setInterval(function () { show(index + 1); }, delay);
                    }
                }

                dots.forEach(function (dot) {
                    dot.addEventListener('click', function () {
                        show(parseInt(dot.getAttribute('data-hero-dot'), 10));
                        restart();
                    });
                });

                var prev = hero.querySelector('[data-hero-prev]');
                var next = hero.querySelector('[data-hero-next]');
                if (prev) prev.addEventListener('click', function () { show(index - 1); restart(); });
                if (next) next.addEventListener('click', function () { show(index + 1); restart(); });

                hero.addEventListener('mouseenter', function () { clearInterval(timer); });
                hero.addEventListener('mouseleave', restart);

                restart();
            }

            // Scroll reveal
            var revealEls = document.querySelectorAll('.reveal');
            if ('IntersectionObserver' in window && revealEls.length) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15 });
                revealEls.forEach(function (el) { observer.observe(el); });
            } else {
                revealEls.forEach(function (el) { el.classList.add('is-visible'); });
            }
        })();
    </script>
</body>
</html>
