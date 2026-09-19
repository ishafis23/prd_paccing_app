<div class="space-y-3 px-4 pt-4 pb-8">
    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-100">
        <h1 class="text-base font-bold text-gray-900">Riwayat Absensi</h1>
        <p class="mt-1 text-sm text-gray-500">Jam datang &amp; pulang per tanggal.</p>
    </div>

    @forelse ($riwayat as $absen)
        @php
            $telat = in_array($absen->status_datang?->value, ['telat', 'telat_toleransi'], true);
            $labelStatus = match ($absen->status_datang?->value) {
                'bonus' => 'Tepat Waktu (Bonus)',
                'normal' => 'Normal',
                'telat' => 'Terlambat',
                'telat_toleransi' => 'Terlambat (Toleransi)',
                default => null,
            };
            $kelasBadge = match ($absen->status_datang?->value) {
                'bonus' => 'bg-emerald-50 text-emerald-700',
                'telat', 'telat_toleransi' => 'bg-rose-100 text-rose-700',
                default => 'bg-gray-100 text-gray-600',
            };
        @endphp
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 {{ $telat ? 'ring-rose-200' : 'ring-gray-100' }}">
            <div class="flex items-center justify-between gap-2">
                <span class="text-sm font-bold text-gray-900">{{ $absen->tanggal->translatedFormat('d M Y (l)') }}</span>
                @if ($labelStatus)
                    <span class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-[11px] font-bold {{ $kelasBadge }}">
                        {{ $labelStatus }}
                    </span>
                @endif
            </div>
            <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs text-gray-400">Datang</p>
                    <p class="font-semibold {{ $telat ? 'text-rose-600' : 'text-gray-800' }}">
                        {{ $absen->jam_datang?->format('H:i') ?? '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Pulang</p>
                    <p class="font-semibold text-gray-800">{{ $absen->jam_pulang?->format('H:i') ?? '—' }}</p>
                </div>
            </div>
            @if ($absen->dikecualikan_denda)
                <p class="mt-2 text-xs font-medium text-blue-600">Denda telat dikecualikan admin.</p>
            @endif
        </div>
    @empty
        <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-100">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-50">
                <x-heroicon-o-calendar-days class="h-8 w-8 text-blue-300" />
            </span>
            <p class="mt-4 font-bold text-gray-700">Belum ada riwayat absensi</p>
        </div>
    @endforelse

    <div class="pt-1">
        {{ $riwayat->links() }}
    </div>
</div>
