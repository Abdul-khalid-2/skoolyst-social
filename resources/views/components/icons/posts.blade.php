@props(['active' => false])

<svg {{ $attributes->merge(['class' => 'shrink-0 ' . ($active ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-600')]) }} width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
    <path d="M14 2v6h6M8 13h8M8 17h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
</svg>
