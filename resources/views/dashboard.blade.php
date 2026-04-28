@extends('layouts.app', [
    'title' => 'Dashboard',
    'description' => 'Overview and performance metrics for your school social media.',
    'subtitle' => null,
])

@section('content')
    <div class="max-w-6xl space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <x-stat-card title="Total Posts" :value="(string) $totalPosts" :change="0" changeLabel="" color="blue">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                    <path d="M14 2v6h6M8 13h8M8 17h5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </x-stat-card>
            <x-stat-card title="Scheduled Posts" :value="(string) $scheduledPosts" :change="0" changeLabel="" color="emerald">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
                    <path d="M12 7v5l3 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </x-stat-card>
            <x-stat-card title="Posted Today" :value="(string) $postedToday" :change="0" changeLabel="" color="amber">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" />
                </svg>
            </x-stat-card>
            <x-stat-card title="Connected Accounts" :value="(string) $connectedAccounts" :change="0" changeLabel="" color="rose">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M10 13a4 4 0 0 0 4 4l1.5-1.5M14 6l2 2M4 7V4h3M4 7h.01M4 12h.01M4 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    <path d="M4 4l5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </x-stat-card>
        </div>

        <div class="flex gap-3">
            <x-button :href="url('/create')" variant="primary" class="!rounded-lg px-4 py-2.5">+ Create Post</x-button>
            <x-button
                :href="url('/scheduled')"
                variant="secondary"
                class="!rounded-lg px-4 py-2.5 !border !border-gray-300 !bg-white !text-gray-700 hover:!bg-gray-50"
            >
                View Scheduled
            </x-button>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2">
                <x-revenue-chart class="mt-0" :platform-stats="$platformStats" />
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-5 h-fit">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-semibold text-gray-900">Recent Activity</h3>
                    <a href="{{ url('/activity') }}" class="text-xs text-blue-600 hover:text-blue-700 font-medium">View all</a>
                </div>
                <div class="space-y-3.5">
                    @forelse ($recentActivity as $item)
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-semibold text-gray-600 shrink-0" aria-hidden="true">
                                {{ $item['badge'] }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm text-gray-800 line-clamp-2">{{ $item['snippet'] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <span class="text-gray-600">{{ $item['platform'] }}</span>
                                    <span class="text-gray-400"> · </span>
                                    <time datetime="{{ $item['time_iso'] ?? '' }}">{{ $item['time_human'] }}</time>
                                </p>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No recent posts yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
