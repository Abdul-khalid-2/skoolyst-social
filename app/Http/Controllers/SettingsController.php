<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebDestroyFacebookDataRequest;
use App\Http\Requests\WebUpdateWorkspaceRequest;
use App\Models\Subscription;
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

    public function index(Request $request, string $tab = 'workspace'): View
    {
        $validTabs = ['workspace', 'profile', 'notifications', 'security', 'appearance', 'billing', 'integrations', 'roles', 'superadmin'];
        if (! in_array($tab, $validTabs)) {
            $tab = 'workspace';
        }

        $user             = $request->user();
        $workspace        = $this->workspaceSettings->getCurrentWorkspace($user);
        $canEditWorkspace = $workspace && $this->workspaceSettings->userCanEditWorkspaceName($user, $workspace);

        // Set team scope for workspace-scoped permission checks
        $workspaceId = (int) $request->session()->get('current_workspace_id', 0);
        if ($workspaceId) {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($workspaceId);
            }
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($workspaceId);
        }

        $canInviteMembers = $workspace && $user->can('workspace.members.invite');
        $canRemoveMembers = $workspace && $user->can('workspace.members.remove');
        $canManageBilling = $workspace && $user->can('workspace.billing.manage');

        // Members
        $members = $workspace
            ? $workspace->members()
                ->wherePivot('is_active', true)
                ->withPivot('role', 'created_at')
                ->orderByPivot('created_at')
                ->get()
            : collect();

        // Subscription (active or trialing only)
        $subscription = $workspace
            ? Subscription::where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'trialing'])
                ->latest('started_at')
                ->first()
            : null;

        $billingHistory = $workspace
            ? \App\Models\PaymentTransaction::where('workspace_id', $workspace->id)
                ->with('subscription')
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        $allPlans = \App\Support\PlanConfig::all();

        // Check superadmin with team_id = null (not team-scoped)
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(null);
        $isSuperadmin = $user->hasRole('superadmin');

        // Restore team scope for role checks
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
            'canInviteMembers', 'canRemoveMembers', 'canManageBilling',
            'members', 'subscription', 'billingHistory', 'allPlans',
            'isOwner', 'isSuperadmin',
            'roles', 'allPermissions', 'permissionGroups',
            'allUsers', 'allWorkspaces', 'plans',
            'title', 'description',
            'tab'
        ));
    }

    public function updateWorkspace(WebUpdateWorkspaceRequest $request): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $this->workspaceSettings->getCurrentWorkspace($user);
        if ($workspace === null) {
            return redirect()
                ->route('settings.tab', 'workspace')
                ->with('error', __('No workspace found.'));
        }

        $this->workspaceSettings->updateWorkspaceName(
            $user,
            $workspace,
            (string) $request->validated('workspace_name')
        );

        return redirect()
            ->route('settings.tab', 'workspace')
            ->with('success', __('Workspace updated.'));
    }

    public function inviteMember(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role'  => ['required', 'in:admin,editor,viewer'],
        ]);

        $workspace = $this->workspaceSettings->getCurrentWorkspace($request->user());
        abort_if(! $workspace, 404);
        abort_unless($request->user()->can('workspace.members.invite'), 403);

        $invitee = \App\Models\User::where('email', $validated['email'])->first();

        if (! $invitee) {
            return redirect()->route('settings.tab', 'workspace')
                ->withErrors(['invite_email' => 'No user found with that email address.']);
        }

        if ($workspace->members()->where('user_id', $invitee->id)->exists()) {
            return redirect()->route('settings.tab', 'workspace')
                ->withErrors(['invite_email' => 'This user is already a member of this workspace.']);
        }

        $workspace->members()->attach($invitee->id, [
            'role'      => $validated['role'],
            'is_active' => true,
        ]);

        $workspaceId = (int) $workspace->id;
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($workspaceId);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($workspaceId);
        \Spatie\Permission\Models\Role::findOrCreate($validated['role'], 'web');
        $invitee->syncRoles([$validated['role']]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('settings.tab', 'workspace')
            ->with('success', "{$invitee->name} added as {$validated['role']}.");
    }

    public function removeMember(Request $request, \App\Models\User $user): RedirectResponse
    {
        $workspace = $this->workspaceSettings->getCurrentWorkspace($request->user());
        abort_if(! $workspace, 404);
        abort_unless($request->user()->can('workspace.members.remove'), 403);
        abort_if($user->id === $workspace->owner_id, 403, 'Cannot remove the workspace owner.');
        abort_if($user->id === $request->user()->id, 403, 'Cannot remove yourself.');

        $workspace->members()->detach($user->id);

        $workspaceId = (int) $workspace->id;
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($workspaceId);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($workspaceId);
        $user->syncRoles([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('settings.tab', 'workspace')
            ->with('success', "{$user->name} removed from workspace.");
    }

    public function updateMemberRole(Request $request, \App\Models\User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'in:admin,editor,viewer'],
        ]);

        $workspace = $this->workspaceSettings->getCurrentWorkspace($request->user());
        abort_if(! $workspace, 404);
        abort_unless($request->user()->can('workspace.settings.manage'), 403);
        abort_if($user->id === $workspace->owner_id, 403, 'Cannot change the owner role.');

        $workspace->members()->updateExistingPivot($user->id, ['role' => $validated['role']]);

        $workspaceId = (int) $workspace->id;
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($workspaceId);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($workspaceId);
        \Spatie\Permission\Models\Role::findOrCreate($validated['role'], 'web');
        $user->syncRoles([$validated['role']]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('settings.tab', 'workspace')
            ->with('success', "{$user->name}'s role updated to {$validated['role']}.");
    }

    public function destroyFacebookData(WebDestroyFacebookDataRequest $request): RedirectResponse
    {
        $code = $this->facebookDataDeletion->purgeForAuthenticatedUser($request->user());
        if ($code === null) {
            return redirect()
                ->route('settings.tab', 'integrations')
                ->with('error', __('No Facebook-linked data is stored for this account.'));
        }

        return redirect()
            ->route('data-deletion', ['code' => $code])
            ->with('success', __('Facebook-connected data has been removed from your account.'));
    }
}
