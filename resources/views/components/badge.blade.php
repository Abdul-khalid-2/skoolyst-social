@props([
    'variant' => 'secondary',
])

@php
    $classes = [
        'primary' => 'bg-blue-50 text-blue-600',
        'secondary' => 'bg-gray-100 text-gray-700',
        'success' => 'bg-emerald-50 text-emerald-600',
        'warning' => 'bg-amber-50 text-amber-700',
        'danger' => 'bg-red-50 text-red-600',
        'ghost' => 'bg-transparent text-gray-600',
    ][$variant] ?? 'bg-gray-100 text-gray-700';
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' . $classes]) }}>
    {{ $slot }}
</span>
