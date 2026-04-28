<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace);
    }

    public function manageSettings(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('workspace.settings.manage');
    }

    public function inviteMembers(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('workspace.members.invite');
    }

    public function removeMembers(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('workspace.members.remove');
    }

    public function manageBilling(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('workspace.billing.manage');
    }

    private function isActiveMember(User $user, Workspace $workspace): bool
    {
        return $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
