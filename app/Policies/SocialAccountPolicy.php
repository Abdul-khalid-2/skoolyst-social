<?php

namespace App\Policies;

use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;

class SocialAccountPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('social_account.view');
    }

    public function connect(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('social_account.connect');
    }

    public function disconnect(User $user, Workspace|SocialAccount $target): bool
    {
        $workspaceId = $target instanceof SocialAccount ? (int) $target->workspace_id : (int) $target->id;

        return $this->isActiveWorkspaceId($user, $workspaceId) && $user->can('social_account.disconnect');
    }

    private function isActiveMember(User $user, Workspace $workspace): bool
    {
        return $this->isActiveWorkspaceId($user, (int) $workspace->id);
    }

    private function isActiveWorkspaceId(User $user, int $workspaceId): bool
    {
        return $user->workspaces()
            ->where('workspaces.id', $workspaceId)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
