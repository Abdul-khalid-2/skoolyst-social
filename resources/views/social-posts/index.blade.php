@extends('layouts.app', [
    'title' => 'Social Posts',
    'description' => 'View and manage posts across your connected accounts',
])

@section('content')
@php
    $workspaceName = $workspace?->name ?? 'No workspace';
@endphp

<div
    class="-m-6 min-h-full"
    x-data="socialPostsPage({
        initialPlatform: @js($initialPlatform),
        posts: {
            facebook: @js($postsByPlatform['facebook'] ?? []),
            instagram: @js($postsByPlatform['instagram'] ?? []),
            linkedin: @js($postsByPlatform['linkedin'] ?? []),
            twitter: @js($postsByPlatform['twitter'] ?? []),
        },
        accounts: @js($postsByPlatform['accounts'] ?? []),
        refreshStatsUrl: @js($refreshStatsUrl),
        commentsUrlTemplate: @js($commentsUrlTemplate),
        csrf: @js(csrf_token()),
    })"
>
    <div class="p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
            <div>
                <h1 class="text-lg font-bold text-gray-900">Social Posts</h1>
                <p class="text-sm text-gray-500 mt-0.5">View and manage posts across your connected accounts</p>
                <p class="text-xs text-gray-500 mt-1">
                    Workspace: <span class="font-medium text-gray-700">{{ $workspaceName }}</span>
                </p>
            </div>
            <p x-show="refreshMessage" x-text="refreshMessage" class="text-xs text-gray-600 max-w-xs" x-cloak></p>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center gap-1 px-4 pt-4 border-b border-gray-100">
                <button type="button" @click="setTab('facebook')"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="activeTab === 'facebook' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                    📘 Facebook
                </button>
                <button type="button" @click="setTab('instagram')"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="activeTab === 'instagram' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                    📸 Instagram
                </button>
                <button type="button" @click="setTab('linkedin')"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="activeTab === 'linkedin' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                    💼 LinkedIn
                </button>
                <button type="button" @click="setTab('twitter')"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="activeTab === 'twitter' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                    𝕏 X
                </button>
            </div>

            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <template x-if="activeTab === 'facebook'">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">📘 Facebook Pages</p>
                            <p class="text-xs text-gray-500 mt-0.5">Published posts from your connected Facebook pages</p>
                        </div>
                        <select x-model="accountFilter.facebook" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Pages</option>
                            <template x-for="acc in accounts.facebook" :key="acc.id">
                                <option :value="acc.name" x-text="acc.name"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <template x-if="activeTab === 'instagram'">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">📸 Instagram</p>
                            <p class="text-xs text-gray-500 mt-0.5">Published posts from connected Instagram accounts</p>
                        </div>
                        <select x-model="accountFilter.instagram" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Accounts</option>
                            <template x-for="acc in accounts.instagram" :key="acc.id">
                                <option :value="acc.name" x-text="acc.name"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <template x-if="activeTab === 'linkedin'">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">💼 LinkedIn</p>
                            <p class="text-xs text-gray-500 mt-0.5">Published posts from connected LinkedIn profiles</p>
                        </div>
                        <select x-model="accountFilter.linkedin" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Profiles</option>
                            <template x-for="acc in accounts.linkedin" :key="acc.id">
                                <option :value="acc.name" x-text="acc.name"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <template x-if="activeTab === 'twitter'">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">𝕏 X</p>
                            <p class="text-xs text-gray-500 mt-0.5">Published posts from connected X accounts</p>
                        </div>
                        <select x-model="accountFilter.twitter" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">All Accounts</option>
                            <template x-for="acc in accounts.twitter" :key="acc.id">
                                <option :value="acc.name" x-text="acc.name"></option>
                            </template>
                        </select>
                    </div>
                </template>
            </div>

            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[180px] max-w-xs">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="search" x-model="searchQuery" placeholder="Search posts..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <select x-model="statusFilter" class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700">
                    <option value="">Status: All</option>
                    <option value="published">Published</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="failed">Failed</option>
                </select>
                <button type="button" @click="refreshStats()" :disabled="refreshing" title="Refresh stats"
                    class="p-2 rounded-lg border border-gray-200 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-colors disabled:opacity-50">
                    <svg class="w-4 h-4" :class="refreshing ? 'animate-spin' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </button>
            </div>

            <div x-show="showLoading" x-cloak class="p-4 space-y-3">
                @for ($i = 0; $i < 4; $i++)
                    <div class="flex gap-4 animate-pulse">
                        <div class="w-12 h-12 bg-gray-200 rounded-lg shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                @endfor
            </div>

            {{-- Empty state --}}
            <div x-show="!showLoading && filteredPosts().length === 0" x-cloak class="p-12 text-center">
                <p class="text-4xl mb-3" x-text="platformIcon(activeTab)"></p>
                <p class="text-sm font-medium text-gray-900">No posts found for this account.</p>
                <p class="text-xs text-gray-500 mt-1">Publish a post from Create Post, then refresh stats here.</p>
                <a href="{{ route('create') }}" class="inline-flex mt-4 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Create Post</a>
            </div>

            {{-- Facebook table --}}
            <div x-show="!showLoading && activeTab === 'facebook' && filteredPosts().length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Post</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Page</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Status</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">❤️ Likes</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">💬 Comments</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">↗️ Shares</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">📅 Published</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="post in paginatedPosts()" :key="post.id">
                            <tr @click="openDetail(post, 'facebook')" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <template x-if="post.thumbnail">
                                            <img :src="post.thumbnail" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0" />
                                        </template>
                                        <template x-if="!post.thumbnail">
                                            <div class="w-10 h-10 bg-gray-200 rounded-lg shrink-0"></div>
                                        </template>
                                        <span class="text-sm text-gray-800 line-clamp-2 max-w-xs" x-text="truncateCaption(post.caption)"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-gray-600" x-text="post.page"></td>
                                <td class="px-4 py-3.5"><span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full" :class="statusClass(post.status)" x-text="capitalize(post.status)"></span></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.likes"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.comments"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.shares"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-600 whitespace-nowrap" x-text="post.published_at"></td>
                                <td class="px-4 py-3.5">
                                    <button type="button" @click.stop="openDetail(post, 'facebook')" class="text-xs font-medium text-blue-600 hover:underline">👁 View</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- Instagram table --}}
            <div x-show="!showLoading && activeTab === 'instagram' && filteredPosts().length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[800px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Post</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Account</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Type</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Status</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">❤️ Likes</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">💬 Comments</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">📅 Published</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="post in paginatedPosts()" :key="post.id">
                            <tr @click="openDetail(post, 'instagram')" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <template x-if="post.thumbnail">
                                            <img :src="post.thumbnail" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0" />
                                        </template>
                                        <template x-if="!post.thumbnail">
                                            <div class="w-10 h-10 bg-gray-200 rounded-lg shrink-0"></div>
                                        </template>
                                        <span class="text-sm text-gray-800 line-clamp-2 max-w-xs" x-text="truncateCaption(post.caption)"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-gray-600" x-text="post.account"></td>
                                <td class="px-4 py-3.5"><span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full bg-purple-50 text-purple-700" x-text="post.type"></span></td>
                                <td class="px-4 py-3.5"><span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full" :class="statusClass(post.status)" x-text="capitalize(post.status)"></span></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.likes"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.comments"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-600 whitespace-nowrap" x-text="post.published_at"></td>
                                <td class="px-4 py-3.5">
                                    <button type="button" @click.stop="openDetail(post, 'instagram')" class="text-xs font-medium text-blue-600 hover:underline">👁 View</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- X (Twitter) table --}}
            <div x-show="!showLoading && activeTab === 'twitter' && filteredPosts().length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[800px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Post</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Account</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Status</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">❤️ Likes</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">💬 Replies</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">🔁 Retweets</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">📅 Published</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="post in paginatedPosts()" :key="post.id">
                            <tr @click="openDetail(post, 'twitter')" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <template x-if="post.thumbnail">
                                            <img :src="post.thumbnail" alt="" class="w-10 h-10 rounded-lg object-cover shrink-0" />
                                        </template>
                                        <template x-if="!post.thumbnail">
                                            <div class="w-10 h-10 bg-gray-200 rounded-lg shrink-0"></div>
                                        </template>
                                        <span class="text-sm text-gray-800 line-clamp-2 max-w-xs" x-text="truncateCaption(post.caption)"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-gray-600" x-text="post.handle"></td>
                                <td class="px-4 py-3.5"><span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full" :class="statusClass(post.status)" x-text="capitalize(post.status)"></span></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.likes"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.comments"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.shares"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-600 whitespace-nowrap" x-text="post.published_at"></td>
                                <td class="px-4 py-3.5">
                                    <button type="button" @click.stop="openDetail(post, 'twitter')" class="text-xs font-medium text-blue-600 hover:underline">👁 View</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            {{-- LinkedIn table --}}
            <div x-show="!showLoading && activeTab === 'linkedin' && filteredPosts().length > 0" class="overflow-x-auto">
                <table class="w-full min-w-[800px]">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Post</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Profile</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Status</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">👍 Reactions</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">💬 Comments</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">↗️ Shares</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">📅 Published</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="post in paginatedPosts()" :key="post.id">
                            <tr @click="openDetail(post, 'linkedin')" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors">
                                <td class="px-4 py-3.5">
                                    <span class="text-sm text-gray-800 line-clamp-2 max-w-md block" x-text="truncateCaption(post.caption)"></span>
                                </td>
                                <td class="px-4 py-3.5 text-sm text-gray-600" x-text="post.profile"></td>
                                <td class="px-4 py-3.5"><span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full" :class="statusClass(post.status)" x-text="capitalize(post.status)"></span></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.reactions"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.comments"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-700" x-text="post.shares"></td>
                                <td class="px-4 py-3.5 text-sm text-gray-600 whitespace-nowrap" x-text="post.published_at"></td>
                                <td class="px-4 py-3.5">
                                    <button type="button" @click.stop="openDetail(post, 'linkedin')" class="text-xs font-medium text-blue-600 hover:underline">👁 View</button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div x-show="!showLoading && filteredPosts().length > 0" class="flex items-center justify-end gap-2 px-4 py-3 border-t border-gray-100">
                <button type="button" @click="prevPage()" :disabled="currentPage <= 1" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg disabled:text-gray-400 disabled:cursor-not-allowed hover:bg-gray-50">Previous</button>
                <span class="text-xs text-gray-500" x-text="'Page ' + currentPage + ' of ' + totalPages()"></span>
                <button type="button" @click="nextPage()" :disabled="currentPage >= totalPages()" class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg disabled:text-gray-400 disabled:cursor-not-allowed hover:bg-gray-50">Next</button>
            </div>
        </div>
    </div>

    {{-- Teleport to body so the panel is not clipped by main overflow --}}
    <template x-teleport="body">
        <div
            x-show="detailOpen"
            x-cloak
            class="fixed inset-0 z-[9999]"
            role="presentation"
            @keydown.escape.window="closeDetail()"
        >
            <div
                class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"
                x-show="detailOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="closeDetail()"
            ></div>

            <div class="absolute inset-0 flex items-end sm:items-center justify-center sm:p-4 md:p-6 pointer-events-none">
                <div
                    x-show="detailOpen && selectedPost"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0 sm:scale-95"
                    x-transition:enter-end="translate-y-0 sm:opacity-100 sm:scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-y-0 sm:opacity-100 sm:scale-100"
                    x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0 sm:scale-95"
                    class="pointer-events-auto w-full sm:max-w-2xl md:max-w-3xl lg:max-w-4xl bg-white shadow-2xl flex flex-col overflow-hidden rounded-t-2xl sm:rounded-2xl h-[92dvh] sm:h-auto sm:max-h-[90dvh]"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="social-post-detail-title"
                    @click.stop
                >
                    <div class="flex items-center justify-between gap-2 px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 shrink-0 pt-[max(0.75rem,env(safe-area-inset-top))]">
                        <div class="flex items-center gap-2 min-w-0">
                            <button type="button" @click="closeDetail()" class="sm:hidden p-1.5 -ml-1 text-gray-500 hover:text-gray-900 rounded-lg hover:bg-gray-100" aria-label="Back">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <h2 id="social-post-detail-title" class="text-base sm:text-lg font-semibold text-gray-900 truncate">
                                <span x-text="platformIcon(selectedPlatform)"></span>
                                <span class="hidden sm:inline">Post Details</span>
                                <span class="sm:hidden" x-text="detailAccountName()"></span>
                            </h2>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" class="hidden sm:inline-flex text-sm px-3 py-1.5 border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed" disabled title="Coming soon">Edit</button>
                            <button type="button" class="hidden sm:inline-flex text-sm px-3 py-1.5 border border-red-200 text-red-400 rounded-lg cursor-not-allowed" disabled title="Coming soon">Delete</button>
                            <button type="button" @click="closeDetail()" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100" aria-label="Close">
                                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-4 sm:px-6 py-4 sm:py-5 space-y-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:gap-5">
                            <div class="shrink-0 mx-auto sm:mx-0">
                                <img
                                    x-show="selectedPost && selectedPost.thumbnail"
                                    :src="selectedPost?.thumbnail"
                                    alt=""
                                    class="w-32 h-32 sm:w-40 sm:h-40 md:w-44 md:h-44 rounded-xl object-cover border border-gray-200"
                                />
                                <div
                                    x-show="selectedPost && !selectedPost.thumbnail"
                                    class="w-32 h-32 sm:w-40 sm:h-40 md:w-44 md:h-44 bg-gray-100 rounded-xl border border-gray-200 flex items-center justify-center text-gray-400"
                                >
                                    <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0 w-full">
                                <p class="text-sm sm:text-base font-semibold text-gray-900 break-words">
                                    <span x-text="detailAccountName()"></span>
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(selectedPost?.status)" x-text="capitalize(selectedPost?.status)"></span>
                                    <span class="text-xs text-gray-500" x-text="selectedPost?.published_at"></span>
                                </div>
                                <p x-show="selectedPost?.platform_post_id" class="text-[10px] text-gray-400 mt-2 break-all" x-text="'ID: ' + (selectedPost?.platform_post_id || '')"></p>
                                <div class="mt-3 sm:mt-4">
                                    <p class="text-xs font-medium text-gray-500 mb-1">Caption</p>
                                    <p class="text-sm text-gray-800 whitespace-pre-wrap break-words leading-relaxed" x-text="selectedPost?.caption || '—'"></p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-row gap-2 sm:gap-3 w-full">
                            <div class="flex-1 basis-0 rounded-xl border border-gray-200 p-2.5 sm:p-4 text-center min-w-0 bg-gray-50/50">
                                <p class="text-base sm:text-2xl font-bold text-gray-900 truncate" x-text="detailStat('likes')"></p>
                                <p class="text-[10px] sm:text-xs text-gray-500 mt-0.5 sm:mt-1 leading-tight truncate" x-text="selectedPlatform === 'linkedin' ? '👍 Reactions' : '❤️ Likes'"></p>
                            </div>
                            <div class="flex-1 basis-0 rounded-xl border border-gray-200 p-2.5 sm:p-4 text-center min-w-0 bg-gray-50/50">
                                <p class="text-base sm:text-2xl font-bold text-gray-900 truncate" x-text="detailStat('comments')"></p>
                                <p class="text-[10px] sm:text-xs text-gray-500 mt-0.5 sm:mt-1 truncate">💬 Comments</p>
                            </div>
                            <div class="flex-1 basis-0 rounded-xl border border-gray-200 p-2.5 sm:p-4 text-center min-w-0 bg-gray-50/50">
                                <p class="text-base sm:text-2xl font-bold text-gray-900 truncate" x-text="detailStat('shares')"></p>
                                <p class="text-[10px] sm:text-xs text-gray-500 mt-0.5 sm:mt-1 truncate">↗️ Shares</p>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3 sm:mb-4">💬 Comments (<span x-text="loadedComments.length"></span>)</h3>
                            <p x-show="commentsLoading" class="text-sm text-gray-500">Loading comments…</p>
                            <p x-show="commentsError" class="text-sm text-red-600" x-text="commentsError"></p>
                            <p x-show="!commentsLoading && !commentsError && selectedPost && !selectedPost.has_platform_id" class="text-sm text-gray-500">Comments are available after the post is published on the platform.</p>
                            <div class="space-y-4" x-show="!commentsLoading && loadedComments.length > 0">
                                <template x-for="(comment, idx) in loadedComments" :key="idx">
                                    <div>
                                        <div class="flex gap-3">
                                            <div class="w-8 h-8 rounded-full bg-gray-200 shrink-0"></div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-baseline justify-between gap-2 flex-wrap">
                                                    <span class="text-sm font-semibold text-gray-900 break-words" x-text="comment.author"></span>
                                                    <span class="text-[10px] text-gray-400 shrink-0" x-text="comment.date"></span>
                                                </div>
                                                <p class="text-sm text-gray-700 mt-0.5 break-words" x-text="comment.text"></p>
                                            </div>
                                        </div>
                                        <template x-for="(reply, ridx) in (comment.replies || [])" :key="ridx">
                                            <div class="flex gap-2 sm:gap-3 mt-3 ml-6 sm:ml-11">
                                                <div class="w-7 h-7 rounded-full bg-gray-100 shrink-0"></div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-baseline justify-between gap-2 flex-wrap">
                                                        <span class="text-xs font-semibold text-gray-800 break-words" x-text="reply.author"></span>
                                                        <span class="text-[10px] text-gray-400 shrink-0" x-text="reply.date"></span>
                                                    </div>
                                                    <p class="text-xs text-gray-600 mt-0.5 break-words" x-text="reply.text"></p>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <p x-show="!commentsLoading && selectedPost?.has_platform_id && loadedComments.length === 0 && !commentsError" class="text-sm text-gray-500">No comments yet.</p>
                        </div>
                    </div>

                    <div class="shrink-0 border-t border-gray-200 px-4 sm:px-6 py-3 sm:py-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] bg-gray-50/50">
                        <div class="flex flex-col sm:flex-row gap-2 sm:items-end">
                            <textarea rows="2" disabled :placeholder="'Write a reply as ' + detailAccountName() + '... (coming soon)'" class="w-full flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none bg-white text-gray-400 min-h-[2.5rem] focus:outline-none"></textarea>
                            <button type="button" disabled class="w-full sm:w-auto px-5 py-2.5 sm:py-2 bg-gray-200 text-gray-500 text-sm font-medium rounded-lg shrink-0 cursor-not-allowed">Send</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@push('head')
<script>
function socialPostsPage(config) {
    return {
        activeTab: config.initialPlatform || 'facebook',
        showLoading: false,
        refreshing: false,
        refreshMessage: '',
        posts: config.posts,
        accounts: config.accounts || { facebook: [], instagram: [], linkedin: [], twitter: [] },
        accountFilter: { facebook: '', instagram: '', linkedin: '', twitter: '' },
        searchQuery: '',
        statusFilter: '',
        currentPage: 1,
        perPage: 10,
        refreshStatsUrl: config.refreshStatsUrl,
        commentsUrlTemplate: config.commentsUrlTemplate,
        csrf: config.csrf,
        detailOpen: false,
        selectedPost: null,
        selectedPlatform: 'facebook',
        editMode: false,
        showDeleteModal: false,
        loadedComments: [],
        commentsLoading: false,
        commentsError: '',

        init() {
            const params = new URLSearchParams(window.location.search);
            const platform = params.get('platform');
            if (['facebook', 'instagram', 'linkedin', 'twitter'].includes(platform)) {
                this.activeTab = platform;
            }
        },

        setTab(tab) {
            this.activeTab = tab;
            this.currentPage = 1;
            const url = new URL(window.location.href);
            url.searchParams.set('platform', tab);
            window.history.replaceState({}, '', url);
        },

        filteredPosts() {
            let list = this.posts[this.activeTab] || [];
            const accountKey = this.activeTab;
            const filterName = this.accountFilter[accountKey];
            if (filterName) {
                list = list.filter((p) => {
                    const name = p.page || p.account || p.profile || p.handle || '';
                    return name === filterName;
                });
            }
            if (this.statusFilter) {
                list = list.filter((p) => p.status === this.statusFilter);
            }
            if (this.searchQuery.trim()) {
                const q = this.searchQuery.trim().toLowerCase();
                list = list.filter((p) => (p.caption || '').toLowerCase().includes(q));
            }
            return list;
        },

        paginatedPosts() {
            const list = this.filteredPosts();
            const start = (this.currentPage - 1) * this.perPage;
            return list.slice(start, start + this.perPage);
        },

        totalPages() {
            return Math.max(1, Math.ceil(this.filteredPosts().length / this.perPage));
        },

        prevPage() {
            if (this.currentPage > 1) this.currentPage--;
        },

        nextPage() {
            if (this.currentPage < this.totalPages()) this.currentPage++;
        },

        async refreshStats() {
            if (!this.refreshStatsUrl) return;
            this.refreshing = true;
            this.refreshMessage = '';
            const targetIds = this.filteredPosts()
                .filter((p) => p.has_platform_id && p.status === 'published')
                .slice(0, 12)
                .map((p) => p.target_id);
            try {
                const res = await fetch(this.refreshStatsUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        platform: this.activeTab,
                        target_ids: targetIds.length > 0 ? targetIds : undefined,
                    }),
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.refreshMessage = data.message || 'Refresh failed.';
                    return;
                }
                if (data.posts) {
                    this.posts.facebook = data.posts.facebook || this.posts.facebook;
                    this.posts.instagram = data.posts.instagram || this.posts.instagram;
                    this.posts.linkedin = data.posts.linkedin || this.posts.linkedin;
                    this.posts.twitter = data.posts.twitter || this.posts.twitter;
                }
                this.refreshMessage = data.message || `Synced ${data.synced || 0} post(s).`;
            } catch (e) {
                this.refreshMessage = 'Network error while refreshing stats.';
            } finally {
                this.refreshing = false;
            }
        },

        truncateCaption(text) {
            if (!text) return '';
            return text.length > 80 ? text.slice(0, 80) + '…' : text;
        },

        capitalize(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
        },

        statusClass(status) {
            const map = {
                published: 'bg-emerald-50 text-emerald-700',
                scheduled: 'bg-blue-50 text-blue-700',
                failed: 'bg-red-50 text-red-700',
                pending: 'bg-gray-100 text-gray-700',
            };
            return map[status] || 'bg-gray-100 text-gray-700';
        },

        openDetail(post, platform) {
            this.selectedPost = { ...post };
            this.selectedPlatform = platform;
            this.detailOpen = true;
            this.editMode = false;
            this.loadedComments = [];
            this.commentsError = '';
            this.commentsLoading = false;
            document.documentElement.classList.add('overflow-hidden');
            document.body.classList.add('overflow-hidden');
            if (post.has_platform_id && post.target_id) {
                this.loadComments(post.target_id);
            }
        },

        async loadComments(targetId) {
            const url = this.commentsUrlTemplate.replace('__TARGET__', String(targetId));
            this.commentsLoading = true;
            this.commentsError = '';
            try {
                const res = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    this.commentsError = data.message || 'Could not load comments.';
                    return;
                }
                this.loadedComments = data.comments || [];
            } catch (e) {
                this.commentsError = 'Network error loading comments.';
            } finally {
                this.commentsLoading = false;
            }
        },

        closeDetail() {
            this.detailOpen = false;
            this.showDeleteModal = false;
            this.loadedComments = [];
            this.commentsError = '';
            document.documentElement.classList.remove('overflow-hidden');
            document.body.classList.remove('overflow-hidden');
            setTimeout(() => {
                if (!this.detailOpen) {
                    this.selectedPost = null;
                }
            }, 250);
        },

        platformIcon(p) {
            return { facebook: '📘', instagram: '📸', linkedin: '💼', twitter: '𝕏' }[p] || '🔗';
        },

        detailAccountName() {
            if (!this.selectedPost) return '';
            return this.selectedPost.page || this.selectedPost.account || this.selectedPost.profile || this.selectedPost.handle || '';
        },

        detailStat(key) {
            if (!this.selectedPost) return '—';
            if (key === 'likes' && this.selectedPlatform === 'linkedin') {
                return this.selectedPost.reactions ?? '—';
            }
            return this.selectedPost[key] ?? '—';
        },
    };
}
</script>
@endpush
@endsection
