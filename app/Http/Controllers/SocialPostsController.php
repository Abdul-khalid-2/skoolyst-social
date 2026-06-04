<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\DashboardService;
use App\Services\SocialPostListingService;
use App\Services\SocialPostStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialPostsController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
        private readonly SocialPostListingService $listingService,
        private readonly SocialPostStatsService $statsService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $workspaceId = $this->dashboardService->resolveWorkspaceId($user);
        $workspace = $workspaceId ? Workspace::query()->find($workspaceId) : null;

        $initialPlatform = in_array($request->query('platform'), ['facebook', 'instagram', 'linkedin', 'twitter'], true)
            ? $request->query('platform')
            : 'facebook';

        $postsByPlatform = $workspaceId
            ? $this->listingService->listByPlatform($workspaceId)
            : ['facebook' => [], 'instagram' => [], 'linkedin' => [], 'twitter' => [], 'accounts' => []];

        return view('social-posts.index', [
            'title' => 'Social Posts',
            'description' => 'View and manage posts across your connected accounts',
            'workspace' => $workspace,
            'initialPlatform' => $initialPlatform,
            'postsByPlatform' => $postsByPlatform,
            'refreshStatsUrl' => route('social-posts.refresh-stats'),
            'commentsUrlTemplate' => url('/social-posts/targets/__TARGET__/comments'),
        ]);
    }

    public function refreshStats(Request $request): JsonResponse
    {
        $workspaceId = $this->dashboardService->resolveWorkspaceId($request->user());
        if ($workspaceId === null) {
            return response()->json(['message' => 'No workspace selected.'], 403);
        }

        $platform = $request->input('platform');
        if ($platform !== null && ! in_array($platform, ['facebook', 'instagram', 'linkedin', 'twitter'], true)) {
            return response()->json(['message' => 'Invalid platform.'], 422);
        }

        $targetIds = $request->input('target_ids', []);
        if (! is_array($targetIds)) {
            $targetIds = [];
        }

        $limit = 12;
        $totalEligible = $this->listingService->countPublishedTargetsForRefresh($workspaceId, $platform);
        $targets = $this->listingService->publishedTargetsForRefresh(
            $workspaceId,
            $platform,
            $targetIds,
            $limit,
        );

        $result = $this->statsService->syncManyTargets($targets);
        $processed = $targets->count();
        $remaining = max(0, $totalEligible - $processed);

        $postsByPlatform = $this->listingService->listByPlatform($workspaceId);

        $message = "Synced {$result['synced']} post(s)";
        if ($result['failed'] > 0) {
            $message .= ", {$result['failed']} failed";
        }
        if ($remaining > 0 && $targetIds === []) {
            $message .= ". {$remaining} more — click Refresh again.";
        }

        return response()->json([
            'synced' => $result['synced'],
            'failed' => $result['failed'],
            'processed' => $processed,
            'remaining' => $remaining,
            'message' => $message,
            'errors' => $result['errors'],
            'posts' => [
                'facebook'  => $postsByPlatform['facebook'],
                'instagram' => $postsByPlatform['instagram'],
                'linkedin'  => $postsByPlatform['linkedin'],
                'twitter'   => $postsByPlatform['twitter'],
            ],
        ]);
    }

    public function comments(Request $request, int $target): JsonResponse
    {
        $workspaceId = $this->dashboardService->resolveWorkspaceId($request->user());
        if ($workspaceId === null) {
            return response()->json(['message' => 'No workspace selected.'], 403);
        }

        $postTarget = $this->listingService->findTargetForWorkspace($workspaceId, $target);
        if ($postTarget === null) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        if ($postTarget->status !== 'published' || ! filled($postTarget->platform_post_id)) {
            return response()->json(['message' => 'Post is not published on this platform.'], 422);
        }

        $shouldRefresh = $request->boolean('refresh')
            || $postTarget->comments_synced_at === null;

        if ($shouldRefresh) {
            $synced = $this->statsService->syncTargetComments($postTarget);
            if (! $synced && $postTarget->storedComments()->doesntExist()) {
                return response()->json([
                    'message' => 'Could not load comments from the platform.',
                ], 422);
            }

            $postTarget->refresh();
        }

        return response()->json([
            'comments' => $this->statsService->getStoredComments($postTarget),
            'synced_at' => $postTarget->comments_synced_at?->toIso8601String(),
        ]);
    }
}
