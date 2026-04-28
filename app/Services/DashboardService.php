<?php

namespace App\Services;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardService
{
    /**
     * @return array{
     *     totalPosts: int,
     *     scheduledPosts: int,
     *     postedToday: int,
     *     connectedAccounts: int,
     *     recentActivity: Collection<int, array{
     *         id: int,
     *         snippet: string,
     *         platform: string,
     *         badge: string,
     *         time_iso: string|null,
     *         time_human: string
     *     }>,
     *     platformStats: Collection<int, array{
     *         name: string,
     *         count: int,
     *         barClass: string,
     *         widthPercent: float
     *     }>
     * }
     */
    public function getDashboardData(User $user): array
    {
        $workspaceId = $this->resolveWorkspaceId($user);
        if ($workspaceId === null) {
            return [
                'totalPosts' => 0,
                'scheduledPosts' => 0,
                'postedToday' => 0,
                'connectedAccounts' => 0,
                'recentActivity' => collect(),
                'platformStats' => collect(),
            ];
        }

        $postBase = fn (): Builder => Post::query()
            ->select(['id'])
            ->where('workspace_id', $workspaceId);

        $totalPosts = $postBase()->count();

        $scheduledPosts = $postBase()
            ->where('status', 'scheduled')
            ->count();

        $postedToday = $postBase()
            ->where('status', 'published')
            ->whereDate('published_at', Carbon::today())
            ->count();

        $connectedAccounts = SocialAccount::query()
            ->select(['id'])
            ->where('workspace_id', $workspaceId)
            ->where('is_connected', true)
            ->count();

        $recentActivity = $this->formatRecentActivity(
            Post::query()
                ->select(['id', 'content', 'caption', 'platforms', 'created_at'])
                ->where('workspace_id', $workspaceId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
        );

        $platformStats = $this->buildPlatformStats($workspaceId);

        return [
            'totalPosts' => $totalPosts,
            'scheduledPosts' => $scheduledPosts,
            'postedToday' => $postedToday,
            'connectedAccounts' => $connectedAccounts,
            'recentActivity' => $recentActivity,
            'platformStats' => $platformStats,
        ];
    }

    public function resolveWorkspaceId(User $user): ?int
    {
        $fromSession = session('current_workspace_id');
        if (is_numeric($fromSession)) {
            $id = (int) $fromSession;
            if ($id > 0 && $user->workspaces()->where('workspaces.id', $id)->exists()) {
                return $id;
            }
        }

        $w = $user->workspaces()
            ->select('workspaces.id')
            ->orderBy('workspaces.id')
            ->first();

        if ($w !== null) {
            return (int) $w->id;
        }

        $owned = $user->ownedWorkspaces()
            ->select('id')
            ->orderBy('id')
            ->first();

        return $owned?->id;
    }

    private function formatRecentActivity(Collection $posts): Collection
    {
        return $posts->map(function (Post $post): array {
            $raw = $post->content ?? $post->caption ?? '';
            $snippet = Str::limit(trim($raw), 80);

            $platforms = is_array($post->platforms) ? $post->platforms : [];
            if ($platforms === []) {
                $label = 'Unspecified';
                $badge = '—';
            } else {
                $label = $this->platformLabel((string) $platforms[0]);
                $badge = $this->platformBadge($label);
            }

            return [
                'id' => (int) $post->id,
                'snippet' => $snippet !== '' ? $snippet : '—',
                'platform' => $label,
                'badge' => $badge,
                'time_iso' => $post->created_at?->toIso8601String(),
                'time_human' => $post->created_at?->diffForHumans() ?? '',
            ];
        });
    }

    private function buildPlatformStats(int $workspaceId): Collection
    {
        $rows = DB::table('post_targets')
            ->join('posts', 'post_targets.post_id', '=', 'posts.id')
            ->where('posts.workspace_id', $workspaceId)
            ->whereNull('posts.deleted_at')
            ->select('post_targets.social_platform_id', DB::raw('count(distinct post_targets.post_id) as post_count'))
            ->groupBy('post_targets.social_platform_id')
            ->orderByDesc('post_count')
            ->get();

        if ($rows->isEmpty()) {
            return $this->platformStatsFromPostJson($workspaceId);
        }

        $ids = $rows->pluck('social_platform_id')->unique()->filter()->values()->all();
        $nameById = SocialPlatform::query()
            ->select(['id', 'name', 'slug', 'color'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $max = max(1, (int) $rows->max('post_count'));

        return $rows->map(function ($row) use ($nameById, $max): array {
            $id = (int) $row->social_platform_id;
            $p = $nameById->get($id);
            $name = $p?->name ?? 'Platform #'.$id;
            $barClass = $this->defaultBarClassForSlug((string) ($p?->slug ?? 'gray'));

            return [
                'name' => $name,
                'count' => (int) $row->post_count,
                'barClass' => $barClass,
                'widthPercent' => round(((int) $row->post_count / $max) * 100, 1),
            ];
        })->values();
    }

    /**
     * Fallback when there are no post_targets rows: aggregate "platforms" JSON on posts.
     */
    private function platformStatsFromPostJson(int $workspaceId): Collection
    {
        $rows = Post::query()
            ->select(['id', 'platforms'])
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('platforms')
            ->get();

        $slugCounts = [];
        foreach ($rows as $post) {
            $platforms = $post->platforms;
            if (! is_array($platforms)) {
                continue;
            }
            foreach ($platforms as $slug) {
                $s = (string) $slug;
                if ($s === '') {
                    continue;
                }
                $slugCounts[$s] = ($slugCounts[$s] ?? 0) + 1;
            }
        }

        if ($slugCounts === []) {
            return collect();
        }

        arsort($slugCounts);
        $max = max(1, max($slugCounts));

        $slugs = array_keys($slugCounts);
        $nameBySlug = SocialPlatform::query()
            ->select(['id', 'name', 'slug', 'color'])
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        $out = [];
        foreach ($slugCounts as $slug => $count) {
            $p = $nameBySlug->get($slug);
            $name = $p?->name ?? $this->platformLabel($slug);
            $barClass = $this->defaultBarClassForSlug($p?->slug ?? $slug);

            $out[] = [
                'name' => $name,
                'count' => (int) $count,
                'barClass' => $barClass,
                'widthPercent' => round((($count / $max) * 100), 1),
            ];
        }

        return collect($out);
    }

    private function platformLabel(string $slug): string
    {
        $slug = Str::lower($slug);

        return match ($slug) {
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'twitter', 'x' => 'X',
            'linkedin' => 'LinkedIn',
            'draft' => 'Draft',
            default => Str::ucfirst($slug),
        };
    }

    private function platformBadge(string $label): string
    {
        if ($label === 'Unspecified') {
            return '—';
        }

        $map = [
            'Facebook' => 'FB',
            'Instagram' => 'IG',
            'LinkedIn' => 'IN',
            'X' => 'X',
            'Draft' => '—',
        ];

        return $map[$label] ?? Str::of($label)->substr(0, 2)->upper()->toString();
    }

    private function defaultBarClassForSlug(string $slug): string
    {
        $slug = Str::lower($slug);

        return match ($slug) {
            'facebook' => 'bg-blue-500',
            'instagram' => 'bg-pink-500',
            'twitter', 'x' => 'bg-gray-800',
            'linkedin' => 'bg-indigo-500',
            default => 'bg-gray-400',
        };
    }
}
