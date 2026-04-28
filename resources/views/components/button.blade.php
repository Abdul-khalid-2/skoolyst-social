@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
])

@php
    $classes = [
        'primary' => 'bg-blue-600 hover:bg-blue-700 text-white',
        'secondary' => 'bg-gray-100 hover:bg-gray-200 text-gray-900',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white',
        'ghost' => 'bg-transparent hover:bg-gray-100 text-gray-700',
    ][$variant] ?? 'bg-blue-600 hover:bg-blue-700 text-white';

    $base = 'inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $base . ' ' . $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $base . ' ' . $classes]) }}>
        {{ $slot }}
    </button>
@endif
