<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspacePermissionMap;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (WorkspacePermissionMap::permissions() as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $superadminRole = Role::findOrCreate('superadmin', 'web');
        $superadminRole->syncPermissions(WorkspacePermissionMap::permissions());

        foreach (WorkspacePermissionMap::rolePermissions() as $roleName => $perms) {
            if ($roleName === 'superadmin') {
                continue;
            }
            Role::findOrCreate($roleName, 'web')->syncPermissions($perms);
        }

        $user = User::updateOrCreate(
            ['email' => 'skoolystadmin@gmail.com'],
            [
                'name'              => 'Skoolyst Admin',
                'password'          => Hash::make('nmpd7788'),
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        $workspace = $user->workspaces()->first();

        if (! $workspace) {
            $workspace = Workspace::create([
                'owner_id'  => $user->id,
                'name'      => 'Skoolyst Admin Workspace',
                'slug'      => 'skoolyst-admin-ws-' . $user->id,
                'plan'      => 'enterprise',
                'is_active' => true,
            ]);

            $workspace->members()->attach($user->id, [
                'role'      => 'owner',
                'is_active' => true,
            ]);

            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId((int) $workspace->id);
            }
            $registrar->setPermissionsTeamId((int) $workspace->id);
            $user->syncRoles(['owner']);
        }

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(null);
        }
        $registrar->setPermissionsTeamId(null);
        $user->assignRole('superadmin');

        $registrar->forgetCachedPermissions();

        $this->command->info('Superadmin seeded: skoolystadmin@gmail.com');
        $this->command->info('Workspace: ' . $workspace->name . ' (ID: ' . $workspace->id . ')');
    }
}
