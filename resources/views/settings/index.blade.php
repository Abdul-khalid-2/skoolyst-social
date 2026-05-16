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
    $sectionCard  = 'group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-6 sm:p-7 shadow-sm transition duration-200 hover:border-gray-300 hover:shadow';
    $sectionTitle = 'text-base font-semibold tracking-tight text-gray-900';
    $sectionSub   = 'mt-1 text-sm leading-relaxed text-gray-500';
@endphp

@section('content')
    <div class="min-h-full w-full min-w-0" x-data="{ active: '{{ $tab }}' }">
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
                            @click="active = 'workspace'; history.pushState(null, '', '{{ route('settings.tab', 'workspace') }}')"
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
                            @click="active = 'profile'; history.pushState(null, '', '{{ route('settings.tab', 'profile') }}')"
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
                            @click="active = 'notifications'; history.pushState(null, '', '{{ route('settings.tab', 'notifications') }}')"
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
                            @click="active = 'security'; history.pushState(null, '', '{{ route('settings.tab', 'security') }}')"
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
                            @click="active = 'appearance'; history.pushState(null, '', '{{ route('settings.tab', 'appearance') }}')"
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
                            @click="active = 'billing'; history.pushState(null, '', '{{ route('settings.tab', 'billing') }}')"
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
                            @click="active = 'integrations'; history.pushState(null, '', '{{ route('settings.tab', 'integrations') }}')"
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

                        @if ($isOwner)
                        <button type="button" @click="active = 'roles'; history.pushState(null, '', '{{ route('settings.tab', 'roles') }}')"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'roles' ? 'bg-blue-50 text-blue-900 shadow-sm border border-blue-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                  :class="active === 'roles' ? 'bg-white/80 text-blue-600 shadow-sm' : 'bg-gray-100 text-gray-500'">
                                <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.955 11.955 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                                </svg>
                            </span>
                            <span class="min-w-0">Roles & Permissions</span>
                        </button>
                        @endif

                        @if ($isSuperadmin)
                        <button type="button" @click="active = 'superadmin'; history.pushState(null, '', '{{ route('settings.tab', 'superadmin') }}')"
                            class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-all duration-200"
                            :class="active === 'superadmin' ? 'bg-purple-50 text-purple-900 shadow-sm border border-purple-200/80' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900 border border-transparent'">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                                  :class="active === 'superadmin' ? 'bg-white/80 text-purple-600 shadow-sm' : 'bg-gray-100 text-gray-500'">
                                <svg class="h-[15px] w-[15px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                                </svg>
                            </span>
                            <span class="min-w-0">Superadmin</span>
                        </button>
                        @endif
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
                                <a href="{{ route('data-deletion') }}" class="text-blue-600 hover:underline">User data deletion (Facebook / Instagram)</a>
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

                    {{-- Roles & Permissions Panel --}}
                    @if ($isOwner)
                    <div x-show="active === 'roles'" x-cloak class="space-y-6">

                        <div class="{{ $sectionCard }}">
                            <div class="flex items-start justify-between gap-4 flex-wrap">
                                <div>
                                    <h3 class="{{ $sectionTitle }}">Roles & Permissions</h3>
                                    <p class="{{ $sectionSub }} mt-1">Add, rename, delete roles and assign permissions per role. Built-in roles (superadmin, owner, admin, editor, viewer) cannot be renamed or deleted.</p>
                                </div>
                                <form method="POST" action="{{ route('settings.roles.repair') }}">
                                    @csrf
                                    <button type="submit"
                                        onclick="return confirm('Re-sync all workspace member roles? This fixes permission issues for all users.')"
                                        class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-2 rounded-lg border border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                        </svg>
                                        Repair All Roles
                                    </button>
                                </form>
                            </div>
                            @if (session('success'))
                                <div class="mt-4 px-4 py-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-lg">
                                    {{ session('success') }}
                                </div>
                            @endif
                        </div>

                        <div class="{{ $sectionCard }}" x-data="{ addOpen: false }">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-sm font-semibold text-gray-900">All Roles</h4>
                                <button type="button" @click="addOpen = !addOpen"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                    + New Role
                                </button>
                            </div>

                            <form x-show="addOpen" x-cloak method="POST" action="{{ route('settings.roles.store') }}"
                                  class="mb-4 flex items-center gap-2">
                                @csrf
                                <input type="text" name="name" placeholder="e.g. moderator" value="{{ old('name') }}"
                                       class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-500"
                                       pattern="[a-z0-9_\-]+" title="Lowercase letters, numbers, hyphens, underscores only">
                                <button type="submit" class="shrink-0 text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">Create</button>
                                <button type="button" @click="addOpen = false" class="shrink-0 text-xs text-gray-500 hover:text-gray-700 px-2">Cancel</button>
                            </form>

                            <div class="divide-y divide-gray-100">
                                @foreach ($roles as $role)
                                @php
                                    $protected = in_array($role['name'], ['superadmin', 'owner', 'admin', 'editor', 'viewer']);
                                    $badgeCls  = match($role['name']) {
                                        'superadmin' => 'bg-purple-100 text-purple-700',
                                        'owner'      => 'bg-blue-100 text-blue-700',
                                        'admin'      => 'bg-indigo-100 text-indigo-700',
                                        'editor'     => 'bg-gray-100 text-gray-600',
                                        default      => 'bg-emerald-100 text-emerald-700',
                                    };
                                @endphp
                                <div class="py-3 flex items-center gap-3 flex-wrap" x-data="{ editOpen: false }">
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $badgeCls }}">{{ ucfirst($role['name']) }}</span>
                                    <span class="text-xs text-gray-400">{{ count($role['permissions']) }} permissions</span>
                                    <div class="ml-auto flex items-center gap-2">
                                        @if (! $protected)
                                            <button type="button" @click="editOpen = !editOpen"
                                                class="text-xs text-gray-400 hover:text-blue-600 px-2 py-1 rounded hover:bg-blue-50 transition-colors">
                                                Rename
                                            </button>
                                            <form method="POST" action="{{ route('settings.roles.destroy', $role['name']) }}" class="inline">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                    onclick="return confirm('Delete role {{ $role['name'] }}?')"
                                                    class="text-xs text-gray-400 hover:text-rose-600 px-2 py-1 rounded hover:bg-rose-50 transition-colors">
                                                    Delete
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-[10px] text-gray-300 italic">built-in</span>
                                        @endif
                                    </div>
                                    @if (! $protected)
                                    <form x-show="editOpen" x-cloak method="POST"
                                          action="{{ route('settings.roles.update', $role['name']) }}"
                                          class="w-full flex items-center gap-2 pb-2">
                                        @csrf @method('PUT')
                                        <input type="text" name="name" value="{{ $role['name'] }}"
                                               class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 outline-none focus:ring-2 focus:ring-blue-500"
                                               pattern="[a-z0-9_\-]+">
                                        <button type="submit" class="text-xs font-medium bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700">Save</button>
                                    </form>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>

                        @foreach ($roles as $role)
                        <div class="{{ $sectionCard }}">
                            <h4 class="text-sm font-semibold text-gray-900 mb-1">
                                {{ ucfirst($role['name']) }}
                                <span class="font-normal text-gray-400 text-xs ml-1">— Permission Matrix</span>
                            </h4>
                            <form method="POST" action="{{ route('settings.roles.sync', $role['name']) }}">
                                @csrf
                                <div class="space-y-5 mt-4">
                                    @foreach ($permissionGroups as $group => $perms)
                                    <div>
                                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">{{ str_replace('_', ' ', $group) }}</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-6">
                                            @foreach ($perms as $perm)
                                            <label class="flex items-center gap-2.5 cursor-pointer">
                                                <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                                       {{ in_array($perm, $role['permissions']) ? 'checked' : '' }}
                                                       class="w-3.5 h-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                <span class="text-xs text-gray-700">{{ str_replace(['.', '_'], [' → ', ' '], $perm) }}</span>
                                            </label>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="mt-5 flex justify-end">
                                    <button type="submit"
                                        class="text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                        Sync Permissions for {{ ucfirst($role['name']) }}
                                    </button>
                                </div>
                            </form>
                        </div>
                        @endforeach

                    </div>
                    @endif

                    {{-- Superadmin Panel --}}
                    @if ($isSuperadmin)
                    <div x-show="active === 'superadmin'" x-cloak class="space-y-6">

                        <div class="{{ $sectionCard }} border-purple-200 bg-gradient-to-br from-purple-50 to-white">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-purple-600 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="{{ $sectionTitle }}">Superadmin Panel</h3>
                                    <p class="{{ $sectionSub }}">Full application control — manage users, workspaces, plans, and role assignments.</p>
                                </div>
                            </div>
                        </div>

                        <div class="{{ $sectionCard }}">
                            <h4 class="text-sm font-semibold text-gray-900 mb-4">All Users ({{ $allUsers->count() }})</h4>

                            <form method="POST" action="{{ route('settings.superadmin.promote') }}" class="flex items-center gap-2 mb-4">
                                @csrf
                                <select name="user_id" class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="">Select user to promote to superadmin...</option>
                                    @foreach ($allUsers->where('id', '!=', auth()->id()) as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endforeach
                                </select>
                                <button type="submit"
                                    onclick="return confirm('Grant superadmin to this user?')"
                                    class="shrink-0 text-xs font-medium bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    Promote
                                </button>
                            </form>

                            <div class="overflow-x-auto">
                            <table class="w-full min-w-[540px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">User</th>
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Email</th>
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Workspaces</th>
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Status</th>
                                        <th class="text-right text-xs font-semibold text-gray-500 pb-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($allUsers as $u)
                                    <tr>
                                        <td class="py-2.5 pr-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-[10px] font-bold shrink-0">
                                                    {{ strtoupper(substr($u->name, 0, 2)) }}
                                                </div>
                                                <span class="text-xs font-medium text-gray-800">{{ $u->name }}</span>
                                            </div>
                                        </td>
                                        <td class="py-2.5 pr-4 text-xs text-gray-500">{{ $u->email }}</td>
                                        <td class="py-2.5 pr-4 text-xs text-gray-500">{{ $u->workspaces_count }}</td>
                                        <td class="py-2.5 pr-4">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $u->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">
                                                {{ $u->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                @if ($u->id !== auth()->id())
                                                    <form method="POST" action="{{ route('settings.superadmin.toggle-user', $u) }}" class="inline">
                                                        @csrf
                                                        <button type="submit"
                                                            onclick="return confirm('{{ $u->is_active ? 'Deactivate' : 'Activate' }} {{ $u->name }}?')"
                                                            class="text-[10px] font-medium px-2.5 py-1 rounded-md border {{ $u->is_active ? 'border-rose-200 text-rose-600 hover:bg-rose-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' }} transition-colors">
                                                            {{ $u->is_active ? 'Deactivate' : 'Activate' }}
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('settings.superadmin.demote', $u) }}" class="inline">
                                                        @csrf @method('DELETE')
                                                        <button type="submit"
                                                            onclick="return confirm('Remove superadmin from {{ $u->name }}?')"
                                                            class="text-[10px] font-medium px-2.5 py-1 rounded-md border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                                                            Remove SA
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-[10px] text-gray-300">you</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>

                        <div class="{{ $sectionCard }}">
                            <h4 class="text-sm font-semibold text-gray-900 mb-4">All Workspaces ({{ $allWorkspaces->count() }})</h4>
                            <div class="overflow-x-auto">
                            <table class="w-full min-w-[580px] text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100">
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Workspace</th>
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Owner</th>
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Members</th>
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Plan</th>
                                        <th class="text-left text-xs font-semibold text-gray-500 pb-2">Status</th>
                                        <th class="text-right text-xs font-semibold text-gray-500 pb-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    @foreach ($allWorkspaces as $ws)
                                    <tr>
                                        <td class="py-2.5 pr-4 text-xs font-medium text-gray-800">{{ $ws->name }}</td>
                                        <td class="py-2.5 pr-4 text-xs text-gray-500">{{ $ws->owner?->name ?? '—' }}</td>
                                        <td class="py-2.5 pr-4 text-xs text-gray-500">{{ $ws->members_count }}</td>
                                        <td class="py-2.5 pr-4">
                                            <form method="POST" action="{{ route('settings.superadmin.plan', $ws) }}" class="flex items-center gap-1" x-data>
                                                @csrf
                                                <select name="plan" onchange="this.form.submit()"
                                                    class="text-[10px] border border-gray-200 rounded-md px-1.5 py-1 outline-none focus:ring-1 focus:ring-purple-500 bg-white text-gray-700">
                                                    @foreach ($plans as $plan)
                                                        <option value="{{ $plan }}" {{ $ws->plan === $plan ? 'selected' : '' }}>{{ ucfirst($plan) }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td class="py-2.5 pr-4">
                                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $ws->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-600' }}">
                                                {{ $ws->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="py-2.5 text-right">
                                            <form method="POST" action="{{ route('settings.superadmin.toggle-workspace', $ws) }}" class="inline">
                                                @csrf
                                                <button type="submit"
                                                    onclick="return confirm('{{ $ws->is_active ? 'Deactivate' : 'Activate' }} workspace {{ $ws->name }}?')"
                                                    class="text-[10px] font-medium px-2.5 py-1 rounded-md border {{ $ws->is_active ? 'border-rose-200 text-rose-600 hover:bg-rose-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' }} transition-colors">
                                                    {{ $ws->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            </div>
                        </div>

                    </div>
                    @endif
                </div>
        </div>
    </div>
@endsection
