@props([
    'src' => null,
    'name' => 'User',
])

@php
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = count($parts) > 1
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1))
        : strtoupper(substr($parts[0] ?? 'U', 0, 2));
@endphp

@if ($src)
    <img src="{{ $src }}" alt="{{ $name }} avatar" {{ $attributes->merge(['class' => 'w-7 h-7 rounded-full object-cover shrink-0']) }}>
@else
    <span {{ $attributes->merge(['class' => 'w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-xs font-semibold shrink-0']) }}>
        {{ $initials }}
    </span>
@endif
