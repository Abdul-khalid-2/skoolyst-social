<?php

use App\Models\User;
use App\Support\WorkspacePermissionMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (WorkspacePermissionMap::permissions() as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        foreach (WorkspacePermissionMap::rolePermissions() as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }

        DB::table('workspace_user')
            ->select(['id', 'workspace_id', 'user_id', 'role', 'is_active'])
            ->orderBy('id')
            ->chunkById(200, function ($memberships) use ($registrar): void {
                foreach ($memberships as $membership) {
                    if (! (bool) $membership->is_active) {
                        continue;
                    }

                    $user = User::query()->find($membership->user_id);
                    if (! $user) {
                        continue;
                    }

                    $workspaceId = (int) $membership->workspace_id;
                    $roleName = in_array($membership->role, WorkspacePermissionMap::roles(), true)
                        ? (string) $membership->role
                        : 'viewer';

                    if (function_exists('setPermissionsTeamId')) {
                        setPermissionsTeamId($workspaceId);
                    }
                    $registrar->setPermissionsTeamId($workspaceId);

                    $user->syncRoles([$roleName]);
                }
            });

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', User::class)
            ->delete();

        Role::query()
            ->whereIn('name', WorkspacePermissionMap::roles())
            ->where('guard_name', 'web')
            ->delete();

        Permission::query()
            ->whereIn('name', WorkspacePermissionMap::permissions())
            ->where('guard_name', 'web')
            ->delete();

        $registrar->forgetCachedPermissions();
    }
};
