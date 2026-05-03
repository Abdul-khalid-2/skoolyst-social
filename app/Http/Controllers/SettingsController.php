<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebDestroyFacebookDataRequest;
use App\Http\Requests\WebUpdateWorkspaceRequest;
use App\Services\FacebookDataDeletionService;
use App\Services\WorkspaceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly WorkspaceSettingsService $workspaceSettings,
        private readonly FacebookDataDeletionService $facebookDataDeletion,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $workspace = $this->workspaceSettings->getCurrentWorkspace($user);
        $canEditWorkspace = $workspace && $this->workspaceSettings->userCanEditWorkspaceName($user, $workspace);

        return view('settings.index', [
            'user' => $user,
            'workspace' => $workspace,
            'canEditWorkspace' => $canEditWorkspace,
            'title' => 'Settings',
            'description' => 'Workspace and application preferences.',
        ]);
    }

    public function updateWorkspace(WebUpdateWorkspaceRequest $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $this->workspaceSettings->getCurrentWorkspace($user);
        if ($workspace === null) {
            return redirect()
                ->route('settings')
                ->with('error', __('No workspace found.'));
        }

        $this->workspaceSettings->updateWorkspaceName(
            $user,
            $workspace,
            (string) $request->validated('workspace_name')
        );

        return redirect()
            ->route('settings')
            ->with('success', __('Workspace updated.'));
    }

    public function destroyFacebookData(WebDestroyFacebookDataRequest $request): RedirectResponse
    {
        $code = $this->facebookDataDeletion->purgeForAuthenticatedUser($request->user());
        if ($code === null) {
            return redirect()
                ->route('settings')
                ->with('error', __('No Facebook-linked data is stored for this account.'));
        }

        return redirect()
            ->route('privacy.data-deletion', ['code' => $code])
            ->with('success', __('Facebook-connected data has been removed from your account.'));
    }
}
