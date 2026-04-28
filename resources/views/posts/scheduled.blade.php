@php
    use App\Models\Post;
    use App\Services\PostListingService;
    use Carbon\Carbon;
    use Illuminate\Support\Str;
@endphp
@inject('postListing', PostListingService::class)

@extends('layouts.app', [
    'title' => $title,
    'description' => $description,
])

@section('content')
@php
    $platformClasses = [
        'facebook' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'dot' => 'bg-blue-500'],
        'twitter' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'dot' => 'bg-sky-400'],
        'instagram' => ['bg' => 'bg-pink-50', 'text' => 'text-pink-700', 'dot' => 'bg-pink-500'],
        'linkedin' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'dot' => 'bg-indigo-600'],
    ];
    $formatScheduleLabel = static function (mixed $at): array {
        if (! $at instanceof Carbon) {
            return ['date' => '—', 'time' => ''];
        }
        $d = $at->copy()->startOfDay();
        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();
        if ($d->equalTo($today)) {
            $dateLabel = 'Today';
        } elseif ($d->equalTo($tomorrow)) {
            $dateLabel = 'Tomorrow';
        } else {
            $dateLabel = $at->format('M j');
        }

        return ['date' => $dateLabel, 'time' => $at->format('H:i')];
    };
    $imageMedia = static function (Post $post): ?string {
        foreach ($post->postMedia as $m) {
            if (in_array($m->type ?? '', ['image', 'gif'], true) && (string) ($m->url ?? '') !== '') {
                return (string) $m->url;
            }
        }
        if ((string) ($post->image_url ?? '') !== '') {
            return (string) $post->image_url;
        }

        return null;
    };
@endphp

    <div class="p-6 min-h-full">
        @if ($workspace === null)
            <x-empty-state
                :title="__('No workspace')"
                :description="__('Create an account and workspace to see scheduled posts.')"
            >
                <x-slot name="icon">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </x-slot>
            </x-empty-state>
        @elseif ($posts->isEmpty())
            <div class="bg-white border border-gray-200 rounded-xl min-h-[420px] flex items-center justify-center">
                <div class="text-center max-w-sm px-4">
                    <svg class="w-11 h-11 text-gray-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm text-gray-600">{{ __('No posts scheduled yet') }}</p>
                    <div class="mt-3">
                        <x-button variant="primary" :href="url('/create')">{{ __('Create your first post') }}</x-button>
                    </div>
                </div>
            </div>
        @else
            <div class="text-xs text-gray-500 mb-3">
                {{ __('Workspace:') }} <span class="font-medium text-gray-700">{{ $workspace->name }}</span>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left text-xs font-medium text-gray-500 px-5 py-3 uppercase tracking-wide w-1/3">Content Preview</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Platforms</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Scheduled for</th>
                            <th class="text-left text-xs font-medium text-gray-500 px-4 py-3 uppercase tracking-wide">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posts as $post)
                            @php
                                $at = $post->scheduled_at;
                                $lt = $formatScheduleLabel($at);
                                $img = $imageMedia($post);
                                $slugs = $postListing->platformSlugsForPost($post);
                            @endphp
                            <tr @class(['border-b border-gray-50 hover:bg-gray-50 transition-colors', 'border-0' => $loop->last])>
                                <td class="px-5 py-3.5">
                                    <div class="flex items-start gap-3">
                                        @if ($img)
                                            <img src="{{ $img }}" alt="" class="w-12 h-12 rounded object-cover shrink-0 border border-gray-200" />
                                        @endif
                                        <div class="min-w-0">
                                            <p class="text-sm text-gray-900 line-clamp-2">
                                                {{ Str::limit((string) ($post->content ?? $post->caption ?? ''), 160) }}
                                            </p>
                                            <p class="text-xs text-gray-400 mt-0.5 truncate">
                                                {{ $at?->format('Y-m-d H:i') ?? '—' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($slugs as $slug)
                                            @php
                                                $pc = $platformClasses[$slug] ?? ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'dot' => 'bg-gray-500'];
                                            @endphp
                                            <div class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full {{ $pc['bg'] }} {{ $pc['text'] }}">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $pc['dot'] }}"></span>
                                                {{ ucfirst($slug) }}
                                            </div>
                                        @empty
                                            <span class="text-sm text-gray-400">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-col">
                                        <p class="text-sm font-medium text-gray-900">{{ $lt['date'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $lt['time'] }}</p>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-50">
                                        <svg class="w-3 h-3 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 1.5" />
                                        </svg>
                                        <span class="text-amber-600">{{ __('Scheduled') }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex items-center justify-end gap-1">
                                        <form
                                            action="{{ route('posts.destroy', $post) }}"
                                            method="post"
                                            class="inline"
                                            onsubmit="return confirm(@json(__('Delete this scheduled post?')));"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="p-1.5 text-gray-400 hover:text-rose-500 hover:bg-rose-50 rounded-md transition-colors"
                                                title="{{ __('Delete') }}"
                                            >
                                                <svg class="w-[14px] h-[14px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M10 11v6M14 11v6M19 6v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6" stroke-linecap="round" stroke-linejoin="round" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($posts->hasPages())
                <div class="mt-4">
                    {{ $posts->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection
