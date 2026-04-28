@extends('layouts.app', [
    'title' => 'Activity',
    'description' => 'Recent activity in your workspace.',
])

@section('content')
    <x-page-header
        title="Activity"
        :description="__('A timeline of posts, connections, and other events for the current workspace.')"
    />

    <div
        class="rounded-xl border border-dashed border-gray-200 bg-gray-50/80 px-6 py-12 text-center"
        role="status"
    >
        <p class="text-sm font-medium text-gray-900">{{ __('No activity yet') }}</p>
        <p class="mt-1 text-sm text-gray-500 max-w-md mx-auto">
            {{ __('When you publish or connect accounts, events will show up here.') }}
        </p>
    </div>
@endsection
