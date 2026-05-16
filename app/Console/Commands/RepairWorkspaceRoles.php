<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\WorkspacePermissionMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RepairWorkspaceRoles extends Command
{
    protected $signature   = 'workspace:repair-roles';
    protected $description = 'Re-assign Spatie roles scoped to correct workspace team_id for all members';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();

        foreach (WorkspacePermissionMap::permissions() as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        foreach (WorkspacePermissionMap::rolePermissions() as $roleName => $perms) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($perms);
        }

        $memberships = DB::table('workspace_user')->where('is_active', true)->get();
        $fixed = 0;

        foreach ($memberships as $m) {
            $user = User::find($m->user_id);
            if (! $user) {
                continue;
            }

            $roleName    = in_array($m->role, WorkspacePermissionMap::roles(), true) ? $m->role : 'viewer';
            $workspaceId = (int) $m->workspace_id;

            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($workspaceId);
            }
            $registrar->setPermissionsTeamId($workspaceId);
            $user->syncRoles([$roleName]);

            $this->line("  [ok] User #{$user->id} ({$user->email}) -> {$roleName} on workspace #{$workspaceId}");
            $fixed++;
        }

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $this->info("Done. Fixed {$fixed} memberships. Run php artisan cache:clear if issues persist.");

        return self::SUCCESS;
    }
}
