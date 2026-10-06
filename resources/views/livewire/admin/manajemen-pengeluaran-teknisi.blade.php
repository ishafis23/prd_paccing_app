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
    <div class="flex flex-wrap gap-3">
        {{-- Pending total --}}
        <div class="min-w-[9rem] flex-1 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Menunggu Persetujuan</p>
            <p class="mt-1 text-lg font-bold text-yellow-600">{{ $this->summaryBulan['count_pending'] ?? 0 }}</p>
            <p class="text-xs text-gray-400">{{ $this->formatRupiah($this->summaryBulan['total_pending'] ?? 0) }}</p>
        </div>

        {{-- Approved total --}}
        <div class="min-w-[9rem] flex-1 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Sudah Disetujui</p>
            <p class="mt-1 text-lg font-bold text-green-600">{{ $this->summaryBulan['count_approved'] ?? 0 }}</p>
            <p class="text-xs text-gray-400">{{ $this->formatRupiah($this->summaryBulan['total_approved'] ?? 0) }}</p>
        </div>

        {{-- Rejected total --}}
        <div class="min-w-[9rem] flex-1 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Ditolak</p>
            <p class="mt-1 text-lg font-bold text-red-600">{{ $this->summaryBulan['total_rejected'] ?? 0 }}</p>
        </div>

        {{-- Month total --}}
        <div class="min-w-[9rem] flex-1 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-100">
            <p class="text-xs font-medium text-gray-500">Total Bulan Ini</p>
            <p class="mt-1 text-lg font-bold text-blue-600">
                {{ $this->formatRupiah(($this->summaryBulan['total_pending'] ?? 0) + ($this->summaryBulan['total_approved'] ?? 0)) }}
            </p>
        </div>
    </div>

    {{-- Tab utama + tombol filter --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex gap-1 rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-100">
            @foreach (['daftar' => 'Daftar', 'rekap' => 'Rekap Kategori'] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('tampilan', '{{ $key }}')"
                    class="rounded-lg px-4 py-2 text-xs font-semibold transition {{ $tampilan === $key ? 'bg-gray-800 text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <span class="text-xs font-semibold text-gray-600">{{ \Carbon\Carbon::createFromFormat('Y-m', $filterBulan)->translatedFormat('F Y') }}</span>

        <button
            type="button"
            wire:click="$set('tampilFilter', true)"
            title="Filter"
            class="relative ml-auto rounded-lg bg-white p-2 text-gray-600 shadow-sm ring-1 ring-gray-100 hover:bg-gray-50">
            <x-heroicon-o-funnel class="h-5 w-5" />
            @if ($this->jumlahFilterAktif > 0)
                <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary-600 text-[10px] font-bold text-white">{{ $this->jumlahFilterAktif }}</span>
            @endif
        </button>
    </div>

    @if ($tampilan === 'daftar')
        {{-- Tab status --}}
        @php
            $tabs = [
                'pending' => ['Menunggu', $this->summaryBulan['count_pending'] ?? 0],
                'approved' => ['Disetujui', $this->summaryBulan['count_approved'] ?? 0],
                'rejected' => ['Ditolak', $this->summaryBulan['count_rejected'] ?? 0],
                '' => ['Semua', null],
            ];
        @endphp
        <div class="flex flex-wrap gap-1 rounded-xl bg-white p-1 shadow-sm ring-1 ring-gray-100">
            @foreach ($tabs as $key => [$label, $count])
                <button
                    type="button"
                    wire:click="$set('filterStatus', '{{ $key }}')"
                    class="rounded-lg px-4 py-2 text-xs font-semibold transition {{ $filterStatus === (string) $key ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-50' }}">
                    {{ $label }}@if ($count !== null) <span class="ml-1 opacity-80">({{ $count }})</span>@endif
                </button>
            @endforeach
        </div>
    @else
        {{-- Rekap per kategori --}}
        <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-100">
            <p class="px-4 pt-3 text-xs text-gray-500">Ke mana uang pengeluaran teknisi bulan ini dipakai. Total = menunggu + disetujui (yang ditolak tidak dihitung).</p>
            <table class="mt-2 w-full">
                <thead class="border-b border-gray-100 bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Kategori</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Item</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Menunggu</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Disetujui</th>
                        <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse (collect($this->kategoriBreakdown)->filter(fn ($d) => $d['count'] > 0) as $kat => $data)
                        <tr>
                            <td class="px-4 py-2 text-xs font-medium text-gray-800">{{ $this->getKategoriLabel($kat) }}</td>
                            <td class="px-4 py-2 text-right text-xs text-gray-500">{{ $data['count'] }}</td>
                            <td class="px-4 py-2 text-right text-xs text-yellow-600">{{ $this->formatRupiah($data['pending']) }}</td>
                            <td class="px-4 py-2 text-right text-xs text-green-600">{{ $this->formatRupiah($data['approved']) }}</td>
                            <td class="px-4 py-2 text-right text-xs font-bold text-gray-900">{{ $this->formatRupiah($data['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">Tidak ada data bulan ini</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- Modal filter --}}
    @if ($tampilFilter)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h2 class="font-bold text-gray-900">Filter</h2>
                    <button type="button" wire:click="$set('tampilFilter', false)" class="text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600">Bulan</label>
                    <input type="month" wire:model.live="filterBulan" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Teknisi</label>
                    <select wire:model.live="filterTeknisi" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-sm">
                        <option value="">Semua</option>
                        @foreach ($this->teknisList as $tech)
                            <option value="{{ $tech->id }}">{{ $tech->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Kategori</label>
                    <select wire:model.live="filterKategori" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1 text-sm">
                        <option value="">Semua</option>
                        <option value="bensin">⛽ Bensin</option>
                        <option value="makan">🍜 Makan</option>
                        <option value="material">🔧 Material</option>
                        <option value="transport">🚗 Transport</option>
                        <option value="lainnya">📦 Lainnya</option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="button" wire:click="resetFilter" class="flex-1 rounded-lg border border-gray-300 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</button>
                    <button type="button" wire:click="$set('tampilFilter', false)" class="flex-1 rounded-lg bg-primary-600 py-2 text-sm font-semibold text-white hover:bg-primary-500">Selesai</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Expenses Table --}}
    @if ($tampilan === 'daftar')
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
                                {{ $expense->teknisi->name ?? '-' }}
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <span>{{ $this->getKategoriLabel($expense->kategori) }}</span>
                                @if ($expense->keterangan)
                                    <p class="text-[11px] text-gray-500">{{ \Illuminate\Support\Str::limit($expense->keterangan, 40) }}</p>
                                @endif
                                @if ($expense->qty && $expense->harga)
                                    <p class="text-[11px] text-gray-400">{{ $expense->qty }} × {{ $this->formatRupiah($expense->harga) }}</p>
                                @endif
                                @if ($expense->order_id)
                                    <p class="text-[11px] font-medium text-blue-500">Order #{{ $expense->order_id }}</p>
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
                                <div class="flex gap-1">
                                    @if ($expense->status !== 'approved')
                                        <button
                                            type="button"
                                            wire:click="quickApprove({{ $expense->id }})"
                                            wire:loading.attr="disabled"
                                            class="rounded px-2 py-1 font-medium text-green-600 hover:bg-green-50 disabled:opacity-50">
                                            Setuju
                                        </button>
                                    @endif
                                    @if ($expense->status !== 'rejected')
                                        <button
                                            type="button"
                                            wire:click="openApproval({{ $expense->id }})"
                                            class="rounded px-2 py-1 font-medium text-red-600 hover:bg-red-50">
                                            Tolak
                                        </button>
                                    @endif
                                </div>
                                @if ($expense->status === 'rejected' && $expense->catatan_approval)
                                    <p class="mt-1 text-[11px] text-red-500">Alasan: {{ $expense->catatan_approval }}</p>
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
    @endif

    {{-- Approval Modal --}}
    @if ($selectedExpense)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl">
                {{-- Header --}}
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="font-bold text-gray-900">
                            Tolak Pengeluaran
                        </h2>
                        <p class="text-xs text-gray-500">Teknisi: {{ $selectedExpense->teknisi->name }}</p>
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
                    @if ($selectedExpense->order_id)
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Order:</span>
                            <span class="text-xs font-medium text-blue-600">#{{ $selectedExpense->order_id }}</span>
                        </div>
                    @endif
                    @if ($selectedExpense->qty && $selectedExpense->harga)
                        <div class="flex justify-between">
                            <span class="text-xs text-gray-600">Qty × Harga:</span>
                            <span class="text-xs font-medium text-gray-900">{{ $selectedExpense->qty }} × {{ $this->formatRupiah($selectedExpense->harga) }}</span>
                        </div>
                    @endif
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

                    {{-- Action buttons --}}
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="flex-1 rounded-lg bg-red-600 py-2 font-semibold text-white disabled:opacity-50">
                            <span wire:loading.remove>Tolak</span>
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
