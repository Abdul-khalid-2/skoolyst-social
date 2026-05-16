@props([])

@php
    $user = auth()->user();
    $name = trim((string) ($user?->name ?? ''));
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $initials = count($parts) > 1
        ? strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1))
        : strtoupper(substr($parts[0] ?? 'U', 0, 2));

    $currentWorkspaceId = session('current_workspace_id');
    $userWorkspaces = $user
        ? $user->workspaces()->wherePivot('is_active', true)->withPivot('role')->orderBy('workspaces.id')->get()
        : collect();
    $activeWorkspace = $userWorkspaces->firstWhere('id', $currentWorkspaceId) ?? $userWorkspaces->first();
    $activeInitials  = $activeWorkspace ? strtoupper(substr($activeWorkspace->name, 0, 2)) : 'WS';

    $navItems = [
        ['path' => '/dashboard', 'match' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['path' => '/posts', 'match' => 'posts*', 'label' => 'Posts', 'icon' => 'posts'],
        ['path' => '/scheduled', 'match' => 'scheduled', 'label' => 'Scheduled', 'icon' => 'scheduled'],
        ['path' => '/accounts', 'match' => 'accounts', 'label' => 'Accounts', 'icon' => 'accounts'],
        ['path' => '/analytics', 'match' => 'analytics', 'label' => 'Analytics', 'icon' => 'analytics'],
        ['path' => '/activity', 'match' => 'activity', 'label' => 'Activity', 'icon' => 'activity'],
        ['path' => '/notifications', 'match' => 'notifications', 'label' => 'Notifications', 'icon' => 'notifications'],
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

    {{-- Workspace Switcher --}}
    <div
        class="px-2 py-3 border-b border-gray-100"
        x-bind:class="collapsed ? 'flex justify-center' : ''"
        x-data="{ wsOpen: false }"
    >
        {{-- Collapsed: just show initials avatar --}}
        <a
            x-show="collapsed"
            href="{{ url('/switch-account') }}"
            title="Switch Workspace"
            class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-xs font-bold shrink-0"
        >
            {{ $activeInitials }}
        </a>

        {{-- Expanded: dropdown trigger --}}
        <div x-show="!collapsed" class="relative w-full">
            <button
                type="button"
                x-on:click="wsOpen = !wsOpen"
                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg border border-gray-200 hover:border-gray-300 hover:bg-gray-50 transition-colors text-left"
            >
                <span class="w-7 h-7 rounded-md bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-xs font-bold shrink-0">
                    {{ $activeInitials }}
                </span>
                <span class="flex-1 min-w-0">
                    <span class="block text-xs font-semibold text-gray-900 truncate">
                        {{ $activeWorkspace?->name ?? 'No Workspace' }}
                    </span>
                    <span class="block text-[10px] text-gray-400 truncate">
                        {{ ucfirst($activeWorkspace?->pivot?->role ?? '') }}
                    </span>
                </span>
                <svg class="shrink-0 text-gray-400 transition-transform" x-bind:class="wsOpen ? 'rotate-180' : ''" width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>

            {{-- Click-away --}}
            <button
                type="button"
                x-show="wsOpen"
                x-cloak
                x-on:click="wsOpen = false"
                class="fixed inset-0 z-10 cursor-default"
                aria-label="Close"
            ></button>

            {{-- Dropdown list --}}
            <div
                x-show="wsOpen"
                x-cloak
                class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg z-20 overflow-hidden"
            >
                @foreach ($userWorkspaces as $ws)
                    @php $isCurrent = $ws->id == $currentWorkspaceId; @endphp
                    <div class="flex items-center gap-2.5 px-3 py-2.5 {{ $isCurrent ? 'bg-blue-50' : 'hover:bg-gray-50' }} transition-colors">
                        <span class="w-6 h-6 rounded-md bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-[10px] font-bold shrink-0">
                            {{ strtoupper(substr($ws->name, 0, 2)) }}
                        </span>
                        <span class="flex-1 min-w-0 text-xs font-medium text-gray-800 truncate">{{ $ws->name }}</span>
                        @if ($isCurrent)
                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                        @else
                            <form method="POST" action="{{ route('workspace.switch.set', $ws->id) }}">
                                @csrf
                                <button type="submit" class="text-[10px] font-medium text-blue-600 hover:underline">
                                    Switch
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach

                <div class="border-t border-gray-100">
                    <a
                        href="{{ url('/switch-account') }}"
                        class="flex items-center gap-2 px-3 py-2.5 text-xs text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
                    >
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M12 5v14M5 12h14" stroke-linecap="round"/>
                        </svg>
                        Manage workspaces
                    </a>
                </div>
            </div>
        </div>
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
