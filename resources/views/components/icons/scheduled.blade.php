@props(['active' => false])

<svg {{ $attributes->merge(['class' => 'shrink-0 ' . ($active ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-600')]) }} width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
    <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
</svg>
