@extends('layouts.app', [
    'title' => 'Activity',
    'description' => 'Recent activity in your workspace.',
])

@section('content')
    <x-page-header
        title="Activity"
        :description="__('A timeline of posts, connections, and other events for the current workspace.')"
    />

    @if($activities->isEmpty())
        <div
            class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-6 py-12 text-center"
            role="status"
        >
            <p class="text-sm font-medium text-gray-900">{{ __('No activity yet') }}</p>
            <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">
                {{ __('When you publish or connect accounts, events will show up here.') }}
            </p>
        </div>
    @else
        <div class="flow-root mt-8">
            <ul role="list" class="-mb-8">
                @foreach($activities as $activity)
                    <li>
                        <div class="relative pb-8">
                            @if(!$loop->last)
                                <span class="absolute left-5 top-5 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            @endif
                            <div class="relative flex items-start space-x-4">
                                <div class="relative">
                                    @if($activity->status === 'published')
                                        <span class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 6L9 17l-5-5" />
                                            </svg>
                                        </span>
                                    @elseif($activity->status === 'failed')
                                        <span class="h-10 w-10 rounded-full bg-red-100 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 6L6 18M6 6l12 12" />
                                            </svg>
                                        </span>
                                    @elseif($activity->status === 'partial')
                                        <span class="h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-yellow-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </span>
                                    @elseif($activity->status === 'scheduled')
                                        <span class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <circle cx="12" cy="12" r="10" />
                                                <path d="M12 6v6l4 2" />
                                            </svg>
                                        </span>
                                    @elseif($activity->status === 'publishing')
                                        <span class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center ring-8 ring-white">
                                            <svg class="h-5 w-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M12 5v14M5 12h14" />
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1 py-1.5 flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4">
                                    <div class="flex-1">
                                        <div class="text-sm text-gray-500">
                                            Created a post 
                                            @if($activity->status === 'published')
                                                <span class="font-medium text-green-600">successfully</span>
                                                to {{ $activity->postTargets->count() }} account(s).
                                            @elseif($activity->status === 'failed')
                                                <span class="font-medium text-red-600">but it failed</span>
                                                to publish to {{ $activity->postTargets->count() }} account(s).
                                            @elseif($activity->status === 'partial')
                                                <span class="font-medium text-yellow-600">partially</span>
                                                to {{ $activity->postTargets->count() }} account(s).
                                            @elseif($activity->status === 'scheduled')
                                                <span class="font-medium text-blue-600">and scheduled it</span>
                                                for {{ $activity->postTargets->count() }} account(s).
                                            @elseif($activity->status === 'publishing')
                                                <span class="font-medium text-indigo-600">which is currently publishing</span>
                                                to {{ $activity->postTargets->count() }} account(s).
                                            @else
                                                <span class="font-medium text-gray-900">{{ $activity->status }}</span>
                                                to {{ $activity->postTargets->count() }} account(s).
                                            @endif
                                        </div>
                                        <div class="mt-2 text-sm text-gray-700 bg-gray-50 rounded-lg p-4 border border-gray-100 shadow-sm">
                                            <p class="line-clamp-3 whitespace-pre-wrap">{{ $activity->caption }}</p>
                                        </div>
                                    </div>
                                    <div class="whitespace-nowrap text-sm text-gray-400 sm:text-right">
                                        <time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->diffForHumans() }}</time>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
