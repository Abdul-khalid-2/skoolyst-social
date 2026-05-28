@extends('layouts.app', [
    'title' => 'Social Posts',
    'description' => 'View and manage posts across your connected accounts',
])

@section('content')
@php
    $workspaceName = auth()->user()?->workspaces()->wherePivot('is_active', true)->first()?->name ?? 'skoolyst workspace';

    $mockFacebookPosts = [
        ['id' => 'fb-1', 'caption' => 'Kya aapka school abhi bhi hidden hai digital duniya mein? Skoolyst se aaj hi apna school register karein.', 'page' => 'Skoolyst TV', 'status' => 'published', 'likes' => 24, 'comments' => 8, 'shares' => 3, 'published_at' => '2026-05-23 05:49'],
        ['id' => 'fb-2', 'caption' => 'Quiz night is live! Test your knowledge and win exciting prizes with Skoolyst Quiz.', 'page' => 'Skoolyst Quiz', 'status' => 'published', 'likes' => 12, 'comments' => 4, 'shares' => 1, 'published_at' => '2026-05-22 14:20'],
        ['id' => 'fb-3', 'caption' => 'New feature alert: schedule posts across all your pages from one dashboard.', 'page' => 'Skoolyst Social', 'status' => 'scheduled', 'likes' => 0, 'comments' => 0, 'shares' => 0, 'published_at' => '2026-05-28 09:00'],
        ['id' => 'fb-4', 'caption' => 'Visit Skoolyst Store for exclusive educational resources and bundles.', 'page' => 'Skoolyst Store', 'status' => 'failed', 'likes' => 0, 'comments' => 0, 'shares' => 0, 'published_at' => '2026-05-21 11:15'],
        ['id' => 'fb-5', 'caption' => 'Join the Skoolyst Hub community — connect with educators nationwide.', 'page' => 'Skoolyst Hub', 'status' => 'published', 'likes' => 45, 'comments' => 11, 'shares' => 6, 'published_at' => '2026-05-20 08:30'],
    ];

    $mockInstagramPosts = [
        ['id' => 'ig-1', 'caption' => 'Behind the scenes at Skoolyst App — building tools parents love.', 'account' => 'Skoolyst App', 'type' => 'Reel', 'status' => 'published', 'likes' => 156, 'comments' => 22, 'published_at' => '2026-05-24 18:00'],
        ['id' => 'ig-2', 'caption' => 'Carousel: 5 tips for choosing the right school for your child.', 'account' => 'Skoolyst App', 'type' => 'Carousel', 'status' => 'published', 'likes' => 89, 'comments' => 14, 'published_at' => '2026-05-23 12:00'],
        ['id' => 'ig-3', 'caption' => 'Photo dump from our team retreat — thank you for 1K followers!', 'account' => 'Skoolyst App', 'type' => 'Photo', 'status' => 'published', 'likes' => 201, 'comments' => 31, 'published_at' => '2026-05-22 09:45'],
    ];

    $mockLinkedInPosts = [
        ['id' => 'li-1', 'caption' => 'Excited to announce our partnership with leading schools across Pakistan.', 'profile' => 'Abdul Khalid', 'status' => 'published', 'reactions' => 18, 'comments' => 5, 'shares' => 2, 'published_at' => '2026-05-23 10:00'],
        ['id' => 'li-2', 'caption' => 'Hiring: we are looking for passionate educators to join our advisory board.', 'profile' => 'Abdul Khalid', 'status' => 'scheduled', 'reactions' => 0, 'comments' => 0, 'shares' => 0, 'published_at' => '2026-05-29 08:00'],
        ['id' => 'li-3', 'caption' => 'Thought leadership: the future of school discovery in South Asia.', 'profile' => 'Abdul Khalid', 'status' => 'published', 'reactions' => 42, 'comments' => 9, 'shares' => 7, 'published_at' => '2026-05-21 16:30'],
    ];

    $mockComments = [
        ['author' => 'Ahmed Raza', 'text' => 'Great initiative! 👏', 'date' => '2026-05-23 06:12', 'replies' => [
            ['author' => 'Skoolyst TV (Page)', 'text' => 'Thank you Ahmed! 🙏', 'date' => '2026-05-23 06:45'],
        ]],
        ['author' => 'Sara Khan', 'text' => 'When is it launching in Karachi?', 'date' => '2026-05-23 07:20', 'replies' => [
            ['author' => 'Skoolyst TV (Page)', 'text' => 'Very soon, stay tuned! 🚀', 'date' => '2026-05-23 08:01'],
        ]],
        ['author' => 'Hassan Ali', 'text' => 'Shared with my school group.', 'date' => '2026-05-23 09:00', 'replies' => []],
    ];
@endphp

<div
    class="-m-6 min-h-full"
    x-data="socialPostsPage({
        facebook: @js($mockFacebookPosts),
        instagram: @js($mockInstagramPosts),
        linkedin: @js($mockLinkedInPosts),
        comments: @js($mockComments),
    })"
>
    <div class="p-6 space-y-5">
        <div>
            <h1 class="text-lg font-bold text-gray-900">Social Posts</h1>
            <p class="text-sm text-gray-500 mt-0.5">View and manage posts across your connected accounts</p>
            <p class="text-xs text-gray-500 mt-1">
                Workspace: <span class="font-medium text-gray-700">{{ $workspaceName }}</span>
            </p>
        </div>

        {{-- Platform tabs --}}
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <div class="flex items-center gap-1 px-4 pt-4 border-b border-gray-100">
                <button type="button" @click="activeTab = 'facebook'"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="activeTab === 'facebook' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                    📘 Facebook
                </button>
                <button type="button" @click="activeTab = 'instagram'"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="activeTab === 'instagram' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                    📸 Instagram
                </button>
                <button type="button" @click="activeTab = 'linkedin'"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors"
                    :class="activeTab === 'linkedin' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'">
                    💼 LinkedIn
                </button>
            </div>

            {{-- Platform sub-header --}}
            <div class="border-b border-gray-100 bg-gray-50 px-4 py-3">
                <template x-if="activeTab === 'facebook'">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">📘 Facebook Pages</p>
                            <p class="text-xs text-gray-500 mt-0.5">Showing posts from all connected Facebook pages</p>
                        </div>
                        <select class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option>All Pages</option>
                            <option>Skoolyst TV</option>
                            <option>Skoolyst Quiz</option>
                            <option>Skoolyst Social</option>
                        </select>
                    </div>
                </template>
                <template x-if="activeTab === 'instagram'">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">📸 Instagram</p>
                        <p class="text-xs text-gray-500 mt-0.5">Showing posts from connected Instagram accounts</p>
                    </div>
                </template>
                <template x-if="activeTab === 'linkedin'">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">💼 LinkedIn</p>
                        <p class="text-xs text-gray-500 mt-0.5">Showing posts from connected LinkedIn profiles</p>
                    </div>
                </template>
            </div>

            {{-- Filters bar --}}
            <div class="px-4 py-3 border-b border-gray-100 flex flex-wrap items-center gap-3">
                <div class="relative flex-1 min-w-[180px] max-w-xs">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                    </svg>
                    <input type="search" placeholder="Search posts..." class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <select class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700">
                    <option>Status: All</option>
                    <option>Published</option>
                    <option>Scheduled</option>
                    <option>Failed</option>
                </select>
                <select class="text-sm border border-gray-200 rounded-lg px-3 py-2 bg-white text-gray-700">
                    <option>Date Range: All Time</option>
                    <option>Today</option>
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option>Custom Range</option>
                </select>
                <button type="button" title="Refresh stats" class="p-2 rounded-lg border border-gray-200 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                </button>
            </div>

            {{-- Loading skeleton (toggle with showLoading for demo) --}}
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

            {{-- Facebook table --}}
            <div x-show="!showLoading && activeTab === 'facebook'" class="overflow-x-auto">
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
                        <template x-for="post in posts.facebook" :key="post.id">
                            <tr @click="openDetail(post, 'facebook')" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-gray-200 rounded-lg shrink-0"></div>
                                        <span class="text-sm text-gray-800 line-clamp-2 max-w-xs" x-text="post.caption.slice(0, 80) + (post.caption.length > 80 ? '…' : '')"></span>
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
            <div x-show="!showLoading && activeTab === 'instagram'" class="overflow-x-auto">
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
                        <template x-for="post in posts.instagram" :key="post.id">
                            <tr @click="openDetail(post, 'instagram')" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors">
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-gray-200 rounded-lg shrink-0"></div>
                                        <span class="text-sm text-gray-800 line-clamp-2 max-w-xs" x-text="post.caption.slice(0, 80) + '…'"></span>
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

            {{-- LinkedIn table --}}
            <div x-show="!showLoading && activeTab === 'linkedin'" class="overflow-x-auto">
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
                        <template x-for="post in posts.linkedin" :key="post.id">
                            <tr @click="openDetail(post, 'linkedin')" class="border-b border-gray-50 hover:bg-gray-50 cursor-pointer transition-colors">
                                <td class="px-4 py-3.5">
                                    <span class="text-sm text-gray-800 line-clamp-2 max-w-md block" x-text="post.caption.slice(0, 80) + '…'"></span>
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

            {{-- Pagination --}}
            <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-gray-100">
                <button type="button" disabled class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">Previous</button>
                <span class="text-xs text-gray-500">Page 1 of 1</span>
                <button type="button" disabled class="px-3 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-400 cursor-not-allowed">Next</button>
            </div>
        </div>
    </div>

    {{-- Slide-over backdrop --}}
    <div
        x-show="detailOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-0 bg-gray-900/40 z-40"
        @click="closeDetail()"
    ></div>

    {{-- Post detail slide-over --}}
    <div
        x-show="detailOpen"
        x-cloak
        x-trap.inert.noscroll="detailOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        class="fixed inset-y-0 right-0 w-full max-w-2xl bg-white shadow-2xl z-50 flex flex-col overflow-hidden"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="closeDetail()"
    >
        <template x-if="selectedPost">
            <div class="flex flex-col h-full">
                <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 shrink-0">
                    <button type="button" @click="closeDetail()" class="text-sm text-gray-600 hover:text-gray-900 flex items-center gap-1">
                        ← Back to Social Posts
                    </button>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="editMode = !editMode" class="text-sm px-3 py-1.5 border border-gray-200 rounded-lg hover:bg-gray-50">Edit</button>
                        <button type="button" @click="showDeleteModal = true" class="text-sm px-3 py-1.5 border border-red-200 text-red-600 rounded-lg hover:bg-red-50">Delete</button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-5">
                    <div class="flex flex-col sm:flex-row gap-5">
                        <div class="w-full sm:w-48 h-48 bg-gray-200 rounded-xl shrink-0"></div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">
                                <span x-text="platformIcon(selectedPlatform)"></span>
                                <span x-text="detailAccountName()"></span>
                            </p>
                            <p class="text-xs text-gray-500 mt-1">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium" :class="statusClass(selectedPost.status)" x-text="capitalize(selectedPost.status)"></span>
                                <span class="mx-1">·</span>
                                <span x-text="selectedPost.published_at"></span>
                            </p>
                            <div class="mt-4">
                                <p class="text-xs font-medium text-gray-500 mb-1">Caption:</p>
                                <p x-show="!editMode" class="text-sm text-gray-800" x-text="selectedPost.caption"></p>
                                <textarea x-show="editMode" x-model="selectedPost.caption" rows="4" class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="rounded-xl border border-gray-200 p-4 text-center">
                            <p class="text-2xl font-bold text-gray-900" x-text="detailStat('likes')"></p>
                            <p class="text-xs text-gray-500 mt-1">❤️ Likes</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 text-center">
                            <p class="text-2xl font-bold text-gray-900" x-text="detailStat('comments')"></p>
                            <p class="text-xs text-gray-500 mt-1">💬 Comments</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4 text-center">
                            <p class="text-2xl font-bold text-gray-900" x-text="detailStat('shares')"></p>
                            <p class="text-xs text-gray-500 mt-1">↗️ Shares</p>
                        </div>
                    </div>

                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">💬 Comments (<span x-text="mockComments.length"></span>)</h3>
                        <div class="space-y-4">
                            <template x-for="(comment, idx) in mockComments" :key="idx">
                                <div>
                                    <div class="flex gap-3">
                                        <div class="w-8 h-8 rounded-full bg-gray-200 shrink-0"></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-sm font-semibold text-gray-900" x-text="comment.author"></span>
                                                <span class="text-[10px] text-gray-400 shrink-0" x-text="comment.date"></span>
                                            </div>
                                            <p class="text-sm text-gray-700 mt-0.5" x-text="comment.text"></p>
                                            <button type="button" class="text-xs text-blue-600 mt-1 hover:underline">↩ Reply</button>
                                        </div>
                                    </div>
                                    <template x-for="(reply, ridx) in comment.replies" :key="ridx">
                                        <div class="flex gap-3 mt-3 ml-11">
                                            <div class="w-7 h-7 rounded-full bg-gray-100 shrink-0"></div>
                                            <div class="flex-1">
                                                <div class="flex items-baseline justify-between gap-2">
                                                    <span class="text-xs font-semibold text-gray-800" x-text="reply.author"></span>
                                                    <span class="text-[10px] text-gray-400" x-text="reply.date"></span>
                                                </div>
                                                <p class="text-xs text-gray-600 mt-0.5" x-text="reply.text"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <button type="button" class="text-sm text-blue-600 hover:underline mt-2">Load more comments...</button>
                    </div>
                </div>

                <div class="shrink-0 border-t border-gray-200 p-4 flex gap-2">
                    <textarea rows="2" :placeholder="'Write a reply as ' + detailAccountName() + '...'" class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 resize-none focus:ring-2 focus:ring-blue-500"></textarea>
                    <button type="button" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 shrink-0">Send</button>
                </div>
            </div>
        </template>
    </div>

    {{-- Delete confirmation modal --}}
    <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-900/50" @click="showDeleteModal = false"></div>
        <div class="relative bg-white rounded-xl border border-gray-200 shadow-xl p-6 max-w-sm w-full">
            <h3 class="text-base font-semibold text-gray-900">Delete post?</h3>
            <p class="text-sm text-gray-600 mt-2">Are you sure you want to delete this post? This action cannot be undone.</p>
            <div class="flex justify-end gap-2 mt-5">
                <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="button" @click="showDeleteModal = false; closeDetail()" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>
</div>

@push('head')
<script>
function socialPostsPage(config) {
    return {
        activeTab: 'facebook',
        showLoading: false,
        posts: config,
        mockComments: config.comments,
        detailOpen: false,
        selectedPost: null,
        selectedPlatform: 'facebook',
        editMode: false,
        showDeleteModal: false,

        capitalize(s) {
            return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
        },
        statusClass(status) {
            const map = {
                published: 'bg-emerald-50 text-emerald-700',
                scheduled: 'bg-blue-50 text-blue-700',
                failed: 'bg-red-50 text-red-700',
            };
            return map[status] || 'bg-gray-100 text-gray-700';
        },
        openDetail(post, platform) {
            this.selectedPost = { ...post };
            this.selectedPlatform = platform;
            this.detailOpen = true;
            this.editMode = false;
            document.body.style.overflow = 'hidden';
        },
        closeDetail() {
            this.detailOpen = false;
            this.selectedPost = null;
            this.editMode = false;
            document.body.style.overflow = '';
        },
        platformIcon(p) {
            return { facebook: '📘', instagram: '📸', linkedin: '💼' }[p] || '🔗';
        },
        detailAccountName() {
            if (!this.selectedPost) return '';
            return this.selectedPost.page || this.selectedPost.account || this.selectedPost.profile || '';
        },
        detailStat(key) {
            if (!this.selectedPost) return 0;
            if (key === 'likes' && this.selectedPlatform === 'linkedin') return this.selectedPost.reactions ?? 0;
            return this.selectedPost[key] ?? 0;
        },
    };
}
</script>
@endpush
@endsection
