@props(['hint', 'label', 'action'])

<div
    x-data="{
        dragging: false,
        offset: 0,
        max: 0,
        done: false,
        // Touch/mouse dipakai terpisah (bukan Pointer Events) — beberapa
        // browser/WebView HP tidak konsisten mendukung pointer events,
        // sehingga tombol geser sempat tidak merespons sama sekali di HP.
        clientX(e) {
            return e.touches?.[0]?.clientX ?? e.changedTouches?.[0]?.clientX ?? e.clientX;
        },
        start(e) {
            if (this.done) return;
            if (e.cancelable) e.preventDefault();
            this.dragging = true;
            this.max = this.$refs.track.offsetWidth - this.$refs.thumb.offsetWidth;
        },
        move(e) {
            if (!this.dragging || this.done) return;
            if (e.cancelable) e.preventDefault();
            const x = this.clientX(e) - this.$refs.track.getBoundingClientRect().left;
            this.offset = Math.max(0, Math.min(x - (this.$refs.thumb.offsetWidth / 2), this.max));
        },
        end() {
            if (!this.dragging || this.done) return;
            this.dragging = false;
            if (this.offset >= this.max * 0.85) {
                this.offset = this.max;
                this.done = true;
                $wire.{{ $action }}();
            } else {
                this.offset = 0;
            }
        },
        // Aksesibilitas: cukup tekan Enter/Space di thumb utk konfirmasi.
        keyConfirm() {
            if (this.done) return;
            this.offset = this.max;
            this.done = true;
            $wire.{{ $action }}();
        }
    }"
    @mousemove.window="move($event)"
    @mouseup.window="end()"
    class="rounded-2xl bg-white p-4 shadow-sm select-none"
>
    <p class="mb-3 text-sm text-gray-600">{{ $hint }}</p>
    <div x-ref="track" class="relative h-14 w-full rounded-full bg-blue-50">
        <div
            x-ref="thumb"
            role="button"
            tabindex="0"
            aria-label="{{ $label }}"
            @mousedown="start($event)"
            @touchstart="start($event)"
            @touchmove="move($event)"
            @touchend="end()"
            @touchcancel="end()"
            @keydown.enter.prevent="keyConfirm"
            @keydown.space.prevent="keyConfirm"
            :class="done ? 'bg-green-600 cursor-default' : 'cursor-grab active:cursor-grabbing'"
            :style="`transform: translateX(${offset}px)`"
            class="absolute left-0 top-0 flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white shadow transition-colors"
            style="touch-action: none;"
        >
            <x-heroicon-o-chevron-double-right class="pointer-events-none h-5 w-5" />
        </div>
        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-semibold text-blue-700" x-show="!done">
            {{ $label }}
        </span>
        <span class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-semibold text-green-700" x-show="done">
            Terkonfirmasi
        </span>
    </div>
</div>
