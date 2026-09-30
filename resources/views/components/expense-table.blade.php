<div class="overflow-x-auto">
    {{-- Edit Modal --}}
    <div id="expenseEditModal" class="hidden fixed inset-0 bg-black/50 z-[100] flex items-center justify-center" onclick="if(event.target === this) closeExpenseEdit()">
        <div class="bg-white dark:bg-slate-800 rounded-lg shadow-2xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="sticky top-0 bg-white dark:bg-slate-800 px-6 py-4 flex items-center justify-between" style="border-bottom:1px solid #e2e8f0;">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit Pengeluaran</h3>
                <button type="button" onclick="closeExpenseEdit()" class="text-slate-500 hover:text-slate-700">✕</button>
            </div>

            <form onsubmit="submitExpenseEdit(event)" class="p-6" style="display:flex;flex-direction:column;gap:16px;">
                <input type="hidden" id="expenseEditId">

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" style="margin-bottom:6px;">Kategori</label>
                    <select id="expenseEditKategori" class="w-full" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
                        <option value="material">Material</option>
                        <option value="perawatan">Perawatan</option>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" style="margin-bottom:6px;">Qty</label>
                        <input id="expenseEditQty" type="number" min="1" oninput="expenseRecalc()" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" style="margin-bottom:6px;">Harga Satuan</label>
                        <input id="expenseEditHarga" type="number" min="0" oninput="expenseRecalc()" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" style="margin-bottom:6px;">Total (Rp)</label>
                    <input id="expenseEditNominal" type="number" min="1" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
                    <p style="margin-top:4px;font-size:11px;color:#94a3b8;">Otomatis Qty × Harga, bisa dikoreksi.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" style="margin-bottom:6px;">Tanggal</label>
                    <input id="expenseEditTanggal" type="date" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300" style="margin-bottom:6px;">Keterangan</label>
                    <textarea id="expenseEditKeterangan" rows="3" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;"></textarea>
                </div>

                <div style="display:flex;gap:12px;padding-top:16px;border-top:1px solid #e2e8f0;">
                    <button type="button" onclick="closeExpenseEdit()" class="font-medium" style="flex:1;padding:12px 16px;border:1px solid #cbd5e1;border-radius:8px;background:#ffffff;color:#334155;cursor:pointer;">Batal</button>
                    <button type="submit" class="font-bold" style="flex:1;padding:12px 16px;border:1px solid #047857;border-radius:8px;background:#059669;color:#ffffff;cursor:pointer;">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f1f5f9;">
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:left;font-size:13px;">Tanggal</th>
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:left;font-size:13px;">Kategori</th>
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:left;font-size:13px;">Keterangan</th>
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:right;font-size:13px;">Qty</th>
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:right;font-size:13px;">Harga</th>
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:right;font-size:13px;">Total</th>
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:left;font-size:13px;">Dicatat oleh</th>
                <th style="border:1px solid #e2e8f0;padding:10px;text-align:center;font-size:13px;width:130px;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse (($getState() ?? []) as $item)
                <tr>
                    <td style="border:1px solid #e2e8f0;padding:10px;font-size:13px;">{{ optional($item->tanggal)->format('d M Y') }}</td>
                    <td style="border:1px solid #e2e8f0;padding:10px;font-size:13px;">{{ ucfirst($item->kategori->value) }}</td>
                    <td style="border:1px solid #e2e8f0;padding:10px;font-size:13px;">{{ $item->keterangan ?: '—' }}</td>
                    <td style="border:1px solid #e2e8f0;padding:10px;text-align:right;font-size:13px;">{{ $item->qty ?? '—' }}</td>
                    <td style="border:1px solid #e2e8f0;padding:10px;text-align:right;font-size:13px;">Rp{{ number_format((float) $item->harga, 0, ',', '.') }}</td>
                    <td style="border:1px solid #e2e8f0;padding:10px;text-align:right;font-size:13px;font-weight:600;">Rp{{ number_format((float) $item->nominal, 0, ',', '.') }}</td>
                    <td style="border:1px solid #e2e8f0;padding:10px;font-size:13px;">{{ $item->recordedBy?->name ?? '—' }}</td>
                    <td style="border:1px solid #e2e8f0;padding:10px;text-align:center;white-space:nowrap;">
                        <button type="button" title="Edit"
                            onclick="openExpenseEdit({{ $item->id }}, '{{ $item->kategori->value }}', {{ (int) $item->qty }}, {{ (float) $item->harga }}, {{ (float) $item->nominal }}, '{{ optional($item->tanggal)->format('Y-m-d') }}', '{{ addslashes($item->keterangan ?? '') }}')"
                            style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;background:#ffffff;color:#1d4ed8;cursor:pointer;font-size:12px;margin-right:6px;">
                            Edit
                        </button>
                        <button type="button" title="Hapus" onclick="deleteExpense({{ $item->id }})"
                            style="padding:6px 10px;border:1px solid #fecaca;border-radius:6px;background:#ffffff;color:#dc2626;cursor:pointer;font-size:12px;">
                            Hapus
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="border:1px solid #e2e8f0;padding:16px;text-align:center;font-size:13px;color:#94a3b8;">Belum ada pengeluaran material/perawatan</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <script>
        const expenseBaseUrl = @json(rtrim(request()->getBaseUrl(), '/'));

        function expenseRecalc() {
            const qty = parseFloat(document.getElementById('expenseEditQty').value) || 0;
            const harga = parseFloat(document.getElementById('expenseEditHarga').value) || 0;
            document.getElementById('expenseEditNominal').value = Math.round(qty * harga);
        }

        function openExpenseEdit(id, kategori, qty, harga, nominal, tanggal, keterangan) {
            document.getElementById('expenseEditId').value = id;
            document.getElementById('expenseEditKategori').value = kategori;
            document.getElementById('expenseEditQty').value = qty;
            document.getElementById('expenseEditHarga').value = harga;
            document.getElementById('expenseEditNominal').value = nominal;
            document.getElementById('expenseEditTanggal').value = tanggal;
            document.getElementById('expenseEditKeterangan').value = keterangan;
            document.getElementById('expenseEditModal').classList.remove('hidden');
        }

        function closeExpenseEdit() {
            document.getElementById('expenseEditModal').classList.add('hidden');
        }

        async function submitExpenseEdit(event) {
            event.preventDefault();
            const id = document.getElementById('expenseEditId').value;
            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            const payload = {
                kategori: document.getElementById('expenseEditKategori').value,
                qty: parseInt(document.getElementById('expenseEditQty').value),
                harga: parseFloat(document.getElementById('expenseEditHarga').value),
                nominal: parseFloat(document.getElementById('expenseEditNominal').value),
                tanggal: document.getElementById('expenseEditTanggal').value,
                keterangan: document.getElementById('expenseEditKeterangan').value,
            };

            try {
                const response = await fetch(`${expenseBaseUrl}/expenses/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const text = await response.text();

                if (response.ok) {
                    alert('Pengeluaran diperbarui');
                    closeExpenseEdit();
                    location.reload();
                } else {
                    let msg = text.substring(0, 150);
                    try { msg = JSON.parse(text).message || msg; } catch (e) {}
                    alert('Gagal: ' + msg);
                }
            } catch (error) {
                alert('Terjadi kesalahan: ' + error.message);
            }
        }

        async function deleteExpense(id) {
            if (!confirm('Yakin hapus pengeluaran ini?')) return;

            try {
                const response = await fetch(`${expenseBaseUrl}/expenses/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                if (response.ok) {
                    alert('Pengeluaran dihapus');
                    location.reload();
                } else {
                    alert('Gagal menghapus pengeluaran');
                }
            } catch (error) {
                alert('Terjadi kesalahan: ' + error.message);
            }
        }
    </script>
</div>
