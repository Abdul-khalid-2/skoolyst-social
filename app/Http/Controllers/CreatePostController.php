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
        $connectedSlugs = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_connected', true)
            ->whereHas('platform', fn ($q) => $q->whereIn('slug', $allowed))
            ->with('platform')
            ->get()
            ->filter(function (SocialAccount $account) {
                if ($account->token_expires_at && $account->token_expires_at->isPast()) {
                    return false;
                }

                return in_array($account->platform?->slug, ['facebook', 'instagram', 'linkedin', 'twitter'], true);
            })
            ->map(fn (SocialAccount $account) => (string) $account->platform?->slug)
            ->unique()
            ->values();

        return view('posts.create', [
            'workspace' => $workspace,
            'connectedSlugs' => $connectedSlugs,
        ]);
    }
}
