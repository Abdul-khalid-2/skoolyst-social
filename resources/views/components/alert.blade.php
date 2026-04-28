@props([
    'variant' => 'info',
])

@php
    $classes = [
        'success' => 'bg-white border-emerald-200 text-emerald-900',
        'error' => 'bg-white border-red-200 text-red-900',
        'info' => 'bg-white border-blue-200 text-blue-900',
        'warning' => 'bg-white border-amber-200 text-amber-900',
    ][$variant] ?? 'bg-white border-blue-200 text-blue-900';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border p-4 text-sm font-medium ' . $classes]) }} role="alert">
    {{ $slot }}
</div>
