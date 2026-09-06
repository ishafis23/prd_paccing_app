<div class="space-y-4 px-4 pt-4">
    <div class="rounded-2xl bg-gradient-to-r from-blue-600 to-sky-500 p-4 shadow-md shadow-blue-200">
        <p class="text-sm font-semibold text-white">Capaian pengerjaan order Anda sejauh ini.</p>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <x-heroicon-o-calendar-days class="h-6 w-6" />
            </span>
            <p class="mt-3 text-3xl font-extrabold text-gray-900">{{ $bulanIni }}</p>
            <p class="mt-1 text-xs font-medium text-gray-500">Selesai Bulan Ini</p>
        </div>
        <div class="rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-50 text-amber-600">
                <x-heroicon-o-trophy class="h-6 w-6" />
            </span>
            <p class="mt-3 text-3xl font-extrabold text-gray-900">{{ $totalSelesai }}</p>
            <p class="mt-1 text-xs font-medium text-gray-500">Total Selesai</p>
        </div>
    </div>
</div>
