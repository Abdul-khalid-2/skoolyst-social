@props([
    'name',
])

@error($name)
    <p {{ $attributes->merge(['class' => 'mt-1 text-xs font-medium text-red-600']) }}>{{ $message }}</p>
@enderror
