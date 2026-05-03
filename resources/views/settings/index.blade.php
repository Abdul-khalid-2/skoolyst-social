@extends('layouts.app', [
    'title' => 'Settings',
    'description' => 'Workspace and application preferences.',
])

@php
    $nameTrim = trim((string) ($user->name ?? ''));
    $parts = preg_split('/\s+/', $nameTrim, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) > 1) {
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    } else {
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 2));
    }
    if ($errors->has('workspace_name')) {
        $defaultSection = 'workspace';
    } elseif ($errors->hasAny(['name', 'email', 'timezone'])) {
        $defaultSection = 'profile';
    } elseif ($errors->hasAny(['current_password', 'password', 'password_confirmation'])) {
        $defaultSection = 'security';
    } elseif ($errors->has('confirm')) {
        $defaultSection = 'integrations';
    } else {
        $defaultSection = 'workspace';
    }
    $sectionCard =
        'group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 sm:p-7 shadow-sm transition duration-200 hover:border-gray-300 hover:shadow';
    $sectionTitle = 'text-base font-semibold tracking-tight text-gray-900';
    $sectionSub = 'mt-1 text-sm leading-relaxed text-gray-500';
@endphp

@section('content')
    <div class="min-h-full w-full min-w-0" x-data="{ active: '{{ $defaultSection }}' }">
        <div
            class="grid w-full min-w-0 max-w-6xl mx-auto grid-cols-1 items-start gap-8 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-10"
        >
            <aside class="min-w-0 w-full self-start max-lg:mx-auto max-lg:max-w-md">
                <div class="space-y-3 lg:sticky lg:top-6">
                    <div class="px-0.5 max-lg:text-center lg:text-left">
                        <p class="text-xs font-medium uppercase tracking-widest text-gray-500">Settings</p>
                        <p class="mt-1 text-sm text-gray-600">Workspace &amp; preferences</p>
                    </div>
                <nav
                    class="rounded-2xl border border-gray-200 bg-white p-1.5 shadow-sm"
                    aria-label="Settings sections"
                >
                        <button
                            type="button"
                            @click="active = 'workspace'"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'workspace' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="active === 'workspace' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'"
                            >
                            <svg
                                class="h-[15px] w-[15px]"
                                :class="active === 'workspace' ? 'text-blue-600' : 'text-gray-500'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M2.25 21h19.5M3 3h3l1.5 6h8L16 3h3l1.5 6v12a.75.75 0 01-.75.75H3.75A.75.75 0 012 20.25V9l1-6z"
                                />
                            </svg>
                            </span>
                            <span class="min-w-0">Workspace</span>
                        </button>
                        <button
                            type="button"
                            @click="active = 'profile'"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'profile' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="active === 'profile' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'"
                            >
                            <svg
                                class="h-[15px] w-[15px]"
                                :class="active === 'profile' ? 'text-blue-600' : 'text-gray-500'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M12 21a9 9 0 100-18 9 9 0 000 18zM15 9.75A3 3 0 0012 6a3 3 0 00-3 3.75M9 15h6"
                                />
                            </svg>
                            </span>
                            <span class="min-w-0">Profile</span>
                        </button>
                        <button
                            type="button"
                            @click="active = 'notifications'"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'notifications' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="active === 'notifications' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'"
                            >
                            <svg
                                class="h-[15px] w-[15px]"
                                :class="active === 'notifications' ? 'text-blue-600' : 'text-gray-500'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M14.25 3.75h-4.5a3 3 0 00-3 3v1.5a3 3 0 00-1.2 1.2l-1.05 1.8a.75.75 0 00.5 1.2h5.1m3.3 0h5.1a.75.75 0 00.5-1.2l-1.05-1.8a3 3 0 00-1.2-1.2V6.75a3 3 0 00-3-3zM9 18a3 3 0 006 0H9z"
                                />
                            </svg>
                            </span>
                            <span class="min-w-0">Notifications</span>
                        </button>
                        <button
                            type="button"
                            @click="active = 'security'"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'security' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="active === 'security' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'"
                            >
                            <svg
                                class="h-[15px] w-[15px]"
                                :class="active === 'security' ? 'text-blue-600' : 'text-gray-500'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m9 0h1.5a1.5 1.5 0 011.5 1.5v3a1.5 1.5 0 01-1.5 1.5h-15A1.5 1.5 0 013.75 15v-3a1.5 1.5 0 011.5-1.5h1.5m9 0h-6"
                                />
                            </svg>
                            </span>
                            <span class="min-w-0">Security</span>
                        </button>
                        <button
                            type="button"
                            @click="active = 'appearance'"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'appearance' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="active === 'appearance' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'"
                            >
                            <svg
                                class="h-[15px] w-[15px]"
                                :class="active === 'appearance' ? 'text-blue-600' : 'text-gray-500'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M4.5 4.5h15M4.5 9h15M4.5 13.5h7.5M4.5 18h7.5M15 16.5l1.5 1.5L21 15"
                                />
                            </svg>
                            </span>
                            <span class="min-w-0">Appearance</span>
                        </button>
                        <button
                            type="button"
                            @click="active = 'billing'"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'billing' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="active === 'billing' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'"
                            >
                            <svg
                                class="h-[15px] w-[15px]"
                                :class="active === 'billing' ? 'text-blue-600' : 'text-gray-500'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M2.25 6.75h19.5M2.25 6.75l1.2 9.3a1.5 1.5 0 001.5 1.2h12.3a1.5 1.5 0 001.5-1.2l1.2-9.3M6 6.75V4.5a1.5 1.5 0 011.5-1.5h9A1.5 1.5 0 0118 4.5v2.25"
                                />
                            </svg>
                            </span>
                            <span class="min-w-0">Billing</span>
                        </button>
                        <button
                            type="button"
                            @click="active = 'integrations'"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'integrations' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'"
                        >
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                :class="active === 'integrations' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'"
                            >
                            <svg
                                class="h-[15px] w-[15px]"
                                :class="active === 'integrations' ? 'text-blue-600' : 'text-gray-500'"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M9 12.75L10.5 12l.75-1.5.75 1.5L13.5 12l-1.5.75L12 12l-.75-1.5L9 12.75zM3 5.25h4.5l1.5-1.5h6l1.5 1.5H21m-18 0v10.5a1.5 1.5 0 001.5 1.5h12a1.5 1.5 0 001.5-1.5V5.25m-15 0h15"
                                />
                            </svg>
                            </span>
                            <span class="min-w-0">Integrations</span>
                        </button>
                    </nav>
                </div>
                </aside>

                <div class="min-w-0 space-y-6">
                    <div x-show="active === 'workspace'" x-cloak>
                        <div
                            class="{{ $sectionCard }}"
                        >
                            <h3 class="{{ $sectionTitle }}">Workspace</h3>
                            <p class="{{ $sectionSub }} mb-5">Rename the current workspace. Requires owner or admin role.</p>
                            @if (! $workspace)
                                <p class="text-sm text-gray-600">{{ __('No active workspace is available.') }}</p>
                            @elseif ($canEditWorkspace)
                                <form method="post" action="{{ route('settings.workspace') }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="max-w-md">
                                        <x-input
                                            name="workspace_name"
                                            label="Workspace name"
                                            value="{{ old('workspace_name', $workspace->name) }}"
                                        />
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <x-button type="submit" variant="primary" class="font-medium">Save workspace</x-button>
                                    </div>
                                </form>
                            @else
                                <div class="max-w-md">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Workspace name</label>
                                    <input
                                        type="text"
                                        readonly
                                        value="{{ e($workspace->name) }}"
                                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                                    />
                                    <p class="mt-2 text-xs text-gray-500">
                                        {{ __('You do not have permission to rename this workspace.') }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div x-show="active === 'profile'" x-cloak>
                        <div
                            class="{{ $sectionCard }}"
                        >
                            <h3 class="{{ $sectionTitle }} mb-1">Personal Information</h3>
                            <p class="{{ $sectionSub }} mb-5">Update your name, email, and timezone used across the app.</p>
                            <div class="flex items-center gap-4 mb-5">
                                @if ($user->avatar)
                                    <img
                                        src="{{ $user->avatar }}"
                                        alt="{{ $user->name ?? 'User' }}"
                                        class="w-14 h-14 rounded-full object-cover"
                                    />
                                @else
                                    <div
                                        class="w-14 h-14 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-lg font-semibold"
                                    >
                                        {{ $initials }}
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm text-blue-600 font-medium">Avatar from account</p>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $user->email ?? 'No email' }}</p>
                                </div>
                            </div>
                            <form method="post" action="{{ route('profile.update') }}">
                                @csrf
                                @method('PUT')
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <x-input
                                        name="name"
                                        label="Full Name"
                                        value="{{ old('name', $user->name) }}"
                                    />
                                    <x-input
                                        name="email"
                                        label="Email"
                                        type="email"
                                        value="{{ old('email', $user->email) }}"
                                    />
                                    <x-input
                                        name="timezone"
                                        label="Timezone"
                                        value="{{ old('timezone', $user->timezone ?? 'UTC') }}"
                                    />
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <x-button type="submit" variant="primary" class="font-medium">Save changes</x-button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div x-show="active === 'notifications'" x-cloak>
                        <div
                            class="{{ $sectionCard }}"
                        >
                            <h3 class="{{ $sectionTitle }} mb-1">Notification Preferences</h3>
                            <p class="{{ $sectionSub }} mb-5">
                                {{ __('These toggles are local only for now. Server-side notification preferences are not yet configured.') }}
                            </p>
                            <div class="space-y-4">
                                @foreach ([
        ['label' => 'Email notifications', 'desc' => 'Receive updates via email', 'on' => true],
        ['label' => 'Push notifications', 'desc' => 'Browser push alerts', 'on' => false],
        ['label' => 'Weekly digest', 'desc' => 'Summary of activity each week', 'on' => true],
        ['label' => 'Security alerts', 'desc' => 'Notify on login from new device', 'on' => true],
        ['label' => 'Marketing emails', 'desc' => 'Product news and announcements', 'on' => false],
    ] as $item)
                                    <div
                                        x-data="{ on: {{ $item['on'] ? 'true' : 'false' }} }"
                                        class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0"
                                    >
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">{{ $item['label'] }}</p>
                                            <p class="text-xs text-gray-500">{{ $item['desc'] }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="on = !on"
                                            class="relative inline-flex w-9 h-5 rounded-full transition-colors duration-200"
                                            :class="on ? 'bg-blue-600' : 'bg-gray-200'"
                                        >
                                            <span
                                                class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                                                :class="on ? 'translate-x-4' : ''"
                                            ></span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'security'" x-cloak>
                        <div
                            class="{{ $sectionCard }}"
                        >
                            <h3 class="{{ $sectionTitle }} mb-1">Security Settings</h3>
                            <p class="{{ $sectionSub }} mb-5">Change your password and review security options.</p>
                            <form method="post" action="{{ route('profile.password') }}">
                                @csrf
                                @method('PUT')
                                <div class="space-y-4 max-w-lg">
                                    <x-input
                                        name="current_password"
                                        label="Current Password"
                                        type="password"
                                        autocomplete="current-password"
                                    />
                                    <x-input
                                        name="password"
                                        label="New Password"
                                        type="password"
                                        autocomplete="new-password"
                                    />
                                    <x-input
                                        name="password_confirmation"
                                        label="Confirm New Password"
                                        type="password"
                                        autocomplete="new-password"
                                    />
                                </div>
                                <div
                                    x-data="{ on: false }"
                                    class="flex items-center justify-between py-2 mt-2 border-t border-gray-100"
                                >
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">Two-Factor Authentication</p>
                                        <p class="text-xs text-gray-500">Add an extra layer of security</p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="on = !on"
                                        class="relative inline-flex w-9 h-5 rounded-full transition-colors duration-200"
                                        :class="on ? 'bg-blue-600' : 'bg-gray-200'"
                                    >
                                        <span
                                            class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                                            :class="on ? 'translate-x-4' : ''"
                                        ></span>
                                    </button>
                                </div>
                                <div class="flex justify-end mt-4">
                                    <x-button type="submit" variant="primary" class="font-medium">Update password</x-button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div x-show="active === 'appearance'" x-cloak>
                        <div
                            class="{{ $sectionCard }}"
                            x-data="{
                                theme: 'light',
                                accent: 'blue',
                                sidebar: 'expanded',
                            }"
                        >
                            <h3 class="{{ $sectionTitle }} mb-1">Appearance</h3>
                            <p class="{{ $sectionSub }} mb-5">
                                {{ __('Appearance choices are stored in this page only. Global theme support may be added later.') }}
                            </p>
                            <div class="mb-6">
                                <p class="text-sm font-semibold mb-3 text-gray-900">Theme</p>
                                <div class="flex flex-wrap gap-3">
                                    <button
                                        type="button"
                                        @click="theme = 'light'"
                                        class="flex-1 min-w-[5rem] border-2 rounded-xl p-4 flex flex-col items-center gap-2 transition-all active:scale-95"
                                        :class="theme === 'light' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <span class="text-sm font-medium text-gray-800">Light</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="theme = 'dark'"
                                        class="flex-1 min-w-[5rem] border-2 rounded-xl p-4 flex flex-col items-center gap-2 transition-all active:scale-95"
                                        :class="theme === 'dark' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <span class="text-sm font-medium text-gray-800">Dark</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="theme = 'system'"
                                        class="flex-1 min-w-[5rem] border-2 rounded-xl p-4 flex flex-col items-center gap-2 transition-all active:scale-95"
                                        :class="theme === 'system' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <span class="text-sm font-medium text-gray-800">System</span>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-6">
                                <p class="text-sm font-semibold mb-3 text-gray-900">Accent Color</p>
                                <div class="flex flex-wrap gap-3">
                                    @foreach (['blue' => 'bg-blue-600', 'purple' => 'bg-purple-600', 'emerald' => 'bg-emerald-600', 'orange' => 'bg-orange-500', 'pink' => 'bg-pink-500', 'red' => 'bg-red-500'] as $c => $cls)
                                        <button
                                            type="button"
                                            @click="accent = '{{ $c }}'"
                                            class="w-8 h-8 rounded-full cursor-pointer transition-all active:scale-95 ring-offset-2 {{ $cls }}"
                                            :class="accent === '{{ $c }}' ? 'ring-2 ring-offset-2 ring-gray-800' : ''"
                                        ></button>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <p class="text-sm font-semibold mb-3 text-gray-900">Sidebar Style</p>
                                <div class="grid grid-cols-2 gap-3 max-w-sm">
                                    <button
                                        type="button"
                                        @click="sidebar = 'expanded'"
                                        class="border-2 rounded-xl p-4 flex flex-col items-center gap-2 transition-all active:scale-95"
                                        :class="sidebar === 'expanded' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <span class="text-gray-600 text-sm">Panel</span>
                                        <span class="text-sm font-medium text-gray-800">Expanded</span>
                                    </button>
                                    <button
                                        type="button"
                                        @click="sidebar = 'compact'"
                                        class="border-2 rounded-xl p-4 flex flex-col items-center gap-2 transition-all active:scale-95"
                                        :class="sidebar === 'compact' ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'"
                                    >
                                        <span class="text-gray-600 text-sm">Grid</span>
                                        <span class="text-sm font-medium text-gray-800">Compact</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="active === 'billing'" x-cloak class="space-y-6">
                        <div
                            class="relative overflow-hidden rounded-2xl border border-indigo-500/30 bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700 p-6 sm:p-7 text-white shadow-lg shadow-indigo-900/20 ring-1 ring-white/10"
                        >
                            <div class="absolute -right-20 -top-20 h-40 w-40 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
                            <div class="absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-cyan-400/20 blur-2xl" aria-hidden="true"></div>
                            <div class="relative flex items-center justify-between gap-3">
                                <p class="text-lg font-bold tracking-tight sm:text-xl">
                                    {{ $workspace?->plan ? \Illuminate\Support\Str::of($workspace->plan)->headline() : 'Free' }} Plan
                                </p>
                                <span
                                    class="shrink-0 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-xs font-medium text-white/95 backdrop-blur-sm"
                                >{{ $workspace?->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <p class="relative mt-2 text-sm leading-relaxed text-blue-100/90">
                                {{ __('Usage and plan limits are shown for reference. Invoicing is managed in your billing account.') }}
                            </p>
                        </div>
                        <div
                            class="{{ $sectionCard }}"
                        >
                            <h3 class="{{ $sectionTitle }} mb-1">Usage This Month</h3>
                            <p class="{{ $sectionSub }} mb-5">Resource usage against your current plan.</p>
                            @foreach (['Posts' => [47, 100, 'bg-blue-500'], 'Connected accounts' => [2, 5, 'bg-emerald-500'], 'Team members' => [1, 3, 'bg-purple-500']] as $label => $data)
                                <div @class(['mb-4' => $label !== 'Team members'])>
                                    <div class="flex items-center justify-between text-xs mb-1">
                                        <span class="text-gray-600">{{ $label }}</span>
                                        <span class="text-gray-700 font-semibold">{{ $data[0] }}/{{ $data[1] }}</span>
                                    </div>
                                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                        <div
                                            class="h-full {{ $data[2] }}"
                                            style="width: {{ ($data[0] / $data[1]) * 100 }}%"
                                        ></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="active === 'integrations'" x-cloak>
                        <div
                            class="{{ $sectionCard }}"
                        >
                            <h3 class="{{ $sectionTitle }}">Integrations</h3>
                            <p class="{{ $sectionSub }} mt-1 mb-4">
                                {{ __('Connect and manage social accounts for this workspace on the Accounts page.') }}
                            </p>
                            @if ($workspace)
                                <p class="text-xs text-gray-400 mb-4">Workspace: {{ $workspace->name }}</p>
                            @endif
                            <a
                                href="{{ route('accounts') }}"
                                class="inline-flex items-center justify-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium bg-blue-600 hover:bg-blue-700 text-white"
                            >
                                Open Accounts
                            </a>
                            <p class="mt-4 text-xs text-gray-500">
                                <a href="{{ route('privacy.data-deletion') }}" class="text-blue-600 hover:underline">User data deletion (Facebook / Instagram)</a>
                            </p>
                            @if ($user->facebook_id)
                                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50/90 p-4">
                                    <h4 class="text-sm font-semibold text-gray-900">Facebook &amp; Instagram data</h4>
                                    <p class="mt-1 text-xs text-gray-600 leading-relaxed">
                                        Remove your Meta user id, stored Facebook tokens, API tokens, and workspace Facebook/Instagram connections received through Facebook Login.
                                    </p>
                                    <form method="post" action="{{ route('settings.facebook-data.destroy') }}" class="mt-4 space-y-3">
                                        @csrf
                                        <label class="flex items-start gap-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="confirm"
                                                value="1"
                                                class="mt-1 rounded border-gray-300 text-red-600 focus:ring-red-500"
                                            />
                                            <span class="text-xs text-gray-700">
                                                I understand this disconnects Facebook/Instagram publishing for my workspaces and removes stored Meta tokens from Skoolyst.
                                            </span>
                                        </label>
                                        @error('confirm')
                                            <p class="text-xs text-red-600">{{ $message }}</p>
                                        @enderror
                                        <x-button type="submit" variant="danger" class="font-medium">
                                            Remove Facebook-connected data
                                        </x-button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
        </div>
    </div>
@endsection
