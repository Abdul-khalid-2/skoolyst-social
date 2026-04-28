<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class AnalyticsPolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('analytics.view');
    }

    public function export(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('analytics.export');
    }

    private function isActiveMember(User $user, Workspace $workspace): bool
    {
        return $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->wherePivot('is_active', true)
            ->exists();
    }
}
