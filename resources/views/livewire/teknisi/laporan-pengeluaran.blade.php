<div class="space-y-4">
    {{-- Page header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Laporan Pengeluaran</h1>
            <p class="text-sm text-gray-500">Catat dan pantau pengeluaran harian Anda</p>
        </div>
        <button
            type="button"
            wire:click="$toggle('showForm')"
            class="inline-flex items-center gap-2 rounded-full bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-md hover:bg-blue-700">
            <x-heroicon-o-plus-circle class="h-5 w-5" />
            Tambah Pengeluaran
        </button>
    </div>

    {{-- Flash messages --}}
    @if (session('status'))
        <div class="rounded-lg bg-green-50 p-4 text-sm font-medium text-green-700 ring-1 ring-green-200">
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="rounded-lg bg-red-50 p-4 text-sm font-medium text-red-700 ring-1 ring-red-200">
            {{ session('error') }}
        </div>
    @endif

    {{-- Input Form (toggle-able) --}}
    @if ($showForm)
        <div class="space-y-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h2 class="flex items-center gap-2 font-bold text-gray-900">
                <x-heroicon-o-document-plus class="h-5 w-5 text-blue-600" />
                Catat Pengeluaran Baru
            </h2>

            <form wire:submit="submitPengeluaran" class="space-y-3">
                {{-- Tanggal input --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tanggal</label>
                    <input
                        type="date"
                        wire:model="tanggal_input"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @error('tanggal_input')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    {{-- Kategori --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Kategori</label>
                        <select
                            wire:model="kategori"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            <option value="">Pilih kategori...</option>
                            <option value="bensin">⛽ Bensin</option>
                            <option value="makan">🍜 Makan</option>
                            <option value="material">🔧 Material</option>
                            <option value="transport">🚗 Transport</option>
                            <option value="lainnya">📦 Lainnya</option>
                        </select>
                        @error('kategori')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Nominal --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nominal (Rp)</label>
                        <input
                            type="number"
                            wire:model="nominal"
                            placeholder="Min 1.000"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                        @error('nominal')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Keterangan --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">Keterangan</label>
                    <input
                        type="text"
                        wire:model="keterangan"
                        placeholder="Contoh: Makan siang di kantor"
                        class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                    @error('keterangan')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Action buttons --}}
                <div class="flex gap-2">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="flex-1 rounded-lg bg-blue-600 py-2 font-semibold text-white hover:bg-blue-700 active:bg-blue-800 disabled:opacity-50">
                        <span wire:loading.remove>Simpan</span>
                        <span wire:loading><x-heroicon-o-arrow-path class="inline h-4 w-4 animate-spin" /> Menyimpan...</span>
                    </button>
                    <button
                        type="button"
                        wire:click="$toggle('showForm')"
                        class="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        {{-- Hari ini - Pending --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Hari Ini (Pending)</p>
            <p class="mt-1 text-lg font-bold text-yellow-600">{{ $this->formatRupiah($this->summaryHariIni['total_pending']) }}</p>
            <p class="mt-0.5 text-xs text-gray-400">{{ $this->summaryHariIni['total_items'] ?? 0 }} item</p>
        </div>

        {{-- Hari ini - Approved --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Hari Ini (Disetujui)</p>
            <p class="mt-1 text-lg font-bold text-green-600">{{ $this->formatRupiah($this->summaryHariIni['total_approved']) }}</p>
        </div>

        {{-- Bulan ini - Pending --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Bulan Ini (Pending)</p>
            <p class="mt-1 text-lg font-bold text-yellow-600">{{ $this->formatRupiah($this->summaryBulanIni['total_pending']) }}</p>
        </div>

        {{-- Bulan ini - Approved --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Bulan Ini (Disetujui)</p>
            <p class="mt-1 text-lg font-bold text-green-600">{{ $this->formatRupiah($this->summaryBulanIni['total_approved']) }}</p>
        </div>
    </div>

    {{-- Filter Section --}}
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
        <div class="grid grid-cols-3 gap-2">
            {{-- Month filter --}}
            <div>
                <label class="block text-xs font-medium text-gray-600">Bulan</label>
                <input
                    type="month"
                    wire:model.live="filterBulan"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs">
            </div>

            {{-- Kategori filter --}}
            <div>
                <label class="block text-xs font-medium text-gray-600">Kategori</label>
                <select wire:model.live="filterKategori" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs">
                    <option value="">Semua</option>
                    <option value="bensin">⛽ Bensin</option>
                    <option value="makan">🍜 Makan</option>
                    <option value="material">🔧 Material</option>
                    <option value="transport">🚗 Transport</option>
                    <option value="lainnya">📦 Lainnya</option>
                </select>
            </div>

            {{-- Status filter --}}
            <div>
                <label class="block text-xs font-medium text-gray-600">Status</label>
                <select wire:model.live="filterStatus" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs">
                    <option value="">Semua</option>
                    <option value="pending">Menunggu</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Kategori Breakdown --}}
    @if ($this->kategoriBreakdown)
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h2 class="mb-3 font-bold text-gray-900">Breakdown per Kategori ({{ $filterBulan }})</h2>
            <div class="space-y-2">
                @foreach ($this->kategoriBreakdown as $kat => $data)
                    @if ($data['count'] > 0)
                        <div class="flex items-center justify-between rounded-lg bg-gray-50 p-2 text-sm">
                            <div>
                                <p class="font-medium text-gray-800">{{ $this->getKategoriLabel($kat) }}</p>
                                <p class="text-xs text-gray-500">{{ $data['count'] }} item</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-gray-900">{{ $this->formatRupiah($data['total']) }}</p>
                                <p class="text-xs text-gray-500">
                                    @if ($data['pending'] > 0)
                                        <span class="text-yellow-600">{{ $this->formatRupiah($data['pending']) }} pending</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Expenses List --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
        <div class="border-b border-gray-100 px-4 py-3">
            <h2 class="font-bold text-gray-900">Riwayat Pengeluaran</h2>
        </div>

        @if ($this->expenses->isNotEmpty())
            <div class="divide-y divide-gray-100">
                @foreach ($this->expenses as $expense)
                    <div class="flex items-start justify-between gap-3 p-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">{{ match ($expense->kategori) {
                                    'bensin' => '⛽',
                                    'makan' => '🍜',
                                    'material' => '🔧',
                                    'transport' => '🚗',
                                    default => '📦',
                                } }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-gray-900">{{ ucfirst($expense->kategori) }}</p>
                                    <p class="truncate text-xs text-gray-500">
                                        {{ $expense->keterangan ?: 'Tanpa keterangan' }}
                                    </p>
                                    <p class="text-[11px] text-gray-400">{{ $expense->tanggal_input->format('d M Y') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            <p class="font-bold text-gray-900">{{ $this->formatRupiah($expense->nominal) }}</p>

                            {{-- Status badge --}}
                            @php
                                $badge = $this->getStatusBadge($expense->status);
                                $colorMap = [
                                    'yellow' => 'bg-yellow-100 text-yellow-700',
                                    'green' => 'bg-green-100 text-green-700',
                                    'red' => 'bg-red-100 text-red-700',
                                    'gray' => 'bg-gray-100 text-gray-700',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $colorMap[$badge['color']] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $badge['label'] }}
                            </span>

                            {{-- Actions --}}
                            @if ($expense->status === 'pending')
                                <button
                                    type="button"
                                    wire:click="deletePengeluaran({{ $expense->id }})"
                                    wire:confirm="Yakin hapus pengeluaran ini?"
                                    class="text-xs font-medium text-red-600 hover:text-red-700">
                                    Hapus
                                </button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $this->expenses->links() }}
            </div>
        @else
            <div class="px-4 py-8 text-center">
                <x-heroicon-o-inbox class="mx-auto h-12 w-12 text-gray-300" />
                <p class="mt-2 text-sm text-gray-500">Belum ada pengeluaran</p>
            </div>
        @endif
    </div>
</div>
