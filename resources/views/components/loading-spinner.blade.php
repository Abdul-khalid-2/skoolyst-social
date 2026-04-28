@props([
    'label' => 'Loading',
])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-sm text-gray-500']) }}>
    <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
    </svg>
    <span>{{ $label }}</span>
</span>
