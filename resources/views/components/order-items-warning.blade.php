<div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 dark:border-red-400 p-4 rounded-r-lg mb-4">
    <div class="flex gap-3">
        <div class="flex-shrink-0">
            <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div class="flex-1">
            <h3 class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">
                ⚠️ Peringatan: Discrepancy Total
            </h3>
            <p class="text-sm text-red-700 dark:text-red-400 leading-relaxed">
                Total yang dihitung dari item-item (Rp{{ number_format($totalCalculated, 0, ',', '.') }})
                <strong>tidak sama</strong> dengan Total Tagihan yang ditampilkan (Rp{{ number_format($totalActual, 0, ',', '.') }}).
            </p>
            <p class="text-sm text-red-700 dark:text-red-400 mt-2">
                <strong>Penyebab kemungkinan:</strong> Jumlah (quantity) di salah satu item tidak sesuai.
                Periksa kembali data item layanan di atas, terutama nilai "Jumlah" yang terlihat tidak biasa.
            </p>
            <p class="text-sm text-red-600 dark:text-red-300 mt-3 font-semibold">
                Selisih: Rp{{ number_format(abs($totalActual - $totalCalculated), 0, ',', '.') }}
            </p>
        </div>
    </div>
</div>
