@props(['active' => false])

<svg {{ $attributes->merge(['class' => 'shrink-0 ' . ($active ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-600')]) }} width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M3 3v18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    <path d="M7 15l4-4 3 3 5-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
</svg>
