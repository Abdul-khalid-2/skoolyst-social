<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SocialAccountProvisioner
{
    private const FACEBOOK_PAGE_FIELDS = 'id,name,access_token,fan_count,followers_count,posts.limit(0).summary(true),picture,instagram_business_account{id,username,profile_picture_url,name,followers_count,follows_count,media_count}';

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

            // Distinguish "missing from API response" (null = unavailable) from
            // a confirmed-zero count. The accounts UI renders null as an em-dash.
            $followersCount = array_key_exists('followers_count', $page) ? (int) $page['followers_count'] : null;
            $fanCount       = array_key_exists('fan_count', $page) ? (int) $page['fan_count'] : null;
            // Facebook Pages do not have a "following" metric; do not fetch /likes for display.
            $followingCount = null;
            $postsCount     = self::fetchFacebookPagePostsCount($graphVersion, $pageId, $pageToken)
                ?? self::fetchPagePostsCount($page);

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
                    'followers_count' => $followersCount,
                    'fan_count' => $fanCount,
                    'following_count' => $followingCount,
                    'posts_count' => $postsCount,
                    'stats_synced_at' => now(),
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

    /**
     * Fetch post count via the dedicated /posts edge. Embedded `posts.limit(0).summary(true)`
     * on the page object often omits total_count; this call is the reliable source.
     */
    public static function fetchFacebookPagePostsCount(string $graphVersion, string $pageId, string $pageAccessToken): ?int
    {
        if ($pageId === '' || $pageAccessToken === '') {
            return null;
        }

        try {
            $response = Http::timeout(15)->get(
                "https://graph.facebook.com/{$graphVersion}/{$pageId}/posts",
                [
                    'summary' => 'total_count',
                    'limit' => 0,
                    'access_token' => $pageAccessToken,
                ]
            );

            if (! $response->successful()) {
                Log::warning('Facebook page posts count unavailable', [
                    'page_id' => $pageId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $total = $response->json('summary.total_count');

            return is_numeric($total) ? (int) $total : null;
        } catch (Throwable $e) {
            Log::warning('Facebook page posts count fetch failed', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Fallback: read post count from an embedded posts edge on a page payload.
     *
     * @param  array<string, mixed>  $page
     */
    public static function fetchPagePostsCount(array $page): ?int
    {
        $posts = $page['posts'] ?? null;
        if (! is_array($posts)) {
            return null;
        }

        $summary = $posts['summary'] ?? null;
        if (! is_array($summary) || ! array_key_exists('total_count', $summary)) {
            return null;
        }

        return (int) $summary['total_count'];
    }

    public static function fetchPageFollowingCount(string $graphVersion, string $pageId, string $pageAccessToken): ?int
    {
        try {
            $response = Http::timeout(15)->get(
                "https://graph.facebook.com/{$graphVersion}/{$pageId}/likes",
                [
                    'summary' => 'true',
                    'limit' => 0,
                    'access_token' => $pageAccessToken,
                ]
            );

            if (! $response->successful()) {
                Log::warning('Facebook page following count unavailable', [
                    'page_id' => $pageId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $total = $response->json('summary.total_count');

            return is_numeric($total) ? (int) $total : null;
        } catch (Throwable $e) {
            Log::warning('Facebook page following count fetch failed', [
                'page_id' => $pageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** @deprecated Following count is now fetched via fetchPageFollowingCount() using the page access token. */
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

        // Distinguish "API didn't return this field" (null) from a real zero.
        $followers = array_key_exists('followers_count', $instagramBusinessAccount)
            ? (int) $instagramBusinessAccount['followers_count']
            : null;
        $following = array_key_exists('follows_count', $instagramBusinessAccount)
            ? (int) $instagramBusinessAccount['follows_count']
            : null;
        $posts = array_key_exists('media_count', $instagramBusinessAccount)
            ? (int) $instagramBusinessAccount['media_count']
            : null;

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
                'followers_count' => $followers,
                'following_count' => $following,
                'posts_count' => $posts,
                // Instagram has no equivalent of Facebook's fan_count ("likes").
                'fan_count' => null,
                'stats_synced_at' => now(),
                'is_connected' => true,
                'meta' => [
                    'facebook_page_id' => $facebookPageId,
                    'instagram_user_id' => $igId,
                ],
            ],
        );
    }

    /**
     * Connect/refresh a LinkedIn personal profile. The four count parameters are
     * nullable: `null` means "could not be determined" (e.g. API doesn't expose
     * the metric under our current scopes) and is rendered as `—` in the UI;
     * an explicit `0` means "API confirmed zero".
     */
    public static function connectLinkedInForWorkspace(
        Workspace $workspace,
        string $linkedInUserId,
        string $accessToken,
        ?string $refreshToken = null,
        ?\Illuminate\Support\Carbon $expiresAt = null,
        ?string $displayName = null,
        ?string $avatarUrl = null,
        ?string $vanityName = null,
        ?string $profileEmail = null,
        ?int $followersCount = null,
        ?int $followingCount = null,
        ?int $postsCount = null
    ): SocialAccount {
        $linkedin = SocialPlatform::query()
            ->where('slug', 'linkedin')
            ->where('is_active', true)
            ->first();

        if (! $linkedin) {
            throw new \RuntimeException('LinkedIn platform not found.');
        }

        $memberUrn = 'urn:li:person:'.$linkedInUserId;

        return SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'social_platform_id' => $linkedin->id,
                'platform_user_id' => $linkedInUserId,
            ],
            [
                'platform_page_id' => null,
                'account_name' => $displayName ?: 'LinkedIn Profile',
                'account_handle' => self::linkedInPersonHandleForStorage($vanityName, $linkedInUserId),
                'avatar' => self::shortUrl($avatarUrl),
                'access_token' => encrypt($accessToken),
                'refresh_token' => $refreshToken ? encrypt($refreshToken) : null,
                'token_expires_at' => $expiresAt,
                'followers_count' => $followersCount,
                'fan_count' => null,
                'following_count' => $followingCount,
                'posts_count' => $postsCount,
                // Always stamp sync time so the UI does not treat a connected profile as "never synced"
                // when LinkedIn simply does not expose follower/post counts under our scopes.
                'stats_synced_at' => now(),
                'is_connected' => true,
                'meta' => [
                    'li_member_id' => $memberUrn,
                    'li_account_type' => 'person',
                    'li_vanity_name' => $vanityName,
                    'li_profile_email' => self::normalizeLinkedInProfileEmail($profileEmail),
                ],
            ],
        );
    }

    /**
     * Subtitle handle shown on the Accounts page (vanity slug or LinkedIn member id).
     */
    public static function linkedInPersonHandleForStorage(?string $vanityName, string $linkedInUserId): string
    {
        $vanity = trim((string) $vanityName);

        return ($vanity !== '' && $vanity !== $linkedInUserId) ? $vanity : $linkedInUserId;
    }

    public static function fetchLinkedInVanityName(string $accessToken): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'LinkedIn-Version' => '202406',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->get('https://api.linkedin.com/v2/me', [
                    'projection' => '(id,vanityName)',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $vanity = $response->json('vanityName');

            return is_string($vanity) && $vanity !== '' ? $vanity : null;
        } catch (Throwable $e) {
            Log::debug('LinkedIn vanityName fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private static function linkedInRestHeaders(string $accessToken): array
    {
        return [
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => (string) config('services.linkedin.rest_version', '202504'),
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
    }

    public static function normalizeLinkedInProfileEmail(?string $email): ?string
    {
        $email = Str::lower(trim((string) $email));
        if ($email === '' || str_contains($email, '@users.linkedin.local')) {
            return null;
        }

        return $email;
    }

    /**
     * Connect/refresh a LinkedIn organization (company page). `followersCount`
     * is typically populated from the `followingInfo.followerCount` field of
     * the `organizationAcls` projection. Posts and following are not generally
     * available without `r_organization_social` scope, so we leave them null
     * (the UI hides "following" for orgs and renders `—` for posts).
     */
    public static function connectLinkedInOrganizationForWorkspace(
        Workspace $workspace,
        string $linkedInUserId,
        string $organizationId,
        string $accessToken,
        ?string $refreshToken = null,
        ?\Illuminate\Support\Carbon $expiresAt = null,
        ?string $organizationName = null,
        ?string $avatarUrl = null,
        ?int $followersCount = null,
        ?int $postsCount = null
    ): SocialAccount {
        $linkedin = SocialPlatform::query()
            ->where('slug', 'linkedin')
            ->where('is_active', true)
            ->first();

        if (! $linkedin) {
            throw new \RuntimeException('LinkedIn platform not found.');
        }

        $organizationUrn = 'urn:li:organization:'.$organizationId;

        $hasAnyStat = $followersCount !== null || $postsCount !== null;

        return SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'social_platform_id' => $linkedin->id,
                'platform_page_id' => $organizationId,
            ],
            [
                'platform_user_id' => $linkedInUserId,
                'account_name' => $organizationName ?: 'LinkedIn Organization',
                'account_handle' => $organizationId,
                'avatar' => self::shortUrl($avatarUrl),
                'access_token' => encrypt($accessToken),
                'refresh_token' => $refreshToken ? encrypt($refreshToken) : null,
                'token_expires_at' => $expiresAt,
                'followers_count' => $followersCount,
                'fan_count' => null,
                // Companies don't have a "following" count in the IG/personal sense.
                'following_count' => null,
                'posts_count' => $postsCount,
                'stats_synced_at' => $hasAnyStat ? now() : null,
                'is_connected' => true,
                'meta' => [
                    'li_member_id' => $organizationUrn,
                    'li_account_type' => 'organization',
                    'li_organization_id' => $organizationId,
                ],
            ],
        );
    }

    /**
     * Best-effort fetch of follower/following/posts counts for a LinkedIn
     * personal profile. Most installations only request OpenID-tier scopes
     * (`openid profile email w_member_social`), so the calls below typically
     * fail with 403; those return `null` rather than 0 so the UI can render
     * an em-dash and prompt the user to reconnect with broader scopes.
     *
     * @return array{followers: ?int, following: ?int, posts: ?int}
     */
    public static function fetchLinkedInPersonStats(string $accessToken, string $linkedInUserId): array
    {
        $personUrn = 'urn:li:person:'.$linkedInUserId;
        $headers = self::linkedInRestHeaders($accessToken);

        $followers = self::fetchLinkedInMemberFollowersCount($accessToken);
        $posts = self::fetchLinkedInAuthorPostsCount($accessToken, $personUrn, $headers);

        return [
            'followers' => $followers,
            'following' => null,
            'posts' => $posts,
        ];
    }

    public static function fetchLinkedInMemberFollowersCount(string $accessToken): ?int
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(self::linkedInRestHeaders($accessToken))
                ->get('https://api.linkedin.com/rest/memberFollowersCount', ['q' => 'me']);

            if (! $response->successful()) {
                Log::warning('LinkedIn memberFollowersCount unavailable', [
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return null;
            }

            $elements = $response->json('elements');
            if (! is_array($elements) || $elements === []) {
                return null;
            }

            $count = $elements[0]['memberFollowersCount'] ?? null;

            return is_numeric($count) ? (int) $count : null;
        } catch (Throwable $e) {
            Log::warning('LinkedIn memberFollowersCount fetch failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function fetchLinkedInAuthorPostsCount(string $accessToken, string $authorUrn, ?array $headers = null): ?int
    {
        $headers ??= self::linkedInRestHeaders($accessToken);

        try {
            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->get('https://api.linkedin.com/rest/posts', [
                    'q' => 'author',
                    'author' => $authorUrn,
                    'count' => 1,
                    'start' => 0,
                ]);

            if ($response->successful()) {
                $total = $response->json('paging.total');
                if (is_numeric($total)) {
                    return (int) $total;
                }
            } else {
                Log::warning('LinkedIn rest/posts count unavailable', [
                    'author' => $authorUrn,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('LinkedIn rest/posts fetch failed', [
                'author' => $authorUrn,
                'error' => $e->getMessage(),
            ]);
        }

        return self::tryFetchLinkedInPostsCount($authorUrn, $headers);
    }

    /**
     * Best-effort post count for a LinkedIn organization (company page). Requires
     * `r_organization_social` (typically unavailable to standard apps), so this
     * returns null on permission denial.
     */
    public static function fetchLinkedInOrganizationPostsCount(string $organizationId, string $accessToken): ?int
    {
        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'LinkedIn-Version' => '202406',
            'X-Restli-Protocol-Version' => '2.0.0',
        ];

        return self::fetchLinkedInAuthorPostsCount(
            $accessToken,
            'urn:li:organization:'.$organizationId,
            $headers,
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private static function tryFetchLinkedInPostsCount(string $authorUrn, array $headers): ?int
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders($headers)
                ->get('https://api.linkedin.com/v2/ugcPosts', [
                    'q' => 'authors',
                    'authors' => 'List('.$authorUrn.')',
                    'count' => 1,
                    'start' => 0,
                ]);

            if (! $response->successful()) {
                Log::warning('LinkedIn ugcPosts count unavailable', [
                    'author' => $authorUrn,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $total = $response->json('paging.total');

            return is_numeric($total) ? (int) $total : null;
        } catch (Throwable $e) {
            Log::warning('LinkedIn ugcPosts fetch failed', [
                'author' => $authorUrn,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, string>  $headers
     */
    private static function tryFetchLinkedInCount(string $url, array $query, array $headers, string $jsonKey): ?int
    {
        try {
            $response = Http::timeout(15)->withHeaders($headers)->get($url, $query);
            if (! $response->successful()) {
                Log::warning('LinkedIn count fetch unavailable', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $value = $response->json($jsonKey);

            return is_numeric($value) ? (int) $value : null;
        } catch (Throwable $e) {
            Log::warning('LinkedIn count fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
