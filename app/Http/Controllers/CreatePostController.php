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
            ]);
        }

        $workspace = Workspace::query()->find($id);
        if ($workspace === null) {
            return view('posts.create', [
                'workspace' => null,
                'connectedSlugs' => collect(),
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
        $pausedSlugs = $accounts
            ->filter(fn (SocialAccount $a) => ! $a->is_active)
            ->map(fn (SocialAccount $a) => (string) $a->platform?->slug)
            ->diff($connectedSlugs)  // exclude if any active account exists for same platform
            ->unique()
            ->values();

        return view('posts.create', [
            'workspace' => $workspace,
            'connectedSlugs' => $connectedSlugs,
            'pausedSlugs' => $pausedSlugs,
        ]);
    }
}
