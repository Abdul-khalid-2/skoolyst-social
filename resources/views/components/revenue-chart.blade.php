@props(['platformStats' => []])

@php
    $gradId = 'areaGrad' . str()->lower(str()->random(10));
    $platformStatsList = is_iterable($platformStats) ? collect($platformStats) : collect();
@endphp

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                if (window.__revenueChartDataRegistered) {
                    return;
                }
                window.__revenueChartDataRegistered = true;

                function formatValue(v, isRevenue) {
                    if (!isRevenue) {
                        return v.toLocaleString();
                    }
                    if (v >= 1000000) {
                        return '$' + (v / 1000000).toFixed(1) + 'M';
                    }
                    if (v >= 1000) {
                        return '$' + (v / 1000).toFixed(0) + 'K';
                    }
                    return '$' + v;
                }

                const chartData = {
                    week: [
                        { label: 'Mon', revenue: 4200, users: 320 },
                        { label: 'Tue', revenue: 5800, users: 410 },
                        { label: 'Wed', revenue: 4900, users: 370 },
                        { label: 'Thu', revenue: 7200, users: 530 },
                        { label: 'Fri', revenue: 6400, users: 480 },
                        { label: 'Sat', revenue: 3800, users: 290 },
                        { label: 'Sun', revenue: 4100, users: 310 },
                    ],
                    month: [
                        { label: 'Week 1', revenue: 28400, users: 2100 },
                        { label: 'Week 2', revenue: 34200, users: 2600 },
                        { label: 'Week 3', revenue: 31800, users: 2400 },
                        { label: 'Week 4', revenue: 42100, users: 3100 },
                    ],
                    year: [
                        { label: 'Jan', revenue: 82000, users: 6200 },
                        { label: 'Feb', revenue: 74000, users: 5600 },
                        { label: 'Mar', revenue: 91000, users: 7100 },
                        { label: 'Apr', revenue: 108000, users: 8400 },
                        { label: 'May', revenue: 97000, users: 7600 },
                        { label: 'Jun', revenue: 124000, users: 9800 },
                        { label: 'Jul', revenue: 118000, users: 9200 },
                        { label: 'Aug', revenue: 132000, users: 10400 },
                        { label: 'Sep', revenue: 121000, users: 9600 },
                        { label: 'Oct', revenue: 145000, users: 11200 },
                        { label: 'Nov', revenue: 138000, users: 10800 },
                        { label: 'Dec', revenue: 162000, users: 12600 },
                    ],
                };

                Alpine.data('revenueChart', (gradId) => ({
                    data: chartData,
                    range: 'month',
                    metric: 'revenue',
                    hoveredIndex: null,
                    gradId: gradId,
                    chartH: 200,
                    chartW: 100,
                    pad: 8,
                    formatValue,
                    get points() {
                        return this.data[this.range] ?? [];
                    },
                    get values() {
                        return this.points.map((p) => p[this.metric]);
                    },
                    get valueTotal() {
                        return this.values.reduce((a, b) => a + b, 0);
                    },
                    get max() {
                        return Math.max(...this.values);
                    },
                    get min() {
                        return Math.min(...this.values);
                    },
                    getY(v) {
                        if (this.max === this.min) {
                            return this.chartH / 2;
                        }
                        return this.chartH - this.pad - ((v - this.min) / (this.max - this.min)) * (this.chartH - 2 * this.pad);
                    },
                    get xs() {
                        const p = this.points;
                        const w = p.length;
                        if (w < 2) {
                            return [this.pad];
                        }
                        return p.map((_, i) => this.pad + (i / (w - 1)) * (this.chartW - 2 * this.pad));
                    },
                    get ys() {
                        return this.values.map((v) => this.getY(v));
                    },
                    get pathD() {
                        const xs = this.xs;
                        const ys = this.ys;
                        return xs
                            .map((x, i) => (i === 0 ? 'M' : 'L') + ' ' + x + ' ' + ys[i])
                            .join(' ');
                    },
                    get areaD() {
                        const xs = this.xs;
                        const p = this.pathD;
                        if (xs.length === 0) {
                            return '';
                        }
                        return p + ' L ' + xs[xs.length - 1] + ' ' + this.chartH + ' L ' + xs[0] + ' ' + this.chartH + ' Z';
                    },
                    gridLineSteps: [0, 0.25, 0.5, 0.75, 1],
                }));
            });
        </script>
    @endpush
@endonce

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
<div
    x-data="revenueChart('{{ $gradId }}')"
    class="bg-white rounded-xl border border-gray-200 p-5"
>
    <div class="flex items-start justify-between mb-4 flex-wrap gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-900">Performance Overview</h3>
            <p class="text-xs text-gray-500 mt-0.5">Track revenue and user growth</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="flex bg-gray-100 rounded-lg p-0.5">
                <button
                    type="button"
                    x-on:click="metric = 'revenue'"
                    :class="metric === 'revenue' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all"
                >
                    Revenue
                </button>
                <button
                    type="button"
                    x-on:click="metric = 'users'"
                    :class="metric === 'users' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all"
                >
                    Users
                </button>
            </div>
            <div class="flex bg-gray-100 rounded-lg p-0.5">
                <button
                    type="button"
                    x-on:click="range = 'week'"
                    :class="range === 'week' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all"
                >
                    Week
                </button>
                <button
                    type="button"
                    x-on:click="range = 'month'"
                    :class="range === 'month' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all"
                >
                    Month
                </button>
                <button
                    type="button"
                    x-on:click="range = 'year'"
                    :class="range === 'year' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700'"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all"
                >
                    Year
                </button>
            </div>
        </div>
    </div>

    <div class="flex items-baseline gap-2 mb-4">
        <span class="text-2xl font-bold text-gray-900" x-text="formatValue(valueTotal, metric === 'revenue')"></span>
        <span class="text-xs text-emerald-600 font-medium bg-emerald-50 px-2 py-0.5 rounded-full">+12.4% vs last period</span>
    </div>

    <div class="relative w-full" style="height: 220px">
        <svg
            :viewBox="'0 0 ' + chartW + ' ' + (chartH + 20)"
            preserveAspectRatio="none"
            class="w-full h-full"
            x-on:mouseleave="hoveredIndex = null"
        >
            <defs>
                <linearGradient :id="gradId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#2563eb" stop-opacity="0.12" />
                    <stop offset="100%" stop-color="#2563eb" stop-opacity="0" />
                </linearGradient>
            </defs>

            <g x-for="(t, i) in gridLineSteps" :key="'grid-'+i">
                <line
                    :x1="pad"
                    :y1="pad + t * (chartH - 2 * pad)"
                    :x2="chartW - pad"
                    :y2="pad + t * (chartH - 2 * pad)"
                    stroke="#f1f5f9"
                    stroke-width="0.5"
                />
            </g>

            <path :d="areaD" :fill="'url(#' + gradId + ')'" />
            <path
                :d="pathD"
                fill="none"
                stroke="#2563eb"
                stroke-width="1.2"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <g x-for="(x, i) in xs" :key="'pt-'+i">
                <rect
                    :x="i === 0 ? x : (xs[i - 1] + x) / 2"
                    y="0"
                    :width="i === 0 ? (xs[1] - xs[0]) / 2 : (i === xs.length - 1 ? (x - xs[i - 1]) / 2 : (xs[i + 1] - xs[i - 1]) / 2)"
                    :height="chartH"
                    fill="transparent"
                    x-on:mouseenter="hoveredIndex = i"
                />
                <g x-show="hoveredIndex === i">
                    <line
                        :x1="x"
                        :y1="pad"
                        :x2="x"
                        :y2="chartH"
                        stroke="#e2e8f0"
                        stroke-width="0.8"
                        stroke-dasharray="2,2"
                    />
                    <circle
                        :cx="x"
                        :cy="ys[i]"
                        r="1.8"
                        fill="white"
                        stroke="#2563eb"
                        stroke-width="1.2"
                    />
                </g>
            </g>
        </svg>

        <div
            x-show="hoveredIndex !== null"
            x-cloak
            class="absolute pointer-events-none bg-gray-900 text-white text-xs rounded-lg px-2.5 py-1.5 shadow-lg"
            :style="hoveredIndex !== null ? 'left: ' + (xs[hoveredIndex] / chartW * 100) + '%; top: ' + (ys[hoveredIndex] / (chartH + 20) * 100) + '%; transform: translate(-50%, -130%);' : ''"
        >
            <p class="font-semibold" x-text="formatValue(values[hoveredIndex], metric === 'revenue')"></p>
            <p class="text-gray-400" x-text="points[hoveredIndex] ? points[hoveredIndex].label : ''"></p>
        </div>
    </div>

    <div class="flex justify-between mt-1 px-1">
        <span
            x-for="(p, i) in points"
            :key="'lbl-'+i"
            class="text-xs transition-colors"
            :class="hoveredIndex === i ? 'text-blue-600 font-medium' : 'text-gray-400'"
            x-text="p.label"
        ></span>
    </div>
</div>

@if ($platformStatsList->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Platform Performance</h3>
        <div class="space-y-4">
            @foreach ($platformStatsList as $row)
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-700">{{ $row['name'] ?? '—' }}</span>
                        <span class="text-gray-600">{{ (int) ($row['count'] ?? 0) }} posts</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full {{ $row['barClass'] ?? 'bg-gray-400' }}"
                            style="width: {{ (float) ($row['widthPercent'] ?? 0) }}%;"
                        ></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
</div>
