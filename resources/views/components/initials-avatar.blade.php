@props(['name', 'size' => 'h-12 w-12 text-base'])

@php
    $initials = collect(explode(' ', trim($name)))
        ->map(fn ($w) => mb_substr($w, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<span {{ $attributes->merge(['class' => "$size flex items-center justify-center rounded-full bg-blue-600 font-semibold text-white"]) }}>
    {{ mb_strtoupper($initials) }}
</span>
