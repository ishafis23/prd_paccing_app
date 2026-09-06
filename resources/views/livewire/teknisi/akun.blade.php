<div class="space-y-4 px-4 pt-4">

    {{-- Kartu profil --}}
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center gap-4">
            <x-initials-avatar :name="$user->name" size="h-14 w-14 text-lg" />
            <div class="min-w-0">
                <p class="truncate text-base font-bold text-gray-900">{{ $user->name }}</p>
                <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    Teknisi
                </span>
            </div>
        </div>
    </div>

    {{-- Kontak --}}
    <div class="divide-y divide-gray-50 rounded-2xl bg-white p-2 shadow-sm ring-1 ring-gray-100">
        <div class="flex items-center gap-3 px-3 py-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <x-heroicon-o-envelope class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-xs text-gray-400">Email</p>
                <p class="truncate text-sm font-medium text-gray-700">{{ $user->email }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 px-3 py-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-green-50 text-green-600">
                <x-heroicon-o-phone class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-xs text-gray-400">No. HP</p>
                <p class="truncate text-sm font-medium text-gray-700">{{ $user->phone ?? '—' }}</p>
            </div>
        </div>
    </div>

    {{-- Keluar --}}
    <form method="POST" action="{{ url('/logout') }}">
        @csrf
        <button type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-white p-4 text-sm font-bold text-red-600 shadow-sm ring-1 ring-gray-100 transition active:bg-red-50">
            <x-heroicon-o-arrow-right-start-on-rectangle class="h-5 w-5" />
            Keluar
        </button>
    </form>
</div>
