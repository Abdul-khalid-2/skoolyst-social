@php
    use App\Models\SocialAccount;
    use App\Services\AccountListingService;
@endphp
@inject('accountsHelper', AccountListingService::class)

@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('content')
    <div class="p-6" x-data="{ showGuide: false, connectModal: null }">
        <x-page-header
            :title="__('Social Accounts')"
            :description="__('Connect and manage your social media accounts')"
        >
            <x-slot name="actions">
                <button
                    type="button"
                    x-on:click="showGuide = true"
                    class="w-7 h-7 rounded-full border border-gray-300 text-gray-500 hover:text-gray-700 hover:bg-gray-50 flex items-center justify-center"
                    title="{{ __('Connection guidance') }}"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" />
                        <path d="M12 16v-4M12 8h.01" stroke-linecap="round" />
                    </svg>
                </button>
            </x-slot>
        </x-page-header>

        @if ($workspace)
            <div class="text-xs text-gray-500 mb-4">
                {{ __('Workspace:') }} <span class="font-medium text-gray-800">{{ $workspace->name }}</span>
            </div>
        @endif

        @if ($workspace === null)
            <p class="text-sm text-gray-600">{{ __('No workspace found.') }}</p>
        @elseif ($rows->isEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5"></div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach ($rows as $row)
                    @php
                        $platform = $row->platform;
                        $g = $accountsHelper->gradientFor($platform);
                        $gradStyle = "linear-gradient(to right, {$g['from']}, {$g['to']})";
                        $accList = $row->accounts;
                        $connectedAccounts = $accList->filter(fn (SocialAccount $a) => (bool) $a->is_connected);
                    @endphp
                    <div
                        class="bg-white rounded-xl border border-gray-200 overflow-hidden hover:shadow-md transition-shadow duration-200"
                    >
                        <div
                            class="h-2"
                            style="background: {{ $gradStyle }}"
                        ></div>
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div
                                    class="w-12 h-12 rounded-2xl text-white font-bold text-xl flex items-center justify-center"
                                    style="background: {{ $gradStyle }}"
                                >
                                    {{ $accountsHelper->shortBadgeLabel($platform) }}
                                </div>
                                @if ($row->connected)
                                    <span
                                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" aria-hidden="true"></span>
                                        {{ __('Connected') }}
                                    </span>
                                @endif
                            </div>

                            <h3 class="font-bold text-gray-900">{{ $platform->name }}</h3>
                            <p class="text-xs text-gray-500 mt-1 mb-4">
                                {{ __('Connect your :name account to enable publishing.', ['name' => $platform->name]) }}
                            </p>

                            @if ($row->connected)
                                <div class="space-y-4">
                                    @foreach ($connectedAccounts as $acc)
                                        @php
                                            $rowSt = $accountsHelper->accountRowStatus($acc);
                                        @endphp
                                        <div class="flex items-center justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900">{{ $acc->account_name ?? $platform->name }}</p>
                                                <p class="text-xs text-gray-500">
                                                    {{ $acc->account_handle ?: __('Connected account') }}
                                                    · {{ (int) $acc->followers_count }} {{ __('followers') }}
                                                </p>
                                                @if ($acc->token_expires_at
                                                    && $acc->token_expires_at->isFuture()
                                                    && $acc->token_expires_at->getTimestamp() - now()->getTimestamp() < 3 * 24 * 60 * 60)
                                                    <p class="text-xs text-amber-600 mt-1">{{ __('Token expiring soon. Reconnect recommended.') }}</p>
                                                @endif
                                                @if ($rowSt === 'expired')
                                                    <p class="text-xs text-red-600 mt-1">{{ __('Token expired. Reconnect required.') }}</p>
                                                @endif
                                            </div>
                                            <form
                                                method="post"
                                                action="{{ route('accounts.connections.destroy', $acc) }}"
                                                class="shrink-0"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="border border-red-300 text-red-600 hover:bg-red-50 text-xs font-semibold px-4 py-2 rounded-xl active:scale-95 transition-all disabled:opacity-60"
                                                >
                                                    {{ __('Disconnect') }}
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div>
                                    <button
                                        type="button"
                                        x-on:click="connectModal = '{{ $platform->slug }}'"
                                        class="w-full py-2.5 text-white font-semibold text-sm rounded-xl active:scale-95 transition-all disabled:opacity-60"
                                        style="background: {{ $gradStyle }}"
                                    >
                                        {{ $row->status === 'expired' ? __('Reconnect') : __('Connect Account') }}
                                    </button>
                                    <p class="text-xs text-gray-400 text-center mt-2">{{ __('Authorize via OAuth 2.0') }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @foreach ($rows as $row)
                @if (! $row->connected)
                    @php
                        $platform = $row->platform;
                        $g = $accountsHelper->gradientFor($platform);
                        $gradStyle = "linear-gradient(to right, {$g['from']}, {$g['to']})";
                    @endphp
                    <div
                        x-show="connectModal === '{{ $platform->slug }}'"
                        x-cloak
                        class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
                    >
                        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 animate-scale-in">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-bold text-gray-900">{{ $platform->name }}</h2>
                                <button
                                    type="button"
                                    x-on:click="connectModal = null"
                                    class="w-8 h-8 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 active:scale-95 transition-all flex items-center justify-center"
                                >
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" />
                                    </svg>
                                </button>
                            </div>

                            <div
                                class="w-14 h-14 rounded-2xl mx-auto mb-4 text-white font-bold text-xl flex items-center justify-center"
                                style="background: {{ $gradStyle }}"
                            >
                                {{ $accountsHelper->shortBadgeLabel($platform) }}
                            </div>

                            <p class="text-center text-sm text-gray-600 mb-6">
                                {{ __('You will be redirected to :name to authorize Skoolyst Social AI to post on your behalf.', ['name' => $platform->name]) }}
                            </p>

                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    x-on:click="connectModal = null"
                                    class="flex-1 border border-gray-300 text-gray-700 rounded-xl py-2.5 text-sm font-semibold hover:bg-gray-50 active:scale-95 transition-all"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                @if ($platform->slug === 'facebook')
                                    <a
                                        href="{{ route('api.auth.facebook.redirect') }}"
                                        class="flex-1 text-center text-white rounded-xl py-2.5 text-sm font-semibold active:scale-95 transition-all"
                                        style="background: {{ $gradStyle }}"
                                    >
                                        {{ __('Continue') }}
                                    </a>
                                @else
                                    <button
                                        type="button"
                                        disabled
                                        class="flex-1 text-white rounded-xl py-2.5 text-sm font-semibold active:scale-95 transition-all disabled:opacity-60"
                                        style="background: {{ $gradStyle }}"
                                    >
                                        {{ __('Continue') }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        <div
            x-show="showGuide"
            x-cloak
            class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
        >
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold text-gray-900">{{ __('How connection works') }}</h2>
                    <button
                        type="button"
                        x-on:click="showGuide = false"
                        class="w-8 h-8 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 flex items-center justify-center"
                    >
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M18 6L6 18M6 6l12 12" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
                <ul class="text-sm text-gray-700 space-y-2">
                    <li>{{ __('1) Login with Facebook first.') }}</li>
                    <li>{{ __('2) Open Accounts and click Connect on Facebook.') }}</li>
                    <li>{{ __('3) After connected, Create Post enables Facebook publishing.') }}</li>
                    <li>{{ __('4) Other platforms will remain disabled until their OAuth flow is added.') }}</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
