@php
    $tabs = [
        ['route' => 'teknisi.jadwal', 'label' => 'Jadwal', 'icon' => 'home'],
        ['route' => 'teknisi.absensi', 'label' => 'Absensi', 'icon' => 'qr-code'],
        ['route' => 'teknisi.pulang-cepat', 'label' => 'Pulang', 'icon' => 'arrow-right-start-on-rectangle'],
        ['route' => 'teknisi.riwayat', 'label' => 'Riwayat', 'icon' => 'clock'],
        ['route' => 'teknisi.lengkapi-laporan', 'label' => 'Lengkapi', 'icon' => 'clipboard-document-check'],
        ['route' => 'teknisi.capaian', 'label' => 'Capaian', 'icon' => 'chart-bar'],
        ['route' => 'teknisi.keuangan', 'label' => 'Keuangan', 'icon' => 'wallet'],
        ['route' => 'teknisi.setoran', 'label' => 'Setoran', 'icon' => 'banknotes'],
        ['route' => 'teknisi.akun', 'label' => 'Akun', 'icon' => 'user-circle'],
    ];
@endphp

<nav x-data="{
        open: false,
        sx: 0, sy: 0,
        start(e) { this.sx = e.touches[0].clientX; this.sy = e.touches[0].clientY; },
        end(e) {
            const dx = e.changedTouches[0].clientX - this.sx;
            const dy = e.changedTouches[0].clientY - this.sy;
            if (Math.abs(dy) < 30 || Math.abs(dy) < Math.abs(dx)) return;
            this.open = dy < 0;
        },
        init() {
            this.$nextTick(() => this.$refs.row?.querySelector('[data-active]')?.scrollIntoView({ inline: 'center', block: 'nearest' }));
        }
    }"
    @touchstart.passive="start($event)" @touchend.passive="end($event)"
    @click.outside="open = false"
    class="fixed inset-x-0 bottom-0 z-40 rounded-t-2xl border-t border-gray-200 bg-white/95 shadow-[0_-4px_16px_rgba(0,0,0,0.06)] backdrop-blur"
    style="max-width: 480px; margin: 0 auto;">

    {{-- Handle: ketuk atau tarik ke atas untuk semua menu, tarik ke bawah untuk kembali --}}
    <button type="button" @click="open = !open" class="flex h-4 w-full items-center justify-center" aria-label="Tampilkan semua menu" :aria-expanded="open">
        <span class="h-1 w-10 rounded-full bg-gray-300"></span>
    </button>

    {{-- Default: 5 menu terlihat, sisanya digeser ke kanan --}}
    <div x-show="!open" x-ref="row" class="flex h-[64px] snap-x snap-mandatory overflow-x-auto" style="scrollbar-width: none;">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <a href="{{ \App\Support\Url::absolute($tab['route']) }}" wire:navigate @if ($active) data-active @endif
                class="flex w-1/5 shrink-0 snap-start flex-col items-center justify-center gap-0.5 {{ $active ? 'text-blue-600' : 'text-gray-400 transition hover:text-gray-600' }}">
                <span class="flex h-8 w-14 items-center justify-center rounded-full {{ $active ? 'bg-blue-100' : '' }}">
                    <x-dynamic-component :component="'heroicon-' . ($active ? 's' : 'o') . '-' . $tab['icon']" class="h-6 w-6" />
                </span>
                <span class="w-full truncate text-center text-[11px] font-semibold {{ $active ? 'text-blue-700' : '' }}">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Ditarik ke atas: semua menu dalam grid --}}
    <div x-show="open" x-cloak x-transition.opacity class="grid grid-cols-4 gap-y-3 px-2 pb-4 pt-1">
        @foreach ($tabs as $tab)
            @php $active = request()->routeIs($tab['route']); @endphp
            <a href="{{ \App\Support\Url::absolute($tab['route']) }}" wire:navigate
                class="flex flex-col items-center gap-1 {{ $active ? 'text-blue-600' : 'text-gray-500' }}">
                <span class="flex h-11 w-14 items-center justify-center rounded-2xl {{ $active ? 'bg-blue-100' : 'bg-gray-100' }}">
                    <x-dynamic-component :component="'heroicon-' . ($active ? 's' : 'o') . '-' . $tab['icon']" class="h-6 w-6" />
                </span>
                <span class="text-[11px] font-semibold {{ $active ? 'text-blue-700' : '' }}">{{ $tab['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
