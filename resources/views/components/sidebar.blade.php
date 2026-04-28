@props([])

@php
    $user = auth()->user();
    $name = trim((string) ($user?->name ?? ''));
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = count($parts) > 1
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1))
        : strtoupper(substr($parts[0] ?? 'U', 0, 2));

    $navItems = [
        ['path' => '/dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['path' => '/posts', 'match' => 'posts*', 'label' => 'Posts', 'icon' => 'posts'],
        ['path' => '/scheduled', 'match' => 'scheduled', 'label' => 'Scheduled', 'icon' => 'scheduled'],
        ['path' => '/accounts', 'match' => 'accounts', 'label' => 'Accounts', 'icon' => 'accounts'],
        ['path' => '/analytics', 'match' => 'analytics', 'label' => 'Analytics', 'icon' => 'analytics'],
        ['path' => '/activity', 'match' => 'activity', 'label' => 'Activity', 'icon' => 'activity'],
        ['path' => '/settings', 'match' => 'settings', 'label' => 'Settings', 'icon' => 'settings'],
    ];
@endphp

<aside
    {{ $attributes->merge(['class' => 'relative flex flex-col bg-white border-r border-gray-200 transition-all duration-300 ease-in-out shrink-0']) }}
    x-bind:class="collapsed ? 'w-16' : 'w-60'"
>
    <div class="flex items-center h-16 px-4 border-b border-gray-200" x-bind:class="collapsed ? 'justify-center' : 'gap-3'">
        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center shrink-0">
            <svg class="text-white" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
        <span x-show="!collapsed" class="font-semibold text-gray-900 text-sm tracking-tight">Skoolyst Social AI</span>
    </div>

    <div class="px-2 py-3 border-b border-gray-100" x-bind:class="collapsed ? 'flex justify-center' : ''">
        <a
            href="{{ url('/create') }}"
            x-bind:title="collapsed ? 'Create Post' : null"
            class="bg-blue-600 hover:bg-blue-700 text-white font-medium transition-all duration-150 rounded-lg flex items-center gap-2"
            x-bind:class="collapsed ? 'w-9 h-9 justify-center' : 'w-full px-3 py-2'"
        >
            <svg class="shrink-0" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span x-show="!collapsed">Create Post</span>
        </a>
    </div>

    <nav class="flex-1 py-3 px-2 space-y-0.5 overflow-y-auto" aria-label="Main navigation">
        @foreach ($navItems as $item)
            @php($active = request()->is($item['match']))
            <a
                href="{{ url($item['path']) }}"
                x-bind:title="collapsed ? '{{ $item['label'] }}' : null"
                class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 group {{ $active ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}"
                x-bind:class="collapsed ? 'justify-center' : ''"
                @if ($active) aria-current="page" @endif
            >
                <x-dynamic-component :component="'icons.' . $item['icon']" :active="$active" />
                <span x-show="!collapsed">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @auth
        <div x-show="!collapsed" class="p-3 border-t border-gray-200">
            <a href="{{ route('profile') }}" class="w-full flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors text-left">
                @if ($user?->avatar)
                    <img src="{{ $user->avatar }}" alt="{{ $user->name ?: 'User avatar' }}" class="w-7 h-7 rounded-full object-cover shrink-0">
                @else
                    <span class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-xs font-semibold shrink-0">
                        {{ $initials }}
                    </span>
                @endif
                <span class="min-w-0">
                    <span class="block text-xs font-semibold text-gray-900 truncate">{{ $user->name ?? 'User' }}</span>
                    <span class="block text-xs text-gray-500 truncate">{{ $user->email ?? 'No email' }}</span>
                </span>
            </a>
        </div>
    @endauth

    <button
        type="button"
        x-on:click="collapsed = !collapsed"
        class="absolute -right-3 top-20 w-6 h-6 bg-white border border-gray-200 rounded-full flex items-center justify-center text-gray-400 hover:text-gray-600 hover:border-gray-300 transition-all shadow-sm z-10"
        aria-label="Toggle sidebar"
    >
        <svg x-show="!collapsed" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <svg x-show="collapsed" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
</aside>
