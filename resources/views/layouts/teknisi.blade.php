@php
    $bisnis = app(\App\Services\BusinessInfoService::class);
    $namaUsaha = $bisnis->namaUsaha();
    $logoUrl = $bisnis->logoUrl();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>{{ $title ?? 'Teknisi' }} — {{ $namaUsaha }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-200/80 text-gray-900 antialiased">
    <div class="relative mx-auto min-h-screen w-full max-w-[480px] bg-gray-100 shadow-2xl shadow-gray-400/30">

        {{-- Header gradasi brand (B19a) — nama/logo dari Info Usaha (B37) --}}
        <header class="sticky top-0 z-40 bg-gradient-to-r from-sky-600 via-blue-600 to-blue-700 shadow-lg shadow-blue-900/20">
            <div class="flex h-14 items-center justify-between gap-3 px-4">
                <div class="flex min-w-0 items-center gap-2.5">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo {{ $namaUsaha }}"
                            class="h-8 w-8 shrink-0 rounded-lg bg-white/90 object-contain p-0.5 ring-1 ring-white/30">
                    @else
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/20 text-white ring-1 ring-white/30">
                            <x-heroicon-o-wrench-screwdriver class="h-5 w-5" />
                        </span>
                    @endif
                    <div class="min-w-0 leading-tight">
                        <p class="max-w-[180px] truncate text-[9px] font-bold uppercase tracking-[0.18em] text-sky-100">{{ $namaUsaha }}</p>
                        <h1 class="truncate text-[15px] font-extrabold text-white">{{ $title ?? 'Teknisi' }}</h1>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-white/15 px-3 text-xs font-bold text-white ring-1 ring-white/25 transition active:bg-white/30">
                        <x-heroicon-o-arrow-right-start-on-rectangle class="h-4 w-4" />
                        Keluar
                    </button>
                </form>
            </div>
        </header>

        <main class="pb-28">
            {{ $slot }}
        </main>
    </div>

    <x-teknisi-bottom-nav />

    @livewireScripts
</body>
</html>
