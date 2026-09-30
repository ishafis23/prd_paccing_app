@php
    $state = $getState();
    $total = $state['total'] ?? 0;
@endphp

<div class="flex items-center justify-between gap-3">
    <div>
        <p class="text-sm font-semibold text-slate-600 dark:text-slate-400">Total Tagihan</p>
        <p class="text-2xl font-bold text-green-600 dark:text-green-400">Rp{{ number_format($total, 0, ',', '.') }}</p>
    </div>
    <a href="#" class="inline-flex items-center justify-center w-9 h-9 rounded text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30" title="Koreksi Total">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
    </a>
</div>
