<?php

use App\Support\WorkspacePermissionMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('remember_token');
            });
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (WorkspacePermissionMap::permissions() as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $role = Role::findOrCreate('superadmin', 'web');
        $role->syncPermissions(WorkspacePermissionMap::permissions());

        foreach (WorkspacePermissionMap::rolePermissions() as $roleName => $perms) {
            if ($roleName === 'superadmin') {
                continue;
            }
            $r = Role::findOrCreate($roleName, 'web');
            $r->syncPermissions($perms);
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'superadmin')->where('guard_name', 'web')->delete();
        Permission::whereIn('name', [
            'superadmin.users.view', 'superadmin.users.activate', 'superadmin.users.deactivate',
            'superadmin.workspaces.view', 'superadmin.workspaces.activate', 'superadmin.workspaces.deactivate',
            'superadmin.plans.manage', 'superadmin.roles.manage',
        ])->delete();

        if (Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_active'));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
