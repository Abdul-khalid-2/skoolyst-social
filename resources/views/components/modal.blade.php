@props([
    'name' => 'modal',
    'title' => null,
])

<div
    x-data="{ open: false }"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
    x-on:close-modal.window="open = false"
    x-show="open"
    x-trap.inert.noscroll="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
    role="dialog"
    aria-modal="true"
    @if ($title) aria-labelledby="{{ $name }}-title" @else aria-label="{{ $name }}" @endif
>
    <div class="absolute inset-0 bg-gray-900/40" x-on:click="open = false"></div>

    <div {{ $attributes->merge(['class' => 'relative w-full max-w-lg bg-white rounded-xl border border-gray-200 shadow-xl p-5']) }}>
        @if ($title)
            <div class="flex items-start justify-between gap-4 mb-4">
                <h2 id="{{ $name }}-title" class="text-base font-semibold text-gray-900">{{ $title }}</h2>
                <button type="button" x-on:click="open = false" class="text-gray-400 hover:text-gray-600 transition-colors" aria-label="Close modal">
                    x
                </button>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
