<?php

namespace App\Support;

final class WorkspacePermissionMap
{
    public static function permissions(): array
    {
        return [
            // Posts
            'post.create',
            'post.edit',
            'post.delete',
            'post.publish',
            'post.schedule',
            // Social accounts
            'social_account.connect',
            'social_account.disconnect',
            'social_account.view',
            // Workspace
            'workspace.settings.manage',
            'workspace.members.invite',
            'workspace.members.remove',
            'workspace.billing.manage',
            // Analytics & Calendar
            'analytics.view',
            'analytics.export',
            'calendar.view',
            'calendar.manage',
            // Superadmin only
            'superadmin.users.view',
            'superadmin.users.activate',
            'superadmin.users.deactivate',
            'superadmin.workspaces.view',
            'superadmin.workspaces.activate',
            'superadmin.workspaces.deactivate',
            'superadmin.plans.manage',
            'superadmin.roles.manage',
        ];
    }

    public static function rolePermissions(): array
    {
        $all             = self::permissions();
        $workspacePerms  = array_values(array_filter($all, fn ($p) => ! str_starts_with($p, 'superadmin.')));

        return [
            'superadmin' => $all,
            'owner'      => $workspacePerms,
            'admin'      => array_values(array_diff($workspacePerms, ['workspace.billing.manage'])),
            'editor'     => [
                'post.create', 'post.edit', 'post.delete', 'post.publish', 'post.schedule',
                'calendar.view', 'calendar.manage', 'analytics.view', 'social_account.view',
            ],
            'viewer' => ['analytics.view', 'calendar.view'],
        ];
    }

    public static function roles(): array
    {
        return ['superadmin', 'owner', 'admin', 'editor', 'viewer'];
    }
}
