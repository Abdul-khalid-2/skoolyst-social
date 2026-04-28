@props([
    'title',
    'description' => null,
    'titleTag' => 'h1',
    'titleClass' => 'text-2xl font-bold text-gray-900',
    'descriptionClass' => 'text-sm text-gray-500 mt-1 mb-6',
])
<div>
    <div class="flex items-center gap-2">
        <{{ $titleTag }} class="{{ $titleClass }}">{{ $title }}</{{ $titleTag }}>
        @isset($actions)
            {{ $actions }}
        @endisset
    </div>
    @if ($description)
        <p class="{{ $descriptionClass }}">{{ $description }}</p>
    @endif
</div>
