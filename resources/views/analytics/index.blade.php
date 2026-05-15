@php use Illuminate\Support\Str; @endphp

@extends('layouts.app', [
    'title'       => $title ?? 'Analytics',
    'description' => $description ?? 'Performance and engagement metrics.',
    'subtitle'    => null,
])

@php
    $engagementData = $engagementData ?? [];
    $dayLabels      = $dayLabels ?? [];
    $min    = count($engagementData) ? min($engagementData) : 0;
    $max    = count($engagementData) ? max($engagementData) : 1;
    $xStart = 30; $xEnd = 400;
    $xStep  = count($engagementData) > 1 ? ($xEnd - $xStart) / (count($engagementData) - 1) : 0;
    $points = [];
    foreach ($engagementData as $i => $value) {
        $x = $xStart + $i * $xStep;
        $normalized = $max > $min ? ($value - $min) / ($max - $min) : 0.5;
        $y = 100 - $normalized * 90 + 10;
        $points[] = (object) ['x' => $x, 'y' => $y, 'value' => $value, 'day' => $dayLabels[$i] ?? ''];
    }
    $polyline    = collect($points)->map(fn ($p) => $p->x.','.$p->y)->join(' ');
    $guideValues = collect([0.25, 0.5, 0.75])->map(fn ($f) => (object) [
        'y'     => 10 + 90 * $f,
        'value' => (int) round($max - ($max - $min) * $f),
    ]);
    $heatColor = function (int $level): string {
        return match ($level) { 2 => 'bg-blue-500', 1 => 'bg-blue-200', default => 'bg-gray-100' };
    };
    $maxPlatformPosts = max(1, collect($platformPosts ?? [])->max('posts') ?? 1);
@endphp

@section('content')
    <div class="p-6 space-y-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($kpis as $kpi)
                <x-card
                    class="!shadow-sm hover:shadow-md transition-shadow duration-200"
                    padding="!p-4"
                >
                    <p class="text-xs text-gray-500 uppercase tracking-wide">{{ $kpi['label'] }}</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ $kpi['value'] }}</p>
                    <p class="text-xs font-semibold mt-1 {{ $kpi['up'] ? 'text-emerald-600' : 'text-red-500' }}">{{ $kpi['delta'] }}</p>
                </x-card>
            @endforeach
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="xl:col-span-2 space-y-5">
                <x-card class="!shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold text-gray-900">Posts by Platform</h3>
                        <p class="text-xs text-gray-500">Last 30 days</p>
                    </div>
                    <div class="space-y-3">
                        @foreach ($platformPosts as $item)
                            <div class="flex items-center gap-3">
                                <span class="w-20 text-xs text-gray-700 font-medium shrink-0">{{ $item->name }}</span>
                                <div class="flex-1 h-3 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-700 {{ $item->color }}"
                                        style="width: {{ (int) ($item->posts / $maxPlatformPosts * 100) }}%"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-600 font-semibold w-8 text-right shrink-0">{{ $item->posts }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-card>

                <x-card class="!shadow-sm hover:shadow-md transition-shadow duration-200">
                    <div class="mb-4">
                        <h3 class="text-sm font-semibold text-gray-900">Engagement Over Time</h3>
                        <p class="text-xs text-gray-500">Last 7 days</p>
                    </div>
                    <svg viewBox="0 0 420 130" width="100%" height="130" aria-label="{{ __('Engagement over time') }}">
                        @foreach ($guideValues as $guide)
                            <g>
                                <line x1="30" y1="{{ $guide->y }}" x2="400" y2="{{ $guide->y }}" stroke="#e5e7eb" stroke-dasharray="4 4" />
                                <text x="5" y="{{ $guide->y + 3 }}" font-size="10" fill="#9ca3af">
                                    {{ $guide->value }}
                                </text>
                            </g>
                        @endforeach

                        <polyline
                            points="{{ $polyline }}"
                            stroke="#3b82f6"
                            stroke-width="2.5"
                            fill="none"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        @foreach ($points as $point)
                            <circle
                                cx="{{ $point->x }}"
                                cy="{{ $point->y }}"
                                r="4"
                                fill="#3b82f6"
                                stroke="white"
                                stroke-width="2"
                            />
                        @endforeach

                        @foreach ($points as $point)
                            <text x="{{ $point->x }}" y="120" text-anchor="middle" font-size="10" fill="#9ca3af">
                                {{ $point->day }}
                            </text>
                        @endforeach
                    </svg>
                </x-card>
            </div>

            <div class="xl:col-span-1 space-y-5">
                <x-card class="!shadow-sm hover:shadow-md transition-shadow duration-200">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Top Performing Post</h3>

                    @if ($topPost)
                        <div class="flex gap-3">
                            @php $img = $topPost->postMedia->first()?->url; @endphp
                            @if ($img)
                                <img src="{{ $img }}" alt="" class="w-16 h-16 rounded-lg object-cover shrink-0 border border-gray-100" />
                            @else
                                <div class="w-16 h-16 bg-gray-100 rounded-lg shrink-0" role="img" aria-hidden="true"></div>
                            @endif
                            <p class="text-xs text-gray-700 line-clamp-3">{{ Str::limit($topPost->caption ?? '', 140) }}</p>
                        </div>
                        @foreach ($topPost->postTargets as $t)
                            <span class="inline-flex mt-3 bg-blue-100 text-blue-700 rounded-full px-2.5 py-1 text-xs">
                                {{ ucfirst($t->socialPlatform?->slug ?? '') }}
                            </span>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-400">No published posts yet.</p>
                    @endif

                    <div class="flex gap-3 mt-3 pt-3 border-t border-gray-100">
                        <div class="flex items-center gap-1">
                            <svg class="w-4 h-4 text-red-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                            </svg>
                            <span class="text-xs text-gray-600">4,820</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <svg class="w-4 h-4 text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z" />
                            </svg>
                            <span class="text-xs text-gray-600">312</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <svg class="w-4 h-4 text-gray-400" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="18" cy="5" r="3" />
                                <circle cx="6" cy="12" r="3" />
                                <circle cx="18" cy="19" r="3" />
                                <line x1="8.59" x2="15.42" y1="13.51" y2="17.49" />
                                <line x1="15.41" x2="8.59" y1="6.51" y2="10.49" />
                            </svg>
                            <span class="text-xs text-gray-600">891</span>
                        </div>
                    </div>
                </x-card>

                <x-card class="!shadow-sm hover:shadow-md transition-shadow duration-200">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">Best Time to Post</h3>
                    <p class="text-xs text-gray-500 mb-3">Based on your audience engagement</p>

                    <div class="grid grid-cols-5 gap-2 items-center">
                        <div></div>
                        @foreach (['Mon', 'Tue', 'Wed', 'Thu'] as $day)
                            <p class="text-xs text-gray-500 text-center">{{ $day }}</p>
                        @endforeach

                        @foreach ($heatmapRows as $row)
                            <div class="contents">
                                <p class="text-xs text-gray-500">{{ $row->label }}</p>
                                @foreach ($row->values as $index => $level)
                                    <div class="h-8 w-full rounded-md {{ $heatColor($level) }}"></div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>

                    <div class="flex gap-3 mt-3 items-center">
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-gray-100" aria-hidden="true"></span>
                            <span class="text-xs text-gray-500">Low</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-blue-200" aria-hidden="true"></span>
                            <span class="text-xs text-gray-500">Medium</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-3 h-3 rounded-sm bg-blue-500" aria-hidden="true"></span>
                            <span class="text-xs text-gray-500">High</span>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </div>
@endsection
