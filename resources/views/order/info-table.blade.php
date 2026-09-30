<div class="space-y-6 bg-gradient-to-b from-slate-50 to-white dark:from-slate-900 dark:to-slate-800 p-6 rounded-lg">
    <!-- Header Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Customer Card -->
        <div class="bg-white dark:bg-slate-700 rounded-lg p-4 shadow-sm border border-slate-200 dark:border-slate-600 hover:shadow-md transition">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Pelanggan</p>
            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $getState()->customer->nama }}</p>
        </div>

        <!-- Service Card -->
        <div class="bg-white dark:bg-slate-700 rounded-lg p-4 shadow-sm border border-slate-200 dark:border-slate-600 hover:shadow-md transition">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Layanan</p>
            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $getState()->serviceCatalog?->jenis_layanan->value ?? '—' }}</p>
        </div>

        <!-- Status Card -->
        <div class="bg-white dark:bg-slate-700 rounded-lg p-4 shadow-sm border border-slate-200 dark:border-slate-600 hover:shadow-md transition">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Status</p>
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full {{ $getState()->status->value === 'selesai' ? 'bg-green-500' : ($getState()->status->value === 'batal' ? 'bg-red-500' : 'bg-blue-500') }}"></span>
                <p class="text-lg font-bold text-slate-900 dark:text-white">{{ ucfirst(str_replace('_', ' ', $getState()->status->value)) }}</p>
            </div>
        </div>

        <!-- Date Card -->
        <div class="bg-white dark:bg-slate-700 rounded-lg p-4 shadow-sm border border-slate-200 dark:border-slate-600 hover:shadow-md transition">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">Jadwal</p>
            <p class="text-lg font-bold text-slate-900 dark:text-white">{{ $getState()->tanggal_jadwal?->format('d M Y') ?? '—' }}</p>
        </div>
    </div>

    <!-- Main Info Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-4">
            <!-- Teknisi -->
            <div class="bg-white dark:bg-slate-700 rounded-lg p-5 shadow-sm border border-slate-200 dark:border-slate-600">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">Teknisi (PIC)</p>
                <p class="text-base font-semibold text-slate-900 dark:text-white">{{ $getState()->teknisi?->name ?? '— belum di-assign —' }}</p>
            </div>

            <!-- Jenis Pelanggan -->
            <div class="bg-white dark:bg-slate-700 rounded-lg p-5 shadow-sm border border-slate-200 dark:border-slate-600">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">Jenis Pelanggan</p>
                <p class="text-base font-semibold text-slate-900 dark:text-white">
                    @if ($getState()->jenis_pelanggan?->value === 'company')
                        Instansi
                    @elseif ($getState()->jenis_pelanggan?->value === 'perorangan')
                        Cust Umum
                    @else
                        —
                    @endif
                </p>
            </div>
        </div>

        <!-- Right Column -->
        <div class="space-y-4">
            <!-- Alamat -->
            <div class="bg-white dark:bg-slate-700 rounded-lg p-5 shadow-sm border border-slate-200 dark:border-slate-600">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">Alamat</p>
                <p class="text-base font-semibold text-slate-900 dark:text-white">{{ $getState()->customerAddress?->nama_lokasi ?? '—' }}</p>
            </div>

            <!-- Tim Teknisi -->
            <div class="bg-white dark:bg-slate-700 rounded-lg p-5 shadow-sm border border-slate-200 dark:border-slate-600">
                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-2">Tim Pengerjaan</p>
                @php
                    $tim = $getState()->timTeknisi
                        ->map(fn ($u) => (int) $u->id === (int) $getState()->teknisi_id
                            ? $u->name . ' (PIC)'
                            : $u->name);

                    if ($tim->isEmpty() && $getState()->teknisi !== null) {
                        $tim = collect([$getState()->teknisi->name . ' (PIC)']);
                    }
                @endphp
                <p class="text-base font-semibold text-slate-900 dark:text-white">{{ $tim->isEmpty() ? '— belum di-assign —' : $tim->implode(', ') }}</p>
            </div>
        </div>
    </div>

    <!-- Address Section -->
    <div class="bg-white dark:bg-slate-700 rounded-lg p-5 shadow-sm border border-slate-200 dark:border-slate-600">
        <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-3">Lokasi Pengerjaan</p>
        <p class="text-base text-slate-900 dark:text-white leading-relaxed">{{ $getState()->alamat_pengerjaan ?? '—' }}</p>
    </div>

    <!-- Admin Notes -->
    @if ($getState()->catatan_admin)
        <div class="bg-white dark:bg-slate-700 rounded-lg p-5 shadow-sm border border-slate-200 dark:border-slate-600">
            <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 mb-3">Catatan Admin</p>
            <p class="text-base text-slate-900 dark:text-white leading-relaxed">{{ $getState()->catatan_admin }}</p>
        </div>
    @endif

    <!-- Alerts -->
    @if ($getState()->alasan_kendala)
        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-5 border border-red-200 dark:border-red-800/50">
            <p class="text-sm font-semibold text-red-700 dark:text-red-300 mb-2">⚠️ Alasan Kendala</p>
            <p class="text-base text-red-900 dark:text-red-200">{{ $getState()->alasan_kendala }}</p>
        </div>
    @endif

    @if ($getState()->perbaikan_menunggu_konfirmasi)
        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-5 border border-amber-200 dark:border-amber-800/50">
            <p class="text-sm font-semibold text-amber-700 dark:text-amber-300 mb-2">⏳ Menunggu Konfirmasi Perbaikan</p>
            <p class="text-base text-amber-900 dark:text-amber-200">{{ $getState()->perbaikan_catatan }}
                @if ($getState()->perbaikan_estimasi_harga)
                    <span class="block mt-2">(Estimasi: Rp{{ number_format($getState()->perbaikan_estimasi_harga, 0, ',', '.') }})</span>
                @endif
                <span class="block text-sm mt-2">— {{ $getState()->pelaporPerbaikan?->name ?? '—' }}</span>
            </p>
        </div>
    @endif

    <!-- Assignment Info -->
    @if ($getState()->team?->nama)
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-5 shadow-sm border border-blue-200 dark:border-blue-800/50">
            <p class="text-sm font-semibold text-blue-700 dark:text-blue-300 mb-2">👥 Di-assign via Tim</p>
            <p class="text-base text-blue-900 dark:text-blue-200">{{ $getState()->team->nama }}</p>
        </div>
    @endif
</div>
