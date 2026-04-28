@props([])

@php
    $initialToasts = collect(['success', 'error', 'info', 'warning'])
        ->filter(fn (string $type): bool => session()->has($type))
        ->map(fn (string $type): array => [
            'id' => uniqid($type . '-', true),
            'type' => $type,
            'message' => session($type),
        ])
        ->values();
@endphp

<div
    {{ $attributes->merge(['class' => 'fixed top-4 right-4 z-50 max-w-md']) }}
    x-data="{
        toasts: @js($initialToasts),
        styles: {
            success: { bar: 'bg-emerald-500', icon: 'bg-emerald-100 text-emerald-700', text: 'text-emerald-900', bg: 'bg-white border-emerald-200', symbol: 'ok' },
            error: { bar: 'bg-red-500', icon: 'bg-red-100 text-red-700', text: 'text-red-900', bg: 'bg-white border-red-200', symbol: 'x' },
            info: { bar: 'bg-blue-500', icon: 'bg-blue-100 text-blue-700', text: 'text-blue-900', bg: 'bg-white border-blue-200', symbol: 'i' },
            warning: { bar: 'bg-amber-500', icon: 'bg-amber-100 text-amber-700', text: 'text-amber-900', bg: 'bg-white border-amber-200', symbol: '!' },
        },
        add(type, message) {
            const id = `${type}-${Date.now()}-${Math.random()}`;
            this.toasts.push({ id, type, message });
            setTimeout(() => this.remove(id), 5000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
        init() {
            this.toasts.forEach((toast) => setTimeout(() => this.remove(toast.id), 5000));
        },
    }"
    x-on:toast.window="add($event.detail.type || 'info', $event.detail.message)"
>
    <template x-for="toast in toasts" x-bind:key="toast.id">
        <div
            class="relative flex items-start gap-3 w-80 rounded-xl border shadow-lg p-4 mb-2 overflow-hidden animate-slide-in"
            x-bind:class="styles[toast.type].bg"
            role="alert"
        >
            <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl" x-bind:class="styles[toast.type].bar"></div>

            <div class="ml-2 w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs font-bold uppercase" x-bind:class="styles[toast.type].icon">
                <span x-text="styles[toast.type].symbol"></span>
            </div>

            <span class="flex-1 text-sm font-medium leading-snug" x-bind:class="styles[toast.type].text" x-text="toast.message"></span>

            <button
                type="button"
                x-on:click="remove(toast.id)"
                class="shrink-0 text-gray-400 hover:text-gray-600 transition-colors text-lg leading-none"
                aria-label="Dismiss"
            >
                x
            </button>
        </div>
    </template>
</div>
