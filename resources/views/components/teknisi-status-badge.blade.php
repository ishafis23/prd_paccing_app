@props(['status'])

@php
    if ($status instanceof \App\Enums\OrderStatus) {
        $nilai = $status->value;
    } else {
        $nilai = (string) $status;
    }

    $warna = [
        'baru' => 'bg-gray-100 text-gray-600',
        'terjadwal' => 'bg-blue-50 text-blue-700',
        'menuju_lokasi' => 'bg-amber-50 text-amber-700',
        'dikerjakan' => 'bg-indigo-50 text-indigo-700',
        'selesai' => 'bg-emerald-50 text-emerald-700',
        'butuh_followup' => 'bg-orange-50 text-orange-700',
        'terkendala' => 'bg-rose-50 text-rose-700',
        'batal' => 'bg-red-50 text-red-700',
    ];

    $label = ucwords(str_replace('_', ' ', $nilai));
    $kelas = $warna[$nilai] ?? 'bg-gray-100 text-gray-600';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold leading-none '.$kelas]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ $label }}
</span>
