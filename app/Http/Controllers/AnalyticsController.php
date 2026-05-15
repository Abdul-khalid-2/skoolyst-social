<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialPlatform;
use App\Services\PostListingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function __construct(private readonly PostListingService $postListing) {}

    public function index(Request $request): View
    {
        $workspace = $this->postListing->resolveWorkspaceForUser($request->user());

        if (! $workspace) {
            return view('analytics.index', [
                'title'          => 'Analytics',
                'description'    => 'Performance and engagement metrics.',
                'noWorkspace'    => true,
                'kpis'           => [],
                'platformPosts'  => collect(),
                'engagementData' => [],
                'dayLabels'      => [],
                'topPost'        => null,
                'heatmapRows'    => [],
            ]);
        }

        $wid = (int) $workspace->id;

        // ── KPIs ──────────────────────────────────────────────────────────────
        $totalPublished = Post::where('workspace_id', $wid)->where('status', 'published')->count();
        $thisMonth      = Post::where('workspace_id', $wid)
            ->where('status', 'published')
            ->whereMonth('published_at', now()->month)
            ->count();
        $lastMonth      = Post::where('workspace_id', $wid)
            ->where('status', 'published')
            ->whereMonth('published_at', now()->subMonth()->month)
            ->count();

        $kpis = [
            [
                'label' => 'Posts Published',
                'value' => $totalPublished,
                'delta' => '+'.$thisMonth.' this month',
                'up'    => true,
            ],
            [
                'label' => 'Scheduled',
                'value' => Post::where('workspace_id', $wid)->where('status', 'scheduled')->count(),
                'delta' => '',
                'up'    => true,
            ],
            [
                'label' => 'Published (30 days)',
                'value' => Post::where('workspace_id', $wid)
                    ->where('status', 'published')
                    ->where('published_at', '>=', now()->subDays(30))
                    ->count(),
                'delta' => $lastMonth > 0
                    ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1).'%'
                    : 'N/A',
                'up'    => $thisMonth >= $lastMonth,
            ],
            [
                'label' => 'Failed Targets',
                'value' => PostTarget::whereHas('post', fn ($q) => $q->where('workspace_id', $wid))
                    ->where('status', 'failed')
                    ->count(),
                'delta' => '',
                'up'    => false,
            ],
        ];

        // ── Posts by Platform (last 30 days) ──────────────────────────────────
        $platforms = SocialPlatform::where('is_active', true)->get();
        $cutoff    = now()->subDays(30);

        $colorMap = [
            'facebook'  => 'bg-blue-500',
            'instagram' => 'bg-pink-500',
            'linkedin'  => 'bg-indigo-500',
            'twitter'   => 'bg-gray-700',
        ];

        $platformPosts = $platforms->map(function ($platform) use ($wid, $cutoff, $colorMap) {
            $count = PostTarget::where('social_platform_id', $platform->id)
                ->where('status', 'published')
                ->where('published_at', '>=', $cutoff)
                ->whereHas('post', fn ($q) => $q->where('workspace_id', $wid))
                ->count();

            return (object) [
                'name'  => $platform->name,
                'slug'  => $platform->slug,
                'posts' => $count,
                'color' => $colorMap[$platform->slug] ?? 'bg-gray-400',
            ];
        })->filter(fn ($p) => $p->posts > 0)->sortByDesc('posts')->values();

        // ── Engagement Over Time (published posts per day, last 7 days) ───────
        $dayLabels      = [];
        $engagementData = [];
        for ($i = 6; $i >= 0; $i--) {
            $day              = Carbon::today()->subDays($i);
            $dayLabels[]      = $day->format('D');
            $engagementData[] = Post::where('workspace_id', $wid)
                ->where('status', 'published')
                ->whereDate('published_at', $day)
                ->count();
        }

        // ── Top Performing Post (most recent published) ────────────────────────
        $topPost = Post::where('workspace_id', $wid)
            ->where('status', 'published')
            ->with(['postMedia', 'postTargets.socialPlatform'])
            ->latest('published_at')
            ->first();

        // ── Best Time to Post heatmap ──────────────────────────────────────────
        $slots = ['Morning' => [6, 11], 'Afternoon' => [12, 17], 'Evening' => [18, 23]];
        $days  = ['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4];

        $heatmapRows = collect($slots)->map(function ($hours, $label) use ($wid, $days) {
            $values = collect($days)->map(function ($dow) use ($wid, $hours) {
                $count = Post::where('workspace_id', $wid)
                    ->where('status', 'published')
                    ->whereRaw('DAYOFWEEK(published_at) = ?', [$dow + 1])
                    ->whereRaw('HOUR(published_at) BETWEEN ? AND ?', $hours)
                    ->count();

                return $count > 2 ? 2 : ($count > 0 ? 1 : 0);
            })->values()->all();

            return (object) ['label' => $label, 'values' => $values];
        })->values()->all();

        return view('analytics.index', [
            'title'          => 'Analytics',
            'description'    => 'Performance and engagement metrics.',
            'noWorkspace'    => false,
            'kpis'           => $kpis,
            'platformPosts'  => $platformPosts,
            'engagementData' => $engagementData,
            'dayLabels'      => $dayLabels,
            'topPost'        => $topPost,
            'heatmapRows'    => $heatmapRows,
        ]);
    }
}
