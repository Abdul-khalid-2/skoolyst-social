@props([
    'padding' => 'p-5',
])

<div {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-gray-200 ' . $padding]) }}>
    {{ $slot }}
</div>
