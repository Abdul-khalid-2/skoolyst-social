<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebDisconnectSocialAccountRequest;
use App\Models\SocialAccount;
use App\Services\AccountListingService;
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

    public function refreshStats(SocialAccount $account): RedirectResponse
    {
        if ($account->platform?->slug !== 'facebook' || ! $account->platform_page_id) {
            return back()->with('error', __('Stats refresh is only available for Facebook pages.'));
        }

        try {
            $graphVersion = (string) config('services.facebook.graph_version', 'v24.0');
            $pageId = $account->platform_page_id;
            $pageToken = decrypt($account->access_token);

            $pageResponse = Http::timeout(15)->get(
                "https://graph.facebook.com/{$graphVersion}/{$pageId}",
                [
                    'fields' => 'fan_count,followers_count,posts.limit(0).summary(true)',
                    'access_token' => $pageToken,
                ]
            );

            $followingResponse = Http::timeout(15)->get(
                "https://graph.facebook.com/{$graphVersion}/{$pageId}/likes",
                [
                    'summary' => 'true',
                    'limit' => 0,
                    'access_token' => $pageToken,
                ]
            );

            $updates = [];
            if ($pageResponse->successful()) {
                $postsData = $pageResponse->json('posts');
                $postsCount = is_array($postsData)
                    ? (int) ($postsData['summary']['total_count'] ?? $account->posts_count)
                    : $account->posts_count;

                $updates['fan_count'] = (int) ($pageResponse->json('fan_count') ?? $account->fan_count);
                $updates['followers_count'] = (int) ($pageResponse->json('followers_count') ?? $account->followers_count);
                $updates['posts_count'] = $postsCount;
            }
            if ($followingResponse->successful()) {
                $updates['following_count'] = (int) ($followingResponse->json('summary.total_count') ?? $account->following_count);
            }

            if (! empty($updates)) {
                $account->update($updates);
            }

            return back()->with('success', sprintf(
                'Stats refreshed — %d likes · %d followers · %d following · %d posts',
                $updates['fan_count'] ?? $account->fan_count,
                $updates['followers_count'] ?? $account->followers_count,
                $updates['following_count'] ?? $account->following_count,
                $updates['posts_count'] ?? $account->posts_count,
            ));
        } catch (\Throwable $e) {
            Log::error('Facebook stats refresh failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('Could not refresh stats. Token may have expired.'));
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
