<div class="space-y-4">
    {{-- Page header --}}
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Manajemen Pengeluaran Teknisi</h1>
        <p class="text-sm text-gray-500">Tinjau dan setujui pengeluaran yang dilaporkan oleh teknisi</p>
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

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        {{-- Pending total --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Menunggu Persetujuan</p>
            <p class="mt-1 text-lg font-bold text-yellow-600">{{ $this->summaryBulan['count_pending'] ?? 0 }}</p>
            <p class="text-xs text-gray-400">{{ $this->formatRupiah($this->summaryBulan['total_pending'] ?? 0) }}</p>
        </div>

        {{-- Approved total --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Sudah Disetujui</p>
            <p class="mt-1 text-lg font-bold text-green-600">{{ $this->summaryBulan['count_approved'] ?? 0 }}</p>
            <p class="text-xs text-gray-400">{{ $this->formatRupiah($this->summaryBulan['total_approved'] ?? 0) }}</p>
        </div>

        {{-- Rejected total --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Ditolak</p>
            <p class="mt-1 text-lg font-bold text-red-600">{{ $this->summaryBulan['total_rejected'] ?? 0 }}</p>
        </div>

        {{-- Month total --}}
        <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Total Bulan Ini</p>
            <p class="mt-1 text-lg font-bold text-blue-600">
                {{ $this->formatRupiah(($this->summaryBulan['total_pending'] ?? 0) + ($this->summaryBulan['total_approved'] ?? 0)) }}
            </p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
        <div class="grid grid-cols-2 gap-2 md:grid-cols-5">
            {{-- Month filter --}}
            <div>
                <label class="block text-xs font-medium text-gray-600">Bulan</label>
                <input
                    type="month"
                    wire:model.live="filterBulan"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs">
            </div>

            {{-- Status filter --}}
            <div>
                <label class="block text-xs font-medium text-gray-600">Status</label>
                <select wire:model.live="filterStatus" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs">
                    <option value="pending">Menunggu</option>
                    <option value="approved">Disetujui</option>
                    <option value="rejected">Ditolak</option>
                    <option value="">Semua</option>
                </select>
            </div>

            {{-- Teknisi filter --}}
            <div>
                <label class="block text-xs font-medium text-gray-600">Teknisi</label>
                <select wire:model.live="filterTeknisi" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-xs">
                    <option value="">Semua</option>
                    @foreach ($this->teknisList as $tech)
                        <option value="{{ $tech->id }}">{{ $tech->nama }}</option>
                    @endforeach
                </select>
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

            {{-- Reset button --}}
            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="$reset(['filterBulan', 'filterTeknisi', 'filterKategori', 'filterStatus'])"
                    class="w-full rounded-lg border border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">
                    Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Category Breakdown --}}
    @if ($this->kategoriBreakdown)
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-100">
            <h2 class="mb-3 font-bold text-gray-900">Breakdown per Kategori ({{ $filterBulan }})</h2>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-5">
                @foreach ($this->kategoriBreakdown as $kat => $data)
                    @if ($data['count'] > 0)
                        <div class="rounded-lg bg-gray-50 p-2">
                            <p class="font-medium text-gray-800">{{ $this->getKategoriLabel($kat) }}</p>
                            <p class="text-xs font-bold text-gray-900">{{ $this->formatRupiah($data['total']) }}</p>
                            <p class="text-[11px] text-gray-500">{{ $data['count'] }} item</p>
                            @if ($data['pending'] > 0)
                                <p class="text-[11px] text-yellow-600">{{ $this->formatRupiah($data['pending']) }} pending</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Expenses Table --}}
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
        @if ($this->expenses->isNotEmpty())
            <table class="w-full">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Teknisi</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Kategori</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Tanggal</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Nominal</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Status</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($this->expenses as $expense)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-xs font-medium text-gray-900">
                                {{ $expense->teknisi->nama ?? '-' }}
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <span>{{ $this->getKategoriLabel($expense->kategori) }}</span>
                                @if ($expense->keterangan)
                                    <p class="text-[11px] text-gray-500">{{ substr($expense->keterangan, 0, 40) }}...</p>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500">
                                {{ $expense->tanggal_input->format('d M Y') }}
                            </td>
                            <td class="px-4 py-2 text-right text-xs font-bold text-gray-900">
                                {{ $this->formatRupiah($expense->nominal) }}
                            </td>
                            <td class="px-4 py-2">
                                @php
                                    $badge = $this->getStatusBadge($expense->status);
                                    $colorMap = [
                                        'yellow' => 'bg-yellow-100 text-yellow-700',
                                        'green' => 'bg-green-100 text-green-700',
                                        'red' => 'bg-red-100 text-red-700',
                                    ];
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $colorMap[$badge['color']] ?? 'bg-gray-100 text-gray-700' }}">
                                    {{ $badge['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                @if ($expense->status === 'pending')
                                    <div class="flex gap-1">
                                        <button
                                            type="button"
                                            wire:click="openApproval({{ $expense->id }}, 'approve')"
                                            class="rounded px-2 py-1 text-green-600 hover:bg-green-50 font-medium">
                                            Setuju
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="openApproval({{ $expense->id }}, 'reject')"
                                            class="rounded px-2 py-1 text-red-600 hover:bg-red-50 font-medium">
                                            Tolak
                                        </button>
                                    </div>
                                @else
                                    <button
                                        type="button"
                                        wire:click="$set('selectedExpense', $expense)"
                                        class="text-xs text-blue-600 hover:text-blue-700 font-medium">
                                        Detail
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Pagination --}}
            <div class="border-t border-gray-100 px-4 py-3">
                {{ $this->expenses->links() }}
            </div>
        @else
            <div class="px-4 py-8 text-center">
                <x-heroicon-o-inbox class="mx-auto h-12 w-12 text-gray-300" />
                <p class="mt-2 text-sm text-gray-500">Tidak ada pengeluaran</p>
            </div>
        @endif
    </div>

    {{-- Approval Modal --}}
    @if ($selectedExpense)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                {{-- Header --}}
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-bold text-gray-900">
                            {{ $approvalAction === 'approve' ? 'Setujui' : 'Tolak' }} Pengeluaran
                        </h2>
                        <p class="text-xs text-gray-500">Teknisi: {{ $selectedExpense->teknisi->nama }}</p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeApproval"
                        class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                {{-- Details --}}
                <div class="space-y-2 rounded-lg bg-gray-50 p-3">
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-600">Kategori:</span>
                        <span class="text-xs font-medium text-gray-900">{{ $this->getKategoriLabel($selectedExpense->kategori) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-600">Tanggal:</span>
                        <span class="text-xs font-medium text-gray-900">{{ $selectedExpense->tanggal_input->format('d M Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs text-gray-600">Keterangan:</span>
                        <span class="text-xs font-medium text-gray-900">{{ $selectedExpense->keterangan ?: '-' }}</span>
                    </div>
                    <div class="border-t border-gray-200 pt-2">
                        <span class="text-xs text-gray-600">Nominal:</span>
                        <p class="text-lg font-bold text-gray-900">{{ $this->formatRupiah($selectedExpense->nominal) }}</p>
                    </div>
                </div>

                {{-- Approval Form --}}
                <form wire:submit="submitApproval" class="space-y-3">
                    @if ($approvalAction === 'approve')
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Nominal Disetujui (Rp)</label>
                            <input
                                type="number"
                                wire:model="approvalNominal"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                            @error('approvalNominal')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Alasan Penolakan</label>
                            <textarea
                                wire:model="approvalCatatan"
                                rows="3"
                                placeholder="Jelaskan alasan penolakan..."
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></textarea>
                            @error('approvalCatatan')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    {{-- Optional notes for approve --}}
                    @if ($approvalAction === 'approve')
                        <div>
                            <label class="block text-xs font-medium text-gray-700">Catatan (Opsional)</label>
                            <input
                                type="text"
                                wire:model="approvalCatatan"
                                placeholder="Contoh: Disesuaikan karena..."
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        </div>
                    @endif

                    {{-- Action buttons --}}
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="flex-1 rounded-lg {{ $approvalAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} py-2 font-semibold text-white disabled:opacity-50">
                            <span wire:loading.remove>{{ $approvalAction === 'approve' ? 'Setujui' : 'Tolak' }}</span>
                            <span wire:loading><x-heroicon-o-arrow-path class="inline h-4 w-4 animate-spin" /></span>
                        </button>
                        <button
                            type="button"
                            wire:click="closeApproval"
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 font-semibold text-gray-700 hover:bg-gray-50">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
