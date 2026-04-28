@extends('layouts.app', [
    'title' => 'Profile',
    'description' => 'Update your account details and security.',
])

@php
    $nameTrim = trim((string) ($user->name ?? ''));
    $parts = preg_split('/\s+/', $nameTrim, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) > 1) {
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    } else {
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 2));
    }
@endphp

@section('content')
    <div class="p-0 max-w-4xl space-y-6">
        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <div class="flex items-center gap-4 mb-6">
                @if ($user->avatar)
                    <img
                        src="{{ $user->avatar }}"
                        alt="{{ $user->name ?? 'User avatar' }}"
                        class="w-16 h-16 rounded-full object-cover"
                    />
                @else
                    <div
                        class="w-16 h-16 rounded-full bg-gradient-to-br from-blue-500 to-cyan-400 flex items-center justify-center text-white text-lg font-semibold"
                    >
                        {{ $initials }}
                    </div>
                @endif
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">{{ $user->name ?? 'User' }}</h2>
                    <p class="text-sm text-gray-500">{{ $user->email ?? 'No email' }}</p>
                </div>
            </div>

            <h3 class="text-base font-semibold text-gray-900 mb-4">Profile</h3>
            <form method="post" action="{{ route('profile.update') }}" class="space-y-0">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-input
                            name="name"
                            label="Full Name"
                            value="{{ old('name', $user->name) }}"
                        />
                    </div>
                    <div>
                        <x-input
                            name="email"
                            label="Email"
                            type="email"
                            value="{{ old('email', $user->email) }}"
                        />
                    </div>
                    <div>
                        <x-input
                            name="timezone"
                            label="Timezone"
                            value="{{ old('timezone', $user->timezone ?? 'UTC') }}"
                            placeholder="UTC"
                        />
                    </div>
                    <div>
                        <label for="status-readonly" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <input
                            id="status-readonly"
                            type="text"
                            readonly
                            value="{{ $user->is_active === false ? 'Inactive' : 'Active' }}"
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none"
                        />
                    </div>
                </div>
                <div class="mt-5">
                    <x-button type="submit" variant="primary" class="font-semibold">Save Profile</x-button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-base font-semibold text-gray-900 mb-4">Security</h3>
            <form method="post" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <x-input
                            name="current_password"
                            label="Current Password"
                            type="password"
                            autocomplete="current-password"
                        />
                    </div>
                    <div>
                        <x-input
                            name="password"
                            label="New Password"
                            type="password"
                            autocomplete="new-password"
                        />
                    </div>
                    <div>
                        <x-input
                            name="password_confirmation"
                            label="Confirm New Password"
                            type="password"
                            autocomplete="new-password"
                        />
                    </div>
                </div>
                <div class="mt-5">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 bg-gray-900 text-white rounded-lg px-4 py-2 text-sm font-semibold hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2"
                    >
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
