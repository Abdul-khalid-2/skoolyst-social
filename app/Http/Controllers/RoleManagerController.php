<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePermissionMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleManagerController extends Controller
{
    private const PROTECTED_ROLES = ['superadmin', 'owner', 'admin', 'editor', 'viewer'];

    private function setTeam(Request $request): void
    {
        $id = (int) $request->session()->get('current_workspace_id', 0);
        if ($id) {
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($id);
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId($id);
        }
    }

    private function authOwner(Request $request): void
    {
        $this->setTeam($request);
        abort_unless($request->user()->hasRole('owner') || $request->user()->hasRole('superadmin'), 403, 'Owner role required.');
    }

    private function authSuperadmin(Request $request): void
    {
        $this->setTeam($request);
        abort_unless($request->user()->hasRole('superadmin'), 403, 'Superadmin role required.');
    }

    // ── Role CRUD ────────────────────────────────────────────────────────────

    public function storeRole(Request $request): RedirectResponse
    {
        $this->authOwner($request);
        $validated = $request->validate(['name' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9_\-]+$/']]);
        $name = strtolower(trim($validated['name']));
        abort_if(Role::where('name', $name)->where('guard_name', 'web')->exists(), 422, 'Role already exists.');
        Role::create(['name' => $name, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Role '{$name}' created.");
    }

    public function updateRole(Request $request, string $roleName): RedirectResponse
    {
        $this->authOwner($request);
        abort_if(in_array($roleName, self::PROTECTED_ROLES), 403, 'Cannot rename a built-in role.');
        $validated = $request->validate(['name' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9_\-]+$/']]);
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $role->name = strtolower(trim($validated['name']));
        $role->save();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', 'Role renamed.');
    }

    public function destroyRole(Request $request, string $roleName): RedirectResponse
    {
        $this->authOwner($request);
        abort_if(in_array($roleName, self::PROTECTED_ROLES), 403, 'Cannot delete a built-in role.');
        Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail()->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Role '{$roleName}' deleted.");
    }

    public function syncPermissions(Request $request, string $roleName): RedirectResponse
    {
        $this->authOwner($request);
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $selected = array_values(array_intersect(
            $request->input('permissions', []),
            WorkspacePermissionMap::permissions()
        ));
        foreach ($selected as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $role->syncPermissions($selected);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Permissions synced for '{$roleName}'.");
    }

    public function repair(Request $request): RedirectResponse
    {
        $this->authOwner($request);
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (WorkspacePermissionMap::permissions() as $p) {
            Permission::findOrCreate($p, 'web');
        }
        foreach (WorkspacePermissionMap::rolePermissions() as $roleName => $perms) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($perms);
        }

        $fixed = 0;
        DB::table('workspace_user')->where('is_active', true)->orderBy('id')->chunk(200, function ($rows) use ($registrar, &$fixed) {
            foreach ($rows as $m) {
                $user = User::find($m->user_id);
                if (! $user) {
                    continue;
                }
                $roleName = in_array($m->role, WorkspacePermissionMap::roles(), true) ? $m->role : 'viewer';
                if (function_exists('setPermissionsTeamId')) {
                    setPermissionsTeamId((int) $m->workspace_id);
                }
                $registrar->setPermissionsTeamId((int) $m->workspace_id);
                $user->syncRoles([$roleName]);
                $fixed++;
            }
        });

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        return back()->with('success', "Repaired roles for {$fixed} workspace memberships.");
    }

    // ── Superadmin: assign superadmin role to a user ─────────────────────────

    public function makeSuperadmin(Request $request): RedirectResponse
    {
        $this->authSuperadmin($request);
        $validated = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $user = User::findOrFail($validated['user_id']);

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Role::findOrCreate('superadmin', 'web');
        $user->assignRole('superadmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "{$user->name} is now a superadmin.");
    }

    public function removeSuperadmin(Request $request, User $user): RedirectResponse
    {
        $this->authSuperadmin($request);
        abort_if($user->id === $request->user()->id, 403, 'Cannot remove your own superadmin role.');

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $user->removeRole('superadmin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return back()->with('success', "Superadmin removed from {$user->name}.");
    }

    // ── Superadmin: user management ──────────────────────────────────────────

    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        $this->authSuperadmin($request);
        abort_if($user->id === $request->user()->id, 403, 'Cannot deactivate yourself.');
        $user->is_active = ! $user->is_active;
        $user->save();

        return back()->with('success', "User {$user->name} " . ($user->is_active ? 'activated' : 'deactivated') . '.');
    }

    // ── Superadmin: workspace management ─────────────────────────────────────

    public function toggleWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authSuperadmin($request);
        $workspace->is_active = ! $workspace->is_active;
        $workspace->save();

        return back()->with('success', "Workspace '{$workspace->name}' " . ($workspace->is_active ? 'activated' : 'deactivated') . '.');
    }

    public function updatePlan(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authSuperadmin($request);
        $validated = $request->validate(['plan' => ['required', 'string', 'in:free,starter,pro,business,enterprise']]);
        $workspace->plan = $validated['plan'];
        $workspace->save();

        return back()->with('success', "Plan updated for '{$workspace->name}'.");
    }
}
