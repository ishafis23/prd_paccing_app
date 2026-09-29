<div class="space-y-6 p-4">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Laporan Harian</h1>
            <p class="text-gray-600 mt-1">{{ $tanggal }}</p>
        </div>
        <div class="text-sm">
            <span class="px-3 py-1 rounded-full font-semibold"
                  :class="$currentReportId && !@js($currentReportId) ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'">
                {{ $currentReportId && request()->route()->getName() === 'teknisi.daily-report' ? 'Draft' : 'Baru' }}
            </span>
        </div>
    </div>

    <!-- Date Picker -->
    <div class="grid grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700">Tanggal</label>
            <input type="date"
                   wire:model.live="tanggal"
                   class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <div class="flex gap-8">
            <button wire:click="$set('activeTab', 'pengerjaan')"
                    class="px-4 py-2 font-medium text-sm border-b-2 transition"
                    :class="activeTab === 'pengerjaan'
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-600 hover:text-gray-900'">
                📋 Laporan Pengerjaan
            </button>
            <button wire:click="$set('activeTab', 'dana')"
                    class="px-4 py-2 font-medium text-sm border-b-2 transition"
                    :class="activeTab === 'dana'
                        ? 'border-blue-500 text-blue-600'
                        : 'border-transparent text-gray-600 hover:text-gray-900'">
                💰 Laporan Dana
            </button>
        </div>
    </div>

    <!-- TAB: PENGERJAAN -->
    @if($activeTab === 'pengerjaan')
    <div class="space-y-6">
        <!-- Add Work Entry Form -->
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-semibold">Tambah Titik Pengerjaan</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Titik/Poin *</label>
                    <input type="text"
                           wire:model="titik"
                           placeholder="Contoh: Titik 1"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('titik') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nama Customer *</label>
                    <input type="text"
                           wire:model="customer_name"
                           placeholder="Contoh: RedDoorz Unhas"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                    @error('customer_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Catatan/Status</label>
                <textarea wire:model="notes"
                          placeholder="Contoh: Mau dijadwalkan tgl 14"
                          rows="3"
                          class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2"></textarea>
                @error('notes') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <button wire:click="addWorkEntry"
                    class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700">
                ✓ Tambah Titik
            </button>
        </div>

        <!-- Work Entries List -->
        @if(!empty($workEntries))
        <div class="space-y-3">
            <h3 class="font-semibold text-gray-900">Daftar Pengerjaan ({{ count($workEntries) }} titik)</h3>
            @foreach($workEntries as $entry)
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 space-y-2">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-semibold text-gray-900">● {{ $entry['titik'] }}</p>
                        <p class="text-gray-700">{{ $entry['customer_name'] }}</p>
                        @if($entry['notes'])
                        <p class="text-gray-600 text-sm mt-1">{{ $entry['notes'] }}</p>
                        @endif
                    </div>
                    <button wire:click="deleteWorkEntry({{ $entry['id'] }})"
                            wire:confirm="Hapus titik ini?"
                            class="text-red-600 hover:text-red-800 text-sm font-medium">
                        ✕ Hapus
                    </button>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center py-8 text-gray-500">
            Belum ada titik pengerjaan ditambahkan
        </div>
        @endif

        <!-- AC Units Section -->
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-semibold">AC Unit Tambahan</h2>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Customer *</label>
                    <input type="text"
                           wire:model="ac_customer_name"
                           placeholder="Nama customer"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Jumlah Unit *</label>
                    <input type="number"
                           wire:model="ac_jumlah"
                           min="1"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select wire:model="ac_status"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="pending">Pending</option>
                        <option value="cuci">Cuci</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Catatan</label>
                <input type="text"
                       wire:model="ac_catatan"
                       placeholder="Optional"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>

            <button wire:click="addAcUnit"
                    class="w-full bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700">
                ✓ Tambah AC Unit
            </button>
        </div>

        @if(!empty($acUnits))
        <div class="space-y-2">
            <h3 class="font-semibold text-gray-900">AC Units ({{ count($acUnits) }})</h3>
            @foreach($acUnits as $ac)
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 flex justify-between items-center">
                <div>
                    <p class="font-medium">{{ $ac['customer_name'] }} - {{ $ac['jumlah_unit'] }} unit</p>
                    <p class="text-sm text-gray-600">Status: <span class="font-semibold">{{ $ac['status'] }}</span></p>
                </div>
                <button wire:click="deleteAcUnit({{ $ac['id'] }})"
                        wire:confirm="Hapus?"
                        class="text-red-600 text-sm hover:text-red-800">✕</button>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    <!-- TAB: DANA -->
    @if($activeTab === 'dana')
    <div class="space-y-6">
        <!-- Pendapatan Section -->
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-semibold">💵 Pendapatan</h2>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ambil dari Warung/Base (Rp)</label>
                    <input type="number"
                           wire:model.live="saldo_awal"
                           min="0"
                           step="1000"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Dana dari Customer (Rp)</label>
                    <input type="number"
                           wire:model.live="pendapatan_customer"
                           min="0"
                           step="1000"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>

            <div class="bg-blue-50 p-4 rounded-lg">
                <p class="text-sm text-gray-600">Total Pendapatan:</p>
                <p class="text-2xl font-bold text-blue-600">Rp {{ number_format($totalPendapatan, 0, ',', '.') }}</p>
            </div>
        </div>

        <!-- Pengeluaran Section -->
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-semibold">🛣️ Pengeluaran</h2>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Kategori *</label>
                    <select wire:model="expense_kategori"
                            class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="makan">🍽️ Makan</option>
                        <option value="bensin">⛽ Bensin</option>
                        <option value="minum">🥤 Minum</option>
                        <option value="lainnya">📦 Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nominal (Rp) *</label>
                    <input type="number"
                           wire:model="expense_nominal"
                           min="1000"
                           max="5000000"
                           step="1000"
                           class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div class="flex flex-col justify-end">
                    <button wire:click="addExpense"
                            class="bg-blue-600 text-white py-2 rounded-lg font-medium hover:bg-blue-700">
                        ✓ Tambah
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Keterangan</label>
                <input type="text"
                       wire:model="expense_keterangan"
                       placeholder="Optional"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
        </div>

        <!-- Expenses List -->
        @if(!empty($expenses))
        <div class="space-y-2">
            <h3 class="font-semibold text-gray-900">Daftar Pengeluaran</h3>
            @foreach($expenses as $expense)
            <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 flex justify-between items-center">
                <div>
                    <p class="font-medium">{{ ucfirst($expense['kategori']) }}</p>
                    <p class="text-gray-600">Rp {{ number_format($expense['nominal'], 0, ',', '.') }}</p>
                    @if($expense['keterangan'])
                    <p class="text-xs text-gray-500">{{ $expense['keterangan'] }}</p>
                    @endif
                </div>
                <button wire:click="deleteExpense({{ $expense['id'] }})"
                        wire:confirm="Hapus?"
                        class="text-red-600 text-sm hover:text-red-800">✕</button>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Setoran Section -->
        <div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
            <h2 class="text-lg font-semibold">💳 Setoran</h2>

            <div class="bg-yellow-50 p-4 rounded-lg space-y-2">
                <p class="text-sm text-gray-600">Total Pengeluaran:</p>
                <p class="text-xl font-semibold">Rp {{ number_format($totalPengeluaran, 0, ',', '.') }}</p>
            </div>

            <div class="bg-green-50 p-4 rounded-lg space-y-2">
                <p class="text-sm text-gray-600">Hasil Perhitungan (Pendapatan - Pengeluaran):</p>
                <p class="text-2xl font-bold text-green-600">Rp {{ number_format($totalSetoran, 0, ',', '.') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Jumlah Setoran (Rp)</label>
                <input type="number"
                       wire:model="jumlah_setoran"
                       min="0"
                       step="1000"
                       placeholder="Auto fill dari hasil perhitungan"
                       class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Jika ada sisa cash yang dipegang</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Submit Button -->
    @if($currentReportId)
    <div class="flex gap-3">
        <button wire:click="submitReport"
                wire:loading.attr="disabled"
                class="flex-1 bg-green-600 text-white py-3 rounded-lg font-medium hover:bg-green-700 disabled:bg-gray-400">
            <span wire:loading.remove>✓ Submit Laporan</span>
            <span wire:loading>Mengirim...</span>
        </button>
    </div>
    @endif
</div>
