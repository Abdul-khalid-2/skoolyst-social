<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialAccountProvisioner
{
    private const FACEBOOK_PAGE_FIELDS = 'id,name,access_token,fan_count,followers_count,picture,likes.limit(0).summary(true),instagram_business_account{id,username,profile_picture_url,name,followers_count,follows_count,media_count}';

    public static function ensureForWorkspace(Workspace $workspace): void
    {
        // Compatibility no-op: workspace routes call this hook before listing/connecting.
        // Facebook pages are provisioned during OAuth callback.
    }

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
                'access_token' => encrypt($accessToken),
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
        $pagesById = self::fetchPagesForUser($graphVersion, $userAccessToken);
        if ($pagesById === []) {
            return 0;
        }

        $connected = 0;
        foreach ($pagesById as $page) {
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
                    'followers_count' => (int) ($page['followers_count'] ?? 0),
                    'fan_count' => (int) ($page['fan_count'] ?? 0),
                    'following_count' => self::pageFollowingCountFromLikesEdge($page),
                    'posts_count' => 0,
                    'is_connected' => true,
                ],
            );

            $igNode = $page['instagram_business_account'] ?? null;
            if (is_array($igNode) && isset($igNode['id'])) {
                self::upsertInstagramBusinessAccount(
                    $workspace,
                    $facebookUserId,
                    $pageId,
                    $pageToken,
                    $expiresAt,
                    $igNode
                );
            }

            $connected++;
        }

        return $connected;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function fetchPagesForUser(string $graphVersion, string $userAccessToken): array
    {
        $pagesById = [];

        // 1) Directly managed pages from /me/accounts
        $accountPages = self::fetchPagedGraphCollection(
            "https://graph.facebook.com/{$graphVersion}/me/accounts",
            [
                'fields' => self::FACEBOOK_PAGE_FIELDS,
                'access_token' => $userAccessToken,
                'limit' => 100,
            ]
        );
        foreach ($accountPages as $page) {
            if (! is_array($page)) {
                continue;
            }
            $pageId = (string) ($page['id'] ?? '');
            if ($pageId === '') {
                continue;
            }
            $pagesById[$pageId] = $page;
        }
        Log::info('Facebook /me/accounts response', [
            'count' => count($accountPages),
            'page_ids' => array_keys($pagesById),
        ]);

        // 2) Optional business portfolios and their owned pages
        $fetchBusinessPages = (bool) config('services.facebook.fetch_business_pages', true);
        $businesses = [];
        if ($fetchBusinessPages) {
            $businesses = self::fetchBusinessesSafely($graphVersion, $userAccessToken);
        }
        Log::info('Facebook /me/businesses response', [
            'count' => count($businesses),
            'businesses' => array_map(
                fn ($b) => ['id' => (is_array($b) ? ($b['id'] ?? null) : null), 'name' => (is_array($b) ? ($b['name'] ?? null) : null)],
                $businesses
            ),
        ]);
        foreach ($businesses as $business) {
            if (! is_array($business)) {
                continue;
            }
            $businessId = (string) ($business['id'] ?? '');
            if ($businessId === '') {
                continue;
            }

            $ownedPages = self::fetchPagedGraphCollection(
                "https://graph.facebook.com/{$graphVersion}/{$businessId}/owned_pages",
                [
                    'fields' => self::FACEBOOK_PAGE_FIELDS,
                    'access_token' => $userAccessToken,
                    'limit' => 100,
                ]
            );
            Log::info('Facebook owned_pages for business', [
                'business_id' => $businessId,
                'count' => count($ownedPages),
                'page_ids' => array_map(
                    fn ($p) => is_array($p) ? ($p['id'] ?? null) : null,
                    $ownedPages
                ),
            ]);

            foreach ($ownedPages as $ownedPage) {
                if (! is_array($ownedPage)) {
                    continue;
                }
                $pageId = (string) ($ownedPage['id'] ?? '');
                if ($pageId === '') {
                    continue;
                }

                if (! isset($pagesById[$pageId])) {
                    $pagesById[$pageId] = $ownedPage;
                    continue;
                }

                // Merge missing fields while preferring existing primary source values.
                $existing = $pagesById[$pageId];
                foreach (['name', 'access_token', 'fan_count', 'followers_count', 'following_count', 'picture'] as $field) {
                    if (! array_key_exists($field, $existing) || $existing[$field] === null || $existing[$field] === '') {
                        if (array_key_exists($field, $ownedPage)) {
                            $existing[$field] = $ownedPage[$field];
                        }
                    }
                }
                $pagesById[$pageId] = $existing;
            }
        }
        Log::info('Facebook total pages merged', [
            'total' => count($pagesById),
            'all_page_ids' => array_keys($pagesById),
        ]);

        return $pagesById;
    }

    /**
     * @return array<int, mixed>
     */
    private static function fetchBusinessesSafely(string $graphVersion, string $userAccessToken): array
    {
        try {
            $url = "https://graph.facebook.com/{$graphVersion}/me/businesses";
            $query = [
                'fields' => 'id,name',
                'access_token' => $userAccessToken,
                'limit' => 100,
            ];

            $items = [];
            $nextUrl = $url;
            $nextQuery = $query;

            while ($nextUrl !== null) {
                $response = Http::timeout(20)->get($nextUrl, $nextQuery);
                if (! $response->successful()) {
                    Log::warning('Facebook business pages unavailable', [
                        'reason' => 'Missing permission or dev mode limitation',
                        'status' => $response->status(),
                    ]);

                    return [];
                }

                $data = $response->json('data');
                if (is_array($data)) {
                    foreach ($data as $row) {
                        $items[] = $row;
                    }
                }

                $next = $response->json('paging.next');
                $nextUrl = is_string($next) && $next !== '' ? $next : null;
                $nextQuery = [];
            }

            return $items;
        } catch (Throwable $e) {
            Log::warning('Facebook business pages unavailable', [
                'reason' => 'Missing permission or dev mode limitation',
                'status' => null,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<int, mixed>
     */
    private static function fetchPagedGraphCollection(string $url, array $query): array
    {
        $items = [];
        $nextUrl = $url;
        $nextQuery = $query;

        while ($nextUrl !== null) {
            $response = Http::timeout(20)->get($nextUrl, $nextQuery);
            if (! $response->successful()) {
                Log::error('Facebook Graph API error', [
                    'url' => $nextUrl,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                break;
            }

            $data = $response->json('data');
            if (is_array($data)) {
                foreach ($data as $row) {
                    $items[] = $row;
                }
            }

            $next = $response->json('paging.next');
            $nextUrl = is_string($next) && $next !== '' ? $next : null;
            $nextQuery = [];
        }

        return $items;
    }

    public static function pageFollowingCountFromLikesEdge(mixed $page): int
    {
        if (! is_array($page)) {
            return 0;
        }
        $likes = $page['likes'] ?? null;
        if (! is_array($likes)) {
            return 0;
        }
        $summary = $likes['summary'] ?? null;
        if (! is_array($summary)) {
            return 0;
        }

        return (int) ($summary['total_count'] ?? 0);
    }

    private static function shortUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return strlen($url) <= 255 ? $url : null;
    }

    /**
     * @param  array<string, mixed>  $instagramBusinessAccount
     */
    public static function upsertInstagramBusinessAccount(
        Workspace $workspace,
        string $facebookUserId,
        string $facebookPageId,
        string $pageAccessToken,
        ?\Illuminate\Support\Carbon $expiresAt,
        array $instagramBusinessAccount
    ): void {
        $igId = (string) ($instagramBusinessAccount['id'] ?? '');
        if ($igId === '') {
            return;
        }

        $instagram = SocialPlatform::query()
            ->where('slug', 'instagram')
            ->where('is_active', true)
            ->first();

        if (! $instagram) {
            return;
        }

        SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'social_platform_id' => $instagram->id,
                'platform_page_id' => $igId,
            ],
            [
                'platform_user_id' => $facebookUserId,
                'account_name' => (string) ($instagramBusinessAccount['name'] ?? $instagramBusinessAccount['username'] ?? 'Instagram'),
                'account_handle' => (string) ($instagramBusinessAccount['username'] ?? $igId),
                'avatar' => self::shortUrl($instagramBusinessAccount['profile_picture_url'] ?? null),
                'access_token' => encrypt($pageAccessToken),
                'token_expires_at' => $expiresAt,
                'followers_count' => (int) ($instagramBusinessAccount['followers_count'] ?? 0),
                'following_count' => (int) ($instagramBusinessAccount['follows_count'] ?? 0),
                'posts_count' => (int) ($instagramBusinessAccount['media_count'] ?? 0),
                'fan_count' => 0,
                'is_connected' => true,
                'meta' => [
                    'facebook_page_id' => $facebookPageId,
                    'instagram_user_id' => $igId,
                ],
            ],
        );
    }
}
