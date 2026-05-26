<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditPostController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function show(Request $request, Post $post): View|\Illuminate\Http\RedirectResponse
    {
        $user = $request->user();
        $id   = $this->dashboardService->resolveWorkspaceId($user);

        $workspace = $id ? Workspace::query()->find($id) : null;

        if (! $workspace || $post->workspace_id !== $workspace->id) {
            abort(403);
        }

        if (! in_array($post->status, ['draft', 'scheduled'], true)) {
            return redirect()->route('posts.index')
                ->with('error', 'Only draft or scheduled posts can be edited.');
        }

        $post->load(['postMedia', 'postTargets.socialPlatform', 'postTargets.socialAccount']);

        $allowed = ['facebook', 'instagram', 'linkedin', 'twitter'];

        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_connected', true)
            ->whereHas('platform', fn ($q) => $q->whereIn('slug', $allowed))
            ->with('platform')
            ->get()
            ->filter(fn (SocialAccount $a) => ! ($a->token_expires_at?->isPast())
                && in_array($a->platform?->slug, $allowed, true));

        $connectedSlugs = $accounts
            ->filter(fn (SocialAccount $a) => (bool) $a->is_active)
            ->map(fn (SocialAccount $a) => (string) $a->platform?->slug)
            ->unique()
            ->values();

        $existingPlatformSlugs = $post->postTargets
            ->map(fn ($t) => $t->socialPlatform?->slug)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Account ids currently targeted by this post (used to pre-select the modal toggles).
        $existingTargetAccountIds = $post->postTargets
            ->map(fn ($t) => (int) $t->social_account_id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $accountsByPlatform = $accounts
            ->groupBy(fn (SocialAccount $a) => (string) $a->platform?->slug)
            ->map(fn ($group) => $group
                ->map(fn (SocialAccount $a) => [
                    'id'             => (int) $a->id,
                    'platform'       => (string) $a->platform?->slug,
                    'account_name'   => (string) ($a->account_name ?: $a->account_handle ?: ('Account #'.$a->id)),
                    'account_handle' => (string) ($a->account_handle ?? ''),
                    'page_id'        => $a->platform_page_id ? (string) $a->platform_page_id : null,
                    'avatar'         => $a->avatar,
                    'is_active'      => (bool) $a->is_active,
                ])
                ->values()
                ->all()
            )
            ->all();

        return view('posts.edit', [
            'workspace'                => $workspace,
            'post'                     => $post,
            'connectedSlugs'           => $connectedSlugs,
            'existingPlatformSlugs'    => $existingPlatformSlugs,
            'existingTargetAccountIds' => $existingTargetAccountIds,
            'accountsByPlatform'       => $accountsByPlatform,
            'existingMedia'            => $post->postMedia->first(),
        ]);
    }
}
