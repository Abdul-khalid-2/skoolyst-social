@props(['active' => false])

<svg {{ $attributes->merge(['class' => 'shrink-0 ' . ($active ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-600')]) }} width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    <circle cx="9.5" cy="7" r="4" stroke="currentColor" stroke-width="2" />
    <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
</svg>
