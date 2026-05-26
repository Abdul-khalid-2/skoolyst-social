<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\Workspace;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreatePostController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $id = $this->dashboardService->resolveWorkspaceId($user);
        if ($id === null) {
            return view('posts.create', [
                'workspace' => null,
                'connectedSlugs' => collect(),
                'pausedSlugs' => collect(),
                'accountsByPlatform' => [],
            ]);
        }

        $workspace = Workspace::query()->find($id);
        if ($workspace === null) {
            return view('posts.create', [
                'workspace' => null,
                'connectedSlugs' => collect(),
                'pausedSlugs' => collect(),
                'accountsByPlatform' => [],
            ]);
        }

        $allowed = ['facebook', 'instagram', 'linkedin', 'twitter'];
        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_connected', true)
            ->whereHas('platform', fn ($q) => $q->whereIn('slug', $allowed))
            ->with('platform')
            ->get()
            ->filter(fn (SocialAccount $account) => ! ($account->token_expires_at && $account->token_expires_at->isPast())
                && in_array($account->platform?->slug, $allowed, true));

        // Active accounts — fully usable for publishing
        $connectedSlugs = $accounts
            ->filter(fn (SocialAccount $a) => (bool) $a->is_active)
            ->map(fn (SocialAccount $a) => (string) $a->platform?->slug)
            ->unique()
            ->values();

        // Paused accounts — connected but is_active=false; shown greyed in UI
        $pausedSlugs = collect(
            $accounts
                ->filter(fn (SocialAccount $a) => ! $a->is_active)
                ->map(fn (SocialAccount $a) => (string) $a->platform?->slug)
                ->toArray()
        )->diff($connectedSlugs)  // base Collection::diff() — works with plain strings
         ->unique()
         ->values();

        // Per-account/per-page targeting data: shape every connected account into a flat
        // structure the Alpine UI can use to render the targeting modal. Twitter rows are
        // included for visibility but excluded from active publishing (not implemented in
        // the publisher) — they show as "not supported" in the modal.
        $accountsByPlatform = $accounts
            ->groupBy(fn (SocialAccount $a) => (string) $a->platform?->slug)
            ->map(fn ($group) => $group
                ->map(fn (SocialAccount $a) => [
                    'id'           => (int) $a->id,
                    'platform'     => (string) $a->platform?->slug,
                    'account_name' => (string) ($a->account_name ?: $a->account_handle ?: ('Account #'.$a->id)),
                    'account_handle' => (string) ($a->account_handle ?? ''),
                    'page_id'      => $a->platform_page_id ? (string) $a->platform_page_id : null,
                    'avatar'       => $a->avatar,
                    'is_active'    => (bool) $a->is_active,
                ])
                ->values()
                ->all()
            )
            ->all();

        return view('posts.create', [
            'workspace' => $workspace,
            'connectedSlugs' => $connectedSlugs,
            'pausedSlugs' => $pausedSlugs,
            'accountsByPlatform' => $accountsByPlatform,
        ]);
    }
}
