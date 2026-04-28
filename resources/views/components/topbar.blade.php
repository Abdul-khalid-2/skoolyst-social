@props([
    'title' => 'Dashboard',
    'subtitle' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'searchQuery' => '',
    'searchResults' => [],
])

@php
    $user = auth()->user();
    $name = trim((string) ($user?->name ?? ''));
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = count($parts) > 1
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1))
        : strtoupper(substr($parts[0] ?? 'U', 0, 2));
@endphp

<header
    {{ $attributes->merge(['class' => 'sticky top-0 z-10 bg-white border-b border-gray-200 px-6 h-16 flex items-center justify-between gap-4']) }}
    x-data="{ showDropdown: false, showSearchResults: false, searchQuery: @js($searchQuery) }"
>
    <div>
        <h1 class="text-base font-semibold text-gray-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-xs text-gray-500">{{ $subtitle }}</p>
        @endif
    </div>

    <div class="flex items-center gap-2">
        <div class="hidden md:flex relative items-center gap-2 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 w-56">
            <svg class="text-gray-400 shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" />
                <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <input
                type="search"
                placeholder="Search..."
                x-model="searchQuery"
                x-on:focus="showSearchResults = true"
                class="bg-transparent text-sm text-gray-700 placeholder-gray-400 outline-none w-full"
                aria-label="Search"
            >

            <div
                x-show="showSearchResults && searchQuery.trim()"
                x-cloak
                class="absolute top-11 left-0 right-0 bg-white border border-gray-200 rounded-lg shadow-lg z-30 max-h-72 overflow-y-auto"
            >
                @forelse ($searchResults as $item)
                    <a href="{{ url($item['path'] ?? '/') }}" class="block w-full text-left px-3 py-2.5 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                        <span class="block text-sm text-gray-800">{{ $item['title'] ?? '' }}</span>
                        <span class="block text-xs text-gray-500">{{ $item['type'] ?? '' }}</span>
                    </a>
                @empty
                    <p class="px-3 py-3 text-xs text-gray-500">No results found</p>
                @endforelse
            </div>
        </div>

        <a
            href="{{ url('/notifications') }}"
            class="relative w-9 h-9 flex items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 transition-colors"
            aria-label="Notifications"
        >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
            <span class="absolute top-2 right-1.5 bg-red-500 text-white text-xs font-semibold w-5 h-5 rounded-full flex items-center justify-center">3</span>
        </a>

        @if ($actionLabel && $actionHref)
            <a href="{{ url($actionHref) }}" class="flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-3.5 py-2 rounded-lg transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                {{ $actionLabel }}
            </a>
        @endif

        @auth
            <div class="relative">
                <button
                    type="button"
                    x-on:click="showDropdown = !showDropdown"
                    class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-100 rounded-lg transition-colors"
                    aria-haspopup="menu"
                    x-bind:aria-expanded="showDropdown.toString()"
                >
                    <span class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-xs font-semibold" title="{{ $user->name ?? 'User' }}">
                        {{ $initials }}
                    </span>
                    <svg class="text-gray-500" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </button>

                <button
                    type="button"
                    x-show="showDropdown"
                    x-cloak
                    x-on:click="showDropdown = false; showSearchResults = false"
                    class="fixed inset-0 z-10 cursor-default"
                    aria-label="Close menu"
                ></button>

                <div x-show="showDropdown" x-cloak class="absolute right-0 top-12 w-48 bg-white border border-gray-200 rounded-xl shadow-lg z-20" role="menu">
                    <a href="{{ url('/profile') }}" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors" role="menuitem">Profile</a>
                    <a href="{{ url('/switch-account') }}" class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors" role="menuitem">Switch Account</a>
                    <div class="mx-2 my-1 h-px bg-gray-100"></div>
                    <form method="POST" action="{{ url('/logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors" role="menuitem">
                            Logout
                        </button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</header>
