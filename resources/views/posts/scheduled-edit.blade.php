@php
    $platformClasses = [
        'facebook'  => ['bg' => 'bg-blue-50',   'text' => 'text-blue-700',   'dot' => 'bg-blue-500'],
        'twitter'   => ['bg' => 'bg-sky-50',    'text' => 'text-sky-700',    'dot' => 'bg-sky-400'],
        'instagram' => ['bg' => 'bg-pink-50',   'text' => 'text-pink-700',   'dot' => 'bg-pink-500'],
        'linkedin'  => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'dot' => 'bg-indigo-600'],
    ];

    $slugs = $post->postTargets
        ->map(fn ($t) => $t->socialPlatform?->slug)
        ->filter()
        ->unique()
        ->values();
@endphp

@extends('layouts.app', [
    'title'       => $title,
    'description' => $description,
])

@section('content')
    <div class="p-6 min-h-full">

        @if (session('success'))
            <x-alert variant="success" class="mb-5">{{ session('success') }}</x-alert>
        @endif

        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">

                {{-- Card header --}}
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('Edit Scheduled Post') }}</h2>

                        @if ($slugs->isNotEmpty())
                            <div class="flex flex-wrap gap-1">
                                @foreach ($slugs as $slug)
                                    @php
                                        $pc = $platformClasses[$slug] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-500'];
                                    @endphp
                                    <div class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full {{ $pc['bg'] }} {{ $pc['text'] }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $pc['dot'] }}"></span>
                                        {{ ucfirst($slug) }}
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Form --}}
                <form
                    action="{{ route('posts.scheduled.update', $post) }}"
                    method="post"
                    class="px-6 py-5 space-y-5"
                >
                    @csrf
                    @method('POST')

                    <x-textarea
                        label="{{ __('Caption') }}"
                        name="caption"
                        :rows="6"
                    >{{ old('caption', $post->caption) }}</x-textarea>

                    <div>
                        <label for="scheduled_at" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ __('Scheduled At') }}
                        </label>
                        <input
                            type="datetime-local"
                            id="scheduled_at"
                            name="scheduled_at"
                            value="{{ old('scheduled_at', $post->scheduled_at?->format('Y-m-d\TH:i')) }}"
                            x-data
                            x-init="$el.min = new Date(new Date() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16)"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                        />
                        <x-form-error name="scheduled_at" />
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-1">
                        <x-button variant="secondary" :href="route('posts.scheduled')">
                            {{ __('Cancel') }}
                        </x-button>
                        <x-button variant="primary" type="submit">
                            {{ __('Save Changes') }}
                        </x-button>
                    </div>
                </form>

            </div>
        </div>
    </div>
@endsection
