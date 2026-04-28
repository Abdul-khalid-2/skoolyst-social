@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
])

@php($fieldId = $id ?? $name)

<div>
    @if ($label)
        <label for="{{ $fieldId }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
    @endif

    <input
        id="{{ $fieldId }}"
        @if ($name) name="{{ $name }}" @endif
        type="{{ $type }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-400 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100']) }}
    />

    @if ($name)
        <x-form-error :name="$name" />
    @endif
</div>
