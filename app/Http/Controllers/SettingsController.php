<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebUpdateWorkspaceRequest;
use App\Services\WorkspaceSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly WorkspaceSettingsService $workspaceSettings,
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
}
