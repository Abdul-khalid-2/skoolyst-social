<?php

namespace App\Support;

final class WorkspacePermissionMap
{
    /**
     * @return list<string>
     */
    public static function permissions(): array
    {
        return [
            'post.create',
            'post.edit',
            'post.delete',
            'post.publish',
            'post.schedule',
            'social_account.connect',
            'social_account.disconnect',
            'social_account.view',
            'workspace.settings.manage',
            'workspace.members.invite',
            'workspace.members.remove',
            'workspace.billing.manage',
            'analytics.view',
            'analytics.export',
            'calendar.view',
            'calendar.manage',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function rolePermissions(): array
    {
        $all = self::permissions();

        return [
            'owner' => $all,
            'admin' => array_values(array_diff($all, ['workspace.billing.manage'])),
            'editor' => [
                'post.create',
                'post.edit',
                'post.delete',
                'post.publish',
                'post.schedule',
                'calendar.view',
                'calendar.manage',
                'analytics.view',
                'social_account.view',
            ],
            'viewer' => [
                'analytics.view',
                'calendar.view',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function roles(): array
    {
        return ['owner', 'admin', 'editor', 'viewer'];
    }
}
