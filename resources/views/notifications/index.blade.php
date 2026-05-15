@extends('layouts.app', [
    'title'       => $title,
    'description' => $description,
])

@section('content')
<div class="p-6 space-y-4 min-h-full">

    @if ($workspace === null)
        <x-empty-state
            title="{{ __('No workspace') }}"
            description="{{ __('Create a workspace to see notifications.') }}"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </x-slot>
        </x-empty-state>

    @elseif ($notifications->isEmpty())
        <div class="bg-white border border-gray-200 rounded-xl min-h-[360px] flex items-center justify-center">
            <div class="text-center max-w-sm px-4">
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
                <p class="text-sm text-gray-600">{{ __("You're all caught up — no notifications.") }}</p>
            </div>
        </div>

    @else
        <div class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-100 overflow-hidden">
            @foreach ($notifications as $n)
                @php
                    $colorMap = [
                        'success' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'ring' => 'ring-emerald-200'],
                        'error'   => ['bg' => 'bg-rose-50',    'text' => 'text-rose-600',    'ring' => 'ring-rose-200'],
                        'warning' => ['bg' => 'bg-amber-50',   'text' => 'text-amber-600',   'ring' => 'ring-amber-200'],
                        'info'    => ['bg' => 'bg-blue-50',    'text' => 'text-blue-600',    'ring' => 'ring-blue-200'],
                    ];
                    $c = $colorMap[$n['type']] ?? $colorMap['info'];
                @endphp
                <div class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition-colors">

                    {{-- Icon bubble --}}
                    <div class="shrink-0 mt-0.5 w-9 h-9 rounded-full {{ $c['bg'] }} ring-1 {{ $c['ring'] }} flex items-center justify-center">
                        @if ($n['icon'] === 'check-circle')
                            <svg class="w-4 h-4 {{ $c['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @elseif ($n['icon'] === 'x-circle')
                            <svg class="w-4 h-4 {{ $c['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @elseif ($n['icon'] === 'clock')
                            <svg class="w-4 h-4 {{ $c['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        @elseif ($n['icon'] === 'calendar')
                            <svg class="w-4 h-4 {{ $c['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        @else {{-- key --}}
                            <svg class="w-4 h-4 {{ $c['text'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                        @endif
                    </div>

                    {{-- Text --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">{{ $n['title'] }}</p>
                        @if ($n['body'])
                            <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ $n['body'] }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-1">
                            {{ \Carbon\Carbon::parse($n['time'])->diffForHumans() }}
                        </p>
                    </div>

                    {{-- Action --}}
                    @if ($n['action_url'])
                        <a
                            href="{{ $n['action_url'] }}"
                            class="shrink-0 text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline whitespace-nowrap mt-0.5"
                        >
                            {{ $n['action_label'] }}
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="text-xs text-gray-400 text-center">
            {{ __('Showing :count notification:s', [
                'count' => $notifications->count(),
                's'     => $notifications->count() === 1 ? '' : 's',
            ]) }}
        </p>
    @endif

</div>
@endsection
