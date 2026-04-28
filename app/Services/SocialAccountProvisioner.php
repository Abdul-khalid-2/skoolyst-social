<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;

class SocialAccountProvisioner
{
    public static function connectFacebookOnlyForWorkspace(
        Workspace $workspace,
        string $facebookUserId,
        string $accessToken,
        ?\Illuminate\Support\Carbon $expiresAt = null,
        ?string $accountName = null
    ): void {
        $facebook = SocialPlatform::query()
            ->where('slug', 'facebook')
            ->where('is_active', true)
            ->first();

        if (! $facebook) {
            return;
        }

        SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'social_platform_id' => $facebook->id,
            ],
            [
                'platform_user_id' => $facebookUserId,
                'account_name' => $accountName ?: 'Facebook',
                'access_token' => $accessToken,
                'token_expires_at' => $expiresAt,
                'is_connected' => true,
            ],
        );
    }

    public static function connectFacebookPagesForWorkspace(
        Workspace $workspace,
        string $facebookUserId,
        string $userAccessToken,
        ?\Illuminate\Support\Carbon $expiresAt = null
    ): int {
        $facebook = SocialPlatform::query()
            ->where('slug', 'facebook')
            ->where('is_active', true)
            ->first();

        if (! $facebook) {
            return 0;
        }

        $graphVersion = (string) config('services.facebook.graph_version', 'v24.0');
        $res = Http::timeout(20)->get("https://graph.facebook.com/{$graphVersion}/me/accounts", [
            'fields' => 'id,name,access_token,picture',
            'access_token' => $userAccessToken,
        ]);

        if (! $res->successful()) {
            return 0;
        }

        $pages = $res->json('data');
        if (! is_array($pages)) {
            return 0;
        }

        $connected = 0;
        $after = null;

        do {
            foreach ($pages as $page) {
                if (! is_array($page)) {
                    continue;
                }

                $pageId = (string) ($page['id'] ?? '');
                $pageToken = (string) ($page['access_token'] ?? '');
                if ($pageId === '' || $pageToken === '') {
                    continue;
                }

                SocialAccount::query()->updateOrCreate(
                    [
                        'workspace_id' => $workspace->id,
                        'social_platform_id' => $facebook->id,
                        'platform_page_id' => $pageId,
                    ],
                    [
                        'platform_user_id' => $facebookUserId,
                        'account_name' => (string) ($page['name'] ?? 'Facebook Page'),
                        'account_handle' => $pageId,
                        'avatar' => self::shortUrl($page['picture']['data']['url'] ?? null),
                        'access_token' => encrypt($pageToken),
                        'token_expires_at' => $expiresAt,
                        'is_connected' => true,
                    ],
                );

                $connected++;
            }

            $next = $res->json('paging.cursors.after');
            $next = is_string($next) && $next !== '' ? $next : null;
            if ($next === null) {
                $pages = [];
                $after = null;
                break;
            }

            $after = $next;
            $res = Http::timeout(20)->get("https://graph.facebook.com/{$graphVersion}/me/accounts", [
                'fields' => 'id,name,access_token,picture',
                'access_token' => $userAccessToken,
                'limit' => 25,
                'after' => $after,
            ]);

            if (! $res->successful()) {
                break;
            }

            $pages = $res->json('data');
            if (! is_array($pages)) {
                $pages = [];
            }
        } while (count($pages) > 0);

        return $connected;
    }

    private static function shortUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return strlen($url) <= 255 ? $url : null;
    }
}
