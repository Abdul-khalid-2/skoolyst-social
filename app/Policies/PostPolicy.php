<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use App\Models\Workspace;

class PostPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace)
            && $user->hasAnyPermission([
                'post.create',
                'post.edit',
                'post.delete',
                'post.publish',
                'post.schedule',
                'calendar.view',
                'calendar.manage',
            ]);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $this->isActiveMember($user, $workspace) && $user->can('post.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $this->isActiveWorkspaceId($user, (int) $post->workspace_id) && $user->can('post.edit');
    }

    public function delete(User $user, Post $post): bool
    {
        return $this->isActiveWorkspaceId($user, (int) $post->workspace_id) && $user->can('post.delete');
    }

    public function publish(User $user, Workspace|Post $target): bool
    {
        $workspaceId = $target instanceof Post ? (int) $target->workspace_id : (int) $target->id;

        return $this->isActiveWorkspaceId($user, $workspaceId) && $user->can('post.publish');
    }

    public function schedule(User $user, Workspace|Post $target): bool
    {
        $workspaceId = $target instanceof Post ? (int) $target->workspace_id : (int) $target->id;

        return $this->isActiveWorkspaceId($user, $workspaceId) && $user->can('post.schedule');
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
