<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

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
     * @return array{posts: LengthAwarePaginator, tabCounts: array<string, int>, workspace: ?Workspace, filter: string}
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
            ];
        }

        $tabCounts = $this->tabCountsForWorkspace((int) $workspace->id);

        $q = $this->basePostQuery($workspace->id);
        if ($statusFilter !== null && $statusFilter !== 'all' && $statusFilter !== '') {
            $q->where('status', $statusFilter);
        }

        $posts = $q->latest()->paginate($perPage)->withQueryString();

        return [
            'posts' => $posts,
            'tabCounts' => $tabCounts,
            'workspace' => $workspace,
            'filter' => $statusFilter === null || $statusFilter === '' ? 'all' : $statusFilter,
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
            return array_values(array_filter(array_map('strval', $fromJson), fn (string $s): bool => $s !== ''));
        }

        return $post->postTargets
            ->map(fn ($t) => $t->socialPlatform?->slug)
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
    private function tabCountsForWorkspace(int $workspaceId): array
    {
        $all = (int) Post::query()->where('workspace_id', $workspaceId)->count();
        $byStatus = Post::query()
            ->where('workspace_id', $workspaceId)
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
}
