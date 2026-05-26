<?php

namespace App\Services;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostListingService
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    public function resolveWorkspaceForUser(User $user): ?Workspace
    {
        $id = $this->dashboardService->resolveWorkspaceId($user);
        if ($id === null) {
            return null;
        }

        return Workspace::query()->find($id);
    }

    /**
     * @return array{posts: LengthAwarePaginator, tabCounts: array<string, int>, workspace: ?Workspace, filter: string, platformFilter: string, platformCounts: array<string, int>, connectedPlatforms: Collection, showPlatformBar: bool}
     */
    public function paginateForIndex(User $user, ?string $statusFilter, int $perPage = 20): array
    {
        $workspace = $this->resolveWorkspaceForUser($user);
        if ($workspace === null) {
            return [
                'posts' => $this->emptyPaginator($perPage),
                'tabCounts' => $this->emptyTabCounts(),
                'workspace' => null,
                'filter' => 'all',
                'platformFilter' => 'all',
                'platformCounts' => ['all' => 0],
                'connectedPlatforms' => collect(),
                'showPlatformBar' => false,
            ];
        }

        $connectedPlatforms = $this->connectedPlatformsForWorkspace((int) $workspace->id);
        $statusFilter = $this->normalizeStatusFilter($statusFilter);
        $platformFilter = $this->normalizePlatformFilter((string) request()->query('platform'));

        $tabCounts = $this->tabCountsForWorkspace((int) $workspace->id, $platformFilter);
        $platformCounts = $this->platformCountsForWorkspace((int) $workspace->id, $statusFilter);

        $q = $this->basePostQuery($workspace->id);
        if ($statusFilter !== null) {
            $q->where('status', $statusFilter);
        }
        if ($platformFilter !== null) {
            $this->applyPlatformFilter($q, $platformFilter);
        }

        $posts = $q->latest()->paginate($perPage)->withQueryString();

        return [
            'posts' => $posts,
            'tabCounts' => $tabCounts,
            'workspace' => $workspace,
            'filter' => $statusFilter === null ? 'all' : $statusFilter,
            'platformFilter' => $platformFilter === null ? 'all' : $platformFilter,
            'platformCounts' => $platformCounts,
            'connectedPlatforms' => $connectedPlatforms,
            'showPlatformBar' => $connectedPlatforms->count() > 1,
        ];
    }

    /**
     * @return array{posts: LengthAwarePaginator, workspace: ?Workspace}
     */
    public function paginateScheduled(User $user, int $perPage = 20): array
    {
        $workspace = $this->resolveWorkspaceForUser($user);
        if ($workspace === null) {
            return [
                'posts' => $this->emptyPaginator($perPage),
                'workspace' => null,
            ];
        }

        $posts = $this->basePostQuery($workspace->id)
            ->where('status', 'scheduled')
            ->orderBy('scheduled_at', 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return [
            'posts' => $posts,
            'workspace' => $workspace,
        ];
    }

    public function deleteForUser(User $user, Post $post): void
    {
        Gate::forUser($user)->authorize('delete', $post);
        $post->delete();
    }

    /**
     * @return array<int, string>
     */
    public function platformSlugsForPost(Post $post): array
    {
        $fromJson = is_array($post->platforms) ? $post->platforms : [];
        if ($fromJson !== []) {
            return array_values(array_filter(array_map(fn ($value): string => $this->normalizePlatformSlug((string) $value), $fromJson), fn (string $s): bool => $s !== ''));
        }

        return $post->postTargets
            ->filter(fn ($target) => $target->socialAccount?->is_active ?? false)
            ->map(fn ($t) => $this->normalizePlatformSlug((string) ($t->socialPlatform?->slug ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Builder<Post>
     */
    private function basePostQuery(int $workspaceId): Builder
    {
        return Post::query()
            ->where('workspace_id', $workspaceId)
            ->with(['author', 'postMedia', 'postTargets.socialPlatform', 'postTargets.socialAccount']);
    }

    private function emptyPaginator(int $perPage): LengthAwarePaginator
    {
        return new ConcretePaginator(
            new Collection,
            0,
            $perPage,
            1,
            [
                'path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * @return array<string, int>
     */
    private function emptyTabCounts(): array
    {
        return [
            'all' => 0,
            'draft' => 0,
            'scheduled' => 0,
            'publishing' => 0,
            'published' => 0,
            'failed' => 0,
        ];
    }

    /**
     * @param  int  $workspaceId
     * @return Collection<int, SocialPlatform>
     */
    private function connectedPlatformsForWorkspace(int $workspaceId): Collection
    {
        return SocialPlatform::query()
            ->where('is_active', true)
            ->whereHas('socialAccounts', fn ($q) => $q->where('workspace_id', $workspaceId)->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    private function normalizeStatusFilter(?string $statusFilter): ?string
    {
        if ($statusFilter === null || $statusFilter === '' || $statusFilter === 'all') {
            return null;
        }

        return $statusFilter;
    }

    private function normalizePlatformFilter(?string $platformFilter): ?string
    {
        if ($platformFilter === null || trim($platformFilter) === '' || strtolower($platformFilter) === 'all') {
            return null;
        }

        $slug = strtolower(trim($platformFilter));
        if (SocialPlatform::query()->where('slug', $slug)->exists()) {
            return $slug;
        }

        return null;
    }

    private function applyPlatformFilter(Builder $query, string $platformFilter): Builder
    {
        return $query->where(function (Builder $query) use ($platformFilter) {
            $query->whereHas('postTargets', function (Builder $query) use ($platformFilter) {
                $query->whereHas('socialPlatform', fn (Builder $query) => $query->where('slug', $platformFilter))
                    ->whereHas('socialAccount', fn (Builder $query) => $query->where('is_active', true));
            })
            ->orWhereJsonContains('platforms', $platformFilter)
            ->orWhereRaw('LOWER(platforms) LIKE ?', ['%'.strtolower($platformFilter).'%']);
        });
    }

    private function platformCountsForWorkspace(int $workspaceId, ?string $statusFilter): array
    {
        $query = $this->basePostQuery($workspaceId)
            ->select(['id', 'platforms']);

        if ($statusFilter !== null) {
            $query->where('status', $statusFilter);
        }

        $posts = $query->get();
        $counts = ['all' => $posts->count()];

        foreach ($posts as $post) {
            $slugs = array_unique($this->platformSlugsForPost($post));
            foreach ($slugs as $slug) {
                $counts[$slug] = ($counts[$slug] ?? 0) + 1;
            }
        }

        return $counts;
    }

    private function tabCountsForWorkspace(int $workspaceId, ?string $platformFilter = null): array
    {
        $base = $this->baseFilteredPostQuery($workspaceId, $platformFilter);

        $all = (int) $base->count();
        $byStatus = (clone $base)
            ->select('status', DB::raw('count(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status');

        $statuses = ['draft', 'scheduled', 'publishing', 'published', 'failed'];
        $out = ['all' => $all];

        foreach ($statuses as $s) {
            $out[$s] = (int) ($byStatus[$s] ?? 0);
        }

        return $out;
    }

    private function baseFilteredPostQuery(int $workspaceId, ?string $platformFilter): Builder
    {
        $query = $this->basePostQuery($workspaceId);
        if ($platformFilter !== null) {
            $this->applyPlatformFilter($query, $platformFilter);
        }

        return $query;
    }

    private function normalizePlatformSlug(string $platform): string
    {
        $platform = trim(strtolower($platform));
        if ($platform === '') {
            return '';
        }

        $platform = preg_replace('/\+.*/', '', $platform);
        return match ($platform) {
            'facebook' => 'facebook',
            'instagram' => 'instagram',
            'linkedin' => 'linkedin',
            'twitter' => 'x',
            'x' => 'x',
            default => $platform,
        };
    }
}
