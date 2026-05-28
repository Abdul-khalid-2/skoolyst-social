<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebDisconnectSocialAccountRequest;
use App\Models\SocialAccount;
use App\Services\AccountListingService;
use App\Services\SocialAccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AccountsController extends Controller
{
    public function __construct(
        private readonly AccountListingService $accountListing,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->accountListing->getIndexData($request->user());

        return view('accounts.index', array_merge($data, [
            'title' => 'Social Accounts',
            'description' => 'Connect and manage your social media accounts.',
        ]));
    }

    public function toggleActive(
        Request $request,
        SocialAccount $account,
    ): RedirectResponse {
        $data = $this->accountListing->getIndexData($request->user());
        $workspace = $data['workspace'] ?? null;

        if ($workspace === null || $account->workspace_id !== $workspace->id) {
            abort(403);
        }

        $newState = ! $account->is_active;
        $account->update(['is_active' => $newState]);

        $label = $newState ? 'activated' : 'paused';

        return redirect()
            ->route('accounts')
            ->with('success', __(':name :label. It will :action receive new posts.', [
                'name'   => $account->account_name,
                'label'  => $label,
                'action' => $newState ? 'now' : 'no longer',
            ]));
    }

    public function refreshStats(
        Request $request,
        SocialAccount $account,
    ): RedirectResponse {
        $data = $this->accountListing->getIndexData($request->user());
        $workspace = $data['workspace'] ?? null;

        if ($workspace === null || $account->workspace_id !== $workspace->id) {
            abort(403);
        }

        $slug = $account->platform?->slug;

        try {
            $token = decrypt($account->access_token);
            $pageId = $account->platform_page_id ?? $account->platform_user_id;

            $updates = match ($slug) {
                'facebook'  => $this->refreshFacebookStats((string) $pageId, $token),
                'instagram' => $this->refreshInstagramStats((string) $pageId, $token),
                'linkedin'  => $this->refreshLinkedInStats($account, $token),
                default     => [],
            };

            if (! empty($updates)) {
                $updates['stats_synced_at'] = now();
                $account->update($updates);

                return redirect()->route('accounts')->with('success', __('Stats refreshed for :name.', ['name' => $account->account_name]));
            }

            return redirect()->route('accounts')->with('info', __('No stats updated for :name.', ['name' => $account->account_name]));

        } catch (\Throwable $e) {
            Log::error('refreshStats failed', [
                'account_id' => $account->id,
                'slug'       => $slug,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->route('accounts')->with('error', __('Could not refresh stats: :msg', ['msg' => $e->getMessage()]));
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function refreshFacebookStats(string $pageId, string $pageToken): array
    {
        if ($pageId === '') {
            return [];
        }

        $graphVersion = (string) config('services.facebook.graph_version', 'v24.0');
        $updates = [];

        $res = Http::timeout(15)->get(
            "https://graph.facebook.com/{$graphVersion}/{$pageId}",
            ['fields' => 'followers_count', 'access_token' => $pageToken]
        );
        if ($res->successful()) {
            $updates['followers_count'] = is_numeric($res->json('followers_count'))
                ? (int) $res->json('followers_count')
                : null;
        }

        $postsCount = SocialAccountProvisioner::fetchFacebookPagePostsCount($graphVersion, $pageId, $pageToken);
        if ($postsCount !== null) {
            $updates['posts_count'] = $postsCount;
        }

        // Facebook Pages have no "following" metric; keep null so the UI does not show it.
        $updates['following_count'] = null;

        return $updates;
    }

    /**
     * @return array<string, int|null>
     */
    private function refreshInstagramStats(string $igUserId, string $pageToken): array
    {
        if ($igUserId === '') {
            return [];
        }

        $graphVersion = (string) config('services.facebook.graph_version', 'v24.0');
        $res = Http::timeout(15)->get(
            "https://graph.facebook.com/{$graphVersion}/{$igUserId}",
            ['fields' => 'followers_count,follows_count,media_count', 'access_token' => $pageToken]
        );

        if (! $res->successful()) {
            return [];
        }

        return [
            'followers_count' => is_numeric($res->json('followers_count')) ? (int) $res->json('followers_count') : null,
            'following_count' => is_numeric($res->json('follows_count')) ? (int) $res->json('follows_count') : null,
            'posts_count'     => is_numeric($res->json('media_count')) ? (int) $res->json('media_count') : null,
        ];
    }

    /**
     * @return array<string, int|null>
     */
    private function refreshLinkedInStats(SocialAccount $account, string $accessToken): array
    {
        $accountType = (string) ($account->meta['li_account_type'] ?? 'person');

        if ($accountType === 'organization') {
            $orgId = (string) ($account->platform_page_id ?? '');
            if ($orgId === '') {
                return [];
            }

            $followers = $this->fetchLinkedInOrgFollowerCount($orgId, $accessToken);
            $posts = SocialAccountProvisioner::fetchLinkedInOrganizationPostsCount($orgId, $accessToken);

            $updates = [];
            if ($followers !== null) {
                $updates['followers_count'] = $followers;
            }
            if ($posts !== null) {
                $updates['posts_count'] = $posts;
            }

            // Always stamp something so the UI knows we tried, even if both calls
            // returned null — record nulls explicitly so the row stops being "never synced".
            if (empty($updates)) {
                $updates = ['followers_count' => null, 'posts_count' => null];
            }

            return $updates;
        }

        $userId = (string) ($account->platform_user_id ?? '');
        if ($userId === '') {
            return [];
        }

        $stats = SocialAccountProvisioner::fetchLinkedInPersonStats($accessToken, $userId);

        return [
            'followers_count' => $stats['followers'] ?? null,
            'following_count' => $stats['following'] ?? null,
            'posts_count'     => $stats['posts'] ?? null,
        ];
    }

    private function fetchLinkedInOrgFollowerCount(string $organizationId, string $accessToken): ?int
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'LinkedIn-Version' => '202406',
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->get('https://api.linkedin.com/v2/organizationalEntityFollowerStatistics', [
                    'q' => 'organizationalEntity',
                    'organizationalEntity' => 'urn:li:organization:'.$organizationId,
                ]);

            if (! $response->successful()) {
                Log::warning('LinkedIn org follower stats unavailable', [
                    'organization_id' => $organizationId,
                    'status' => $response->status(),
                ]);

                return null;
            }

            // Sum followerCount across all element rows (one per time-bucket).
            $elements = $response->json('elements');
            if (! is_array($elements) || empty($elements)) {
                return null;
            }
            $latest = $elements[0] ?? null;
            if (is_array($latest)) {
                $byAssociationType = $latest['followerCountsByAssociationType'] ?? null;
                if (is_array($byAssociationType)) {
                    $total = 0;
                    foreach ($byAssociationType as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $counts = $row['followerCounts'] ?? null;
                        if (is_array($counts)) {
                            $total += (int) ($counts['organicFollowerCount'] ?? 0);
                            $total += (int) ($counts['paidFollowerCount'] ?? 0);
                        }
                    }

                    return $total;
                }
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('LinkedIn org follower stats fetch failed', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function destroyConnection(
        WebDisconnectSocialAccountRequest $request,
        SocialAccount $account,
    ): RedirectResponse {
        $this->accountListing->deleteAccount($request->user(), $account);

        return redirect()
            ->route('accounts')
            ->with('success', __('Connection removed.'));
    }
}
