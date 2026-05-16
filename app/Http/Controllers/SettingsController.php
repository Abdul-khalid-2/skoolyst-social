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
        $user             = $request->user();
        $workspace        = $this->workspaceSettings->getCurrentWorkspace($user);
        $canEditWorkspace = $workspace && $this->workspaceSettings->userCanEditWorkspaceName($user, $workspace);

        // Check superadmin with team_id = null (not team-scoped)
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        $isSuperadmin = $user->hasRole('superadmin');

        // Restore team for workspace-scoped checks
        $workspaceId = (int) $request->session()->get('current_workspace_id', 0);
        if ($workspaceId) {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($workspaceId);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($workspaceId);
        }
        $isOwner = $user->hasRole('owner') || $isSuperadmin;

        $allPermissions   = \App\Support\WorkspacePermissionMap::permissions();
        $permissionGroups = collect($allPermissions)
            ->groupBy(fn ($p) => explode('.', $p)[0])
            ->toArray();

        $roles = $isOwner
            ? \Spatie\Permission\Models\Role::where('guard_name', 'web')
                ->with('permissions')
                ->get()
                ->map(fn ($r) => [
                    'name'        => $r->name,
                    'permissions' => $r->permissions->pluck('name')->toArray(),
                ])
            : collect();

        $allUsers      = $isSuperadmin ? \App\Models\User::withCount('workspaces')->latest()->get() : collect();
        $allWorkspaces = $isSuperadmin ? \App\Models\Workspace::with('owner')->withCount('members')->latest()->get() : collect();
        $plans         = ['free', 'starter', 'pro', 'business', 'enterprise'];

        $title       = 'Settings';
        $description = 'Workspace and application preferences.';

        return view('settings.index', compact(
            'user', 'workspace', 'canEditWorkspace',
            'isOwner', 'isSuperadmin',
            'roles', 'allPermissions', 'permissionGroups',
            'allUsers', 'allWorkspaces', 'plans',
            'title', 'description'
        ));
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
            ->route('data-deletion', ['code' => $code])
            ->with('success', __('Facebook-connected data has been removed from your account.'));
    }
}
