<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RegisterUserWithDefaultWorkspaceAction
{
    public function execute(array $data): User
    {
        $data = Arr::only($data, ['name', 'email', 'password', 'workspace_name']);

        return DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->refresh();

            $workspaceName = (isset($data['workspace_name']) && is_string($data['workspace_name']) && trim($data['workspace_name']) !== '')
                ? trim($data['workspace_name'])
                : ($data['name']."'s Workspace");

            $workspace = Workspace::query()->create([
                'owner_id' => $user->id,
                'name' => $workspaceName,
                'slug' => $this->makeWorkspaceSlug($user->id, $workspaceName),
                'plan' => 'free',
            ]);

            $workspace->members()->attach($user->id, [
                'role' => 'owner',
                'is_active' => true,
            ]);
            if (function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId((int) $workspace->id);
            }
            app(PermissionRegistrar::class)->setPermissionsTeamId((int) $workspace->id);
            Role::findOrCreate('owner', 'web');
            $user->syncRoles(['owner']);

            return $user;
        });
    }

    private function makeWorkspaceSlug(int $userId, string $workspaceName): string
    {
        $segment = Str::slug($workspaceName);
        if ($segment === '') {
            $segment = 'workspace';
        }

        return $segment.'-ws-'.$userId;
    }
}
