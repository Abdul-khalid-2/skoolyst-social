@php
    use App\Services\PostListingService;
    use Illuminate\Support\Str;
@endphp
@inject('postListing', PostListingService::class)

@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('content')
@php
    $tabs = [
        'All' => 'all',
        'Published' => 'published',
        'Draft' => 'draft',
        'Scheduled' => 'scheduled',
        'Publishing' => 'publishing',
        'Failed' => 'failed',
    ];
    $statusBadgeVariant = static fn (string $s): string => match ($s) {
        'published' => 'success',
        'draft' => 'secondary',
        'scheduled' => 'primary',
        'publishing' => 'warning',
        'failed' => 'danger',
        default => 'secondary',
    };
    $statusLabel = static fn (string $s): string => $s === '' ? '—' : (ucfirst($s));
@endphp

    <div class="flex flex-col min-h-full">
        <div class="flex-1 p-6">
            @if ($workspace === null)
                <x-empty-state
                    class="!min-h-[200px] flex items-center"
                    :title="__('No workspace')"
                    :description="__('Create an account and workspace to see posts.')"
                >
                    <x-slot name="icon">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </x-slot>
                </x-empty-state>
            @else
                <div class="text-xs text-gray-500 mb-3">
                    Workspace: <span class="font-medium text-gray-700">{{ $workspace->name }}</span>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-1 px-4 pt-4 border-b border-gray-100">
                        @foreach ($tabs as $label => $key)
                            @php
                                $isActive = $filter === $key;
                                $tabHref = $key === 'all' ? route('posts.index') : route('posts.index', ['status' => $key]);
                                $count = $key === 'all' ? $tabCounts['all'] : ($tabCounts[$key] ?? 0);
                            @endphp
                            <a
                                href="{{ $tabHref }}"
                                class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors {{ $isActive ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700' }}"
                            >
                                {{ $label }}
                                <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full {{ $isActive ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500' }}">{{ $count }}</span>
                            </a>
                        @endforeach
                    </div>

                    @if ((int) $tabCounts['all'] === 0)
                        <div class="px-4 pb-6">
                            <x-empty-state
                                :title="__('No posts yet')"
                                :description="__('Create your first post to get started.')"
                            >
                                <x-slot name="icon">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </x-slot>
                                <x-button variant="primary" :href="url('/create')">{{ __('Create a post') }}</x-button>
                            </x-empty-state>
                        </div>
                    @else
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Caption</th>
                                <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Platform</th>
                                <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Author</th>
                                <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Status</th>
                                <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Scheduled</th>
                                <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Date</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($posts as $post)
                                <tr
                                    @class([
                                        'border-b border-gray-50 hover:bg-gray-50 transition-colors',
                                        'border-0' => $loop->last,
                                    ])
                                >
                                    <td class="px-4 py-3.5">
                                        <span class="text-sm font-medium text-gray-900 line-clamp-2">
                                            {{ Str::limit((string) ($post->content ?? $post->caption ?? ''), 120) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        @php
                                            $slugs = $postListing->platformSlugsForPost($post);
                                            $first = $slugs[0] ?? null;
                                        @endphp
                                        @if ($first)
                                            @php
                                                $pColors = [
                                                    'facebook' => 'bg-blue-50 text-blue-700',
                                                    'twitter' => 'bg-sky-50 text-sky-700',
                                                    'instagram' => 'bg-pink-50 text-pink-700',
                                                    'linkedin' => 'bg-indigo-50 text-indigo-700',
                                                ];
                                                $cls = $pColors[$first] ?? 'bg-gray-100 text-gray-700';
                                            @endphp
                                            <span class="inline-flex text-xs font-medium px-2 py-0.5 rounded-full {{ $cls }}">
                                                {{ ucfirst($first) }}@if (count($slugs) > 1) <span class="text-gray-500">+{{ count($slugs) - 1 }}</span>@endif
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-sm text-gray-600">{{ $post->author?->name ?? 'Unknown' }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <x-badge :variant="$statusBadgeVariant($post->status)">{{ $statusLabel($post->status) }}</x-badge>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-sm text-gray-600">
                                            {{ $post->scheduled_at?->format('Y-m-d H:i') ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="text-sm text-gray-500">{{ $post->created_at?->toDateString() ?? '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <form
                                            action="{{ route('posts.destroy', $post) }}"
                                            method="post"
                                            class="inline"
                                            onsubmit="return confirm(@json(__('Delete this post?')));"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="p-1.5 text-gray-400 hover:text-rose-500 hover:bg-rose-50 rounded-md transition-colors"
                                                title="{{ __('Delete') }}"
                                            >
                                                <svg class="w-[13px] h-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6M19 6v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if ($posts->isEmpty() && (int) $tabCounts['all'] > 0)
                        <div class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No posts found for this filter.') }}</div>
                    @endif

                    @if ($posts->hasPages())
                        <div class="px-4 py-3 border-t border-gray-100">
                            {{ $posts->onEachSide(1)->links() }}
                        </div>
                    @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
