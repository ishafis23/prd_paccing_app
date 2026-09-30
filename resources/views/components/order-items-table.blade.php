<div class="overflow-x-auto">
    <div class="hidden" x-data="{
        editModal: { open: false, data: { id: null, nama_layanan: '', jumlah: 0, harga: 0, catatan: '' } },
        async submitEdit() {
            try {
                const response = await fetch(`/api/order-items/${this.editModal.data.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
                    },
                    body: JSON.stringify({
                        jumlah: this.editModal.data.jumlah,
                        harga: this.editModal.data.harga,
                        catatan: this.editModal.data.catatan
                    })
                });
                if (response.ok) {
                    alert('Item berhasil diupdate');
                    this.editModal.open = false;
                    location.reload();
                } else {
                    const err = await response.json();
                    alert('Gagal: ' + (err.message || 'Update gagal'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
        },
        async deleteItem(id) {
            if (!confirm('Apakah Anda yakin ingin menghapus layanan ini?')) return;
            try {
                const response = await fetch(`/api/order-items/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content
                    }
                });
                if (response.ok) {
                    alert('Item berhasil dihapus');
                    location.reload();
                } else {
                    alert('Gagal menghapus item');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }
        }
    }">
    <!-- Edit Modal -->
    <div x-show="editModal.open" class="fixed inset-0 bg-black/50 z-[100] flex items-center justify-center" @click="editModal.open = false">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="sticky top-0 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit Layanan</h3>
                <button @click="editModal.open = false" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <form @submit.prevent="submitEdit" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Nama Layanan</label>
                    <input x-model="editModal.data.nama_layanan" type="text" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white" readonly>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Qty</label>
                        <input x-model.number="editModal.data.jumlah" type="number" min="1" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Harga</label>
                        <input x-model.number="editModal.data.harga" type="number" min="0" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Catatan</label>
                    <textarea x-model="editModal.data.catatan" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg dark:bg-slate-700 dark:text-white" rows="3"></textarea>
                </div>

                <div class="flex gap-3 pt-4">
                    <button type="button" @click="editModal.open = false" class="flex-1 px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                        Batal
                    </button>
                    <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
    </div>

    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-slate-100 dark:bg-slate-700">
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-center text-sm font-semibold text-slate-900 dark:text-white w-12">Aksi</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">Layanan</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">Kategori</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-white">Qty</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-white">Harga</th>
                <th class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-sm font-semibold text-slate-900 dark:text-white">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($getState() as $index => $item)
                <tr class="border-b border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    @if ($index > 0)
                    <td class="border border-slate-300 dark:border-slate-600 px-2 py-3 text-center w-12">
                        <div class="relative inline-block">
                            <button type="button" onclick="toggleDropdown(this, {{ $item->id }})" class="p-2 rounded hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors" title="Aksi">
                                <svg class="w-5 h-5 text-slate-600 dark:text-slate-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"></path>
                                </svg>
                            </button>
                            <div class="dropdown-menu hidden absolute left-0 mt-2 w-40 bg-white dark:bg-slate-800 rounded-lg shadow-xl z-50 border border-slate-200 dark:border-slate-700 overflow-hidden" data-item-id="{{ $item->id }}">
                                <button type="button" onclick="openEditModal({{ $item->id }}, '{{ addslashes($item->nama_layanan) }}', {{ $item->jumlah }}, {{ $item->harga }}, '{{ addslashes($item->catatan ?? '') }}')" class="w-full text-left px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Edit
                                </button>
                                <button type="button" onclick="deleteItem({{ $item->id }})" class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors flex items-center gap-2 border-t border-slate-200 dark:border-slate-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </td>
                    @else
                    <td class="border border-slate-300 dark:border-slate-600 px-2 py-3 w-12"></td>
                    @endif
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-slate-900 dark:text-white">
                        <div class="font-medium">{{ $item->nama_layanan }}</div>
                        @if ($item->acUnit)
                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $item->acUnit->labelTampil() }}</div>
                        @endif
                        @if ($item->catatan)
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $item->catatan }}</div>
                        @endif
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-slate-900 dark:text-white">
                        @if ($item->kategori)
                            <span class="inline-block px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded">
                                {{ $item->kategori->value }}
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white font-medium">
                        {{ number_format($item->jumlah, 0, ',', '.') }}
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white">
                        Rp{{ number_format($item->harga, 0, ',', '.') }}
                    </td>
                    <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white font-bold">
                        Rp{{ number_format($item->harga * $item->jumlah, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-center text-slate-500 dark:text-slate-400">
                        Tidak ada layanan
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="bg-green-50 dark:bg-green-900/20 font-bold">
                <td colspan="5" class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-slate-900 dark:text-white">
                    Total Keseluruhan:
                </td>
                <td class="border border-slate-300 dark:border-slate-600 px-4 py-3 text-right text-lg text-green-600 dark:text-green-400">
                    Rp{{ number_format(
                        collect($getState())->sum(fn ($item) => $item->harga * $item->jumlah),
                        0,
                        ',',
                        '.'
                    ) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <script>
        function toggleDropdown(button, itemId) {
            const dropdown = button.nextElementSibling;
            const isHidden = dropdown.classList.contains('hidden');

            // Close all dropdowns
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.add('hidden');
            });

            // Open this dropdown if it was closed
            if (isHidden) {
                dropdown.classList.remove('hidden');
            }
        }

        function openEditModal(id, nama, jumlah, harga, catatan) {
            const modal = document.querySelector('[x-data*="editModal"]');
            if (modal && modal.__x) {
                modal.__x.getUnobservedData().editModal.data = { id, nama_layanan: nama, jumlah, harga, catatan };
                modal.__x.getUnobservedData().editModal.open = true;
            }
            // Close dropdown
            document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
        }

        async function deleteItem(id) {
            if (!confirm('Apakah Anda yakin ingin menghapus layanan ini?')) return;

            try {
                const response = await fetch(`/api/order-items/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                if (response.ok) {
                    alert('Item berhasil dihapus');
                    location.reload();
                } else {
                    alert('Gagal menghapus item');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan: ' + error.message);
            }

            // Close dropdown
            document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown-menu') && !e.target.closest('button[onclick*="toggleDropdown"]')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
            }
        });
    </script>
</div>
