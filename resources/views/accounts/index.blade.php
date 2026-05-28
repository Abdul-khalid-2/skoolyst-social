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
                                <div class="space-y-1">
                                    @foreach ($connectedAccounts as $acc)
                                        @php
                                            $rowSt    = $accountsHelper->accountRowStatus($acc);
                                            $ph       = $platform->slug;
                                            $isActive = (bool) $acc->is_active;

                                            // Raw nullable stat values. null = "unavailable" -> render em-dash.
                                            // 0 = "API confirmed zero" -> render "0".
                                            $followersRaw = $acc->followers_count;
                                            $followingRaw = $acc->following_count;
                                            $postsRaw     = $acc->posts_count;

                                            // Facebook Pages: posts + followers only (no likes/following).
                                            // LinkedIn org pages: posts + followers only.
                                            // Instagram / LinkedIn personal: posts + followers + following.
                                            $accountType = (string) ($acc->meta['li_account_type'] ?? '');
                                            $isLinkedInOrg = $ph === 'linkedin' && $accountType === 'organization';
                                            $isFacebookPage = $ph === 'facebook';

                                            // Renders a stat number with em-dash fallback for null/unavailable.
                                            $renderStat = fn ($value) => $value === null
                                                ? '<span class="text-gray-400" title="'.e(__('Unavailable')).'">'.'&mdash;'.'</span>'
                                                : number_format((int) $value);

                                            // Stale = never synced, or synced more than 1 hour ago.
                                            $syncedAt = $acc->stats_synced_at;
                                            $isStale = $syncedAt === null
                                                || $syncedAt->lt(now()->subHour());

                                            // "Stats unavailable" only when every stat we actually display is null.
                                            if ($isFacebookPage || $isLinkedInOrg) {
                                                $allStatsNull = $followersRaw === null && $postsRaw === null;
                                            } else {
                                                $allStatsNull = $followersRaw === null
                                                    && $postsRaw === null
                                                    && $followingRaw === null;
                                            }
                                        @endphp
                                        <div class="flex items-start justify-between gap-3 py-2 border-t border-gray-100 first:border-t-0">
                                            {{-- Left: account info --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <p class="text-sm font-semibold text-gray-900 truncate">
                                                        {{ $acc->account_name ?? $platform->name }}
                                                    </p>
                                                    @if (! $isActive)
                                                        <span class="inline-flex items-center text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700">
                                                            {{ __('Paused') }}
                                                        </span>
                                                    @endif
                                                    @if ($isStale && ! $allStatsNull)
                                                        <span
                                                            class="inline-flex items-center text-gray-300 hover:text-amber-500 transition-colors"
                                                            title="{{ $syncedAt
                                                                ? __('Stats may be outdated (last synced :time). Click the refresh icon to update.', ['time' => $syncedAt->diffForHumans()])
                                                                : __('Stats have never been synced for this account. Click the refresh icon to fetch them.') }}"
                                                            aria-label="{{ __('Stats may be outdated') }}"
                                                        >
                                                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                                <circle cx="12" cy="12" r="10" />
                                                                <line x1="12" y1="8" x2="12" y2="12" />
                                                                <line x1="12" y1="16" x2="12.01" y2="16" />
                                                            </svg>
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="text-xs text-gray-500 mt-0.5">
                                                    {{ $acc->account_handle ?: __('Connected account') }}
                                                    @if ($allStatsNull)
                                                        · <span class="text-gray-400 italic">{{ __('Stats unavailable') }}</span>
                                                    @else
                                                        {{-- posts · followers; following only for personal-style accounts. --}}
                                                        · {!! $renderStat($postsRaw) !!} {{ __('posts') }}
                                                        · {!! $renderStat($followersRaw) !!} {{ __('followers') }}
                                                        @if (! $isFacebookPage && ! $isLinkedInOrg)
                                                            · {!! $renderStat($followingRaw) !!} {{ __('following') }}
                                                        @endif
                                                    @endif
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

                                            {{-- Right: action buttons --}}
                                            <div class="flex items-center gap-1.5 shrink-0">

                                                {{-- Refresh Stats (all platforms) --}}
                                                <form method="POST" action="{{ route('accounts.connections.refresh-stats', $acc) }}" class="inline">
                                                    @csrf
                                                    <button type="submit"
                                                        title="{{ __('Refresh stats') }}"
                                                        class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
                                                    >
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                                        </svg>
                                                    </button>
                                                </form>

                                                {{-- Active / Inactive toggle --}}
                                                <form method="POST" action="{{ route('accounts.connections.toggle-active', $acc) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button
                                                        type="submit"
                                                        title="{{ $isActive ? __('Pause publishing to this account') : __('Resume publishing to this account') }}"
                                                        class="relative inline-flex items-center w-9 h-5 rounded-full transition-colors focus:outline-none {{ $isActive ? 'bg-emerald-500 hover:bg-emerald-600' : 'bg-gray-300 hover:bg-gray-400' }}"
                                                    >
                                                        <span class="sr-only">{{ $isActive ? __('Pause') : __('Resume') }}</span>
                                                        <span class="inline-block w-3.5 h-3.5 bg-white rounded-full shadow transform transition-transform {{ $isActive ? 'translate-x-4' : 'translate-x-0.5' }}"></span>
                                                    </button>
                                                </form>

                                                {{-- Disconnect --}}
                                                <form method="POST"
                                                      action="{{ route('accounts.connections.destroy', $acc) }}"
                                                      class="inline"
                                                      onsubmit="return confirm('{{ __('Disconnect :name? This cannot be undone.', ['name' => addslashes($acc->account_name ?? $platform->name)]) }}')"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="border border-red-300 text-red-600 hover:bg-red-50 text-xs font-semibold px-3 py-1.5 rounded-xl active:scale-95 transition-all"
                                                    >
                                                        {{ __('Disconnect') }}
                                                    </button>
                                                </form>
                                            </div>
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
                                @if ($platform->slug === 'instagram')
                                    {{ __('Instagram Business accounts are linked through Meta. You’ll continue with Facebook login — approve Pages — and any Instagram Professional accounts linked to those Pages will appear here.') }}                                @elseif ($platform->slug === 'linkedin')
                                    {{ __('You will be redirected to LinkedIn to authorize Skoolyst Social AI to post on your behalf. Personal profile and organization pages you administer will be available for publishing.') }}                                @else
                                    {{ __('You will be redirected to :name to authorize Skoolyst Social AI to post on your behalf.', ['name' => $platform->name]) }}
                                @endif
                            </p>

                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    x-on:click="connectModal = null"
                                    class="flex-1 border border-gray-300 text-gray-700 rounded-xl py-2.5 text-sm font-semibold hover:bg-gray-50 active:scale-95 transition-all"
                                >
                                    {{ __('Cancel') }}
                                </button>
                                @if (in_array($platform->slug, ['facebook', 'instagram', 'linkedin'], true))
                                    {{-- Instagram Business uses the same Meta Facebook Login flow; IG accounts are linked after Pages are authorized. --}}
                                    <a
                                        href="
                                            @if ($platform->slug === 'linkedin')
                                                {{ route('api.auth.linkedin.redirect') }}
                                            @else
                                                {{ route('api.auth.facebook.redirect') }}
                                            @endif
                                        "
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
                    <li>{{ __('1) Use Connect on Facebook, Instagram, or LinkedIn — each uses its own OAuth login.') }}</li>
                    <li>{{ __('2) For Facebook: approve Page access; Instagram Business accounts linked to those Pages are saved automatically.') }}</li>
                    <li>{{ __('3) For LinkedIn: approve personal profile and/or organization pages you administer.') }}</li>
                    <li>{{ __('4) Then Create Post can publish to all connected accounts.') }}</li>
                </ul>
            </div>
        </div>
    </div>
@endsection
