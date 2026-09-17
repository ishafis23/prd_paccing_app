@php
    $tabs = [
        ['route' => 'teknisi.jadwal', 'label' => 'Jadwal', 'icon' => 'home'],
        ['route' => 'teknisi.absensi', 'label' => 'Absensi', 'icon' => 'qr-code'],
        ['route' => 'teknisi.riwayat', 'label' => 'Riwayat', 'icon' => 'clock'],
        ['route' => 'teknisi.capaian', 'label' => 'Capaian', 'icon' => 'chart-bar'],
        ['route' => 'teknisi.akun', 'label' => 'Akun', 'icon' => 'user-circle'],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur"
    style="max-width: 480px; margin: 0 auto;">
    <div class="grid h-[68px] grid-cols-5">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <a href="{{ route($tab['route']) }}" wire:navigate
                class="flex flex-col items-center justify-center gap-0.5 {{ $active ? 'text-blue-600' : 'text-gray-400 transition hover:text-gray-600' }}">
                <span class="flex h-8 w-14 items-center justify-center rounded-full {{ $active ? 'bg-blue-100' : '' }}">
                    <x-dynamic-component :component="'heroicon-' . ($active ? 's' : 'o') . '-' . $tab['icon']" class="h-6 w-6" />
                </span>
                <span class="text-[11px] font-semibold {{ $active ? 'text-blue-700' : '' }}">
                    {{ $tab['label'] }}
                </span>
            </a>
        @endforeach
    </div>
</nav>
