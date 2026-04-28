@props([
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-200 p-8 text-center']) }}>
    <div class="mx-auto mb-3 w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
        {{ $icon ?? '' }}
    </div>
    <h2 class="text-sm font-semibold text-gray-900">{{ $title }}</h2>
    @if ($description)
        <p class="mt-1 text-sm text-gray-500">{{ $description }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
