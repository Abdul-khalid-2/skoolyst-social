@props([
    'title',
    'value',
    'change' => 0,
    'changeLabel' => '',
    'color' => 'blue',
])

@php
    $colors = [
        'blue' => ['icon' => 'text-blue-600', 'ring' => 'bg-blue-100'],
        'emerald' => ['icon' => 'text-emerald-600', 'ring' => 'bg-emerald-100'],
        'amber' => ['icon' => 'text-amber-600', 'ring' => 'bg-amber-100'],
        'rose' => ['icon' => 'text-rose-600', 'ring' => 'bg-rose-100'],
    ][$color] ?? ['icon' => 'text-blue-600', 'ring' => 'bg-blue-100'];

    $positive = (float) $change >= 0;
@endphp

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-200 p-5 hover:shadow-md transition-shadow duration-200']) }}>
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $title }}</p>
            <p class="mt-2 text-2xl font-bold text-gray-900 tracking-tight">{{ $value }}</p>
        </div>
        <div class="w-10 h-10 rounded-lg {{ $colors['ring'] }} flex items-center justify-center">
            <span class="{{ $colors['icon'] }}">
                {{ $icon ?? $slot }}
            </span>
        </div>
    </div>
    <div class="mt-4 flex items-center gap-1.5">
        <span class="flex items-center gap-0.5 text-xs font-semibold {{ $positive ? 'text-emerald-600' : 'text-rose-500' }}">
            @if ($positive)
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 17l6-6 4 4 8-8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M14 7h7v7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                +{{ $change }}%
            @else
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M3 7l6 6 4-4 8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M14 17h7v-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                {{ $change }}%
            @endif
        </span>
        <span class="text-xs text-gray-400">{{ $changeLabel }}</span>
    </div>
</div>
