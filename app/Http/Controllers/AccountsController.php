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
            $graphVersion = (string) config('services.facebook.graph_version', 'v24.0');
            $token = decrypt($account->access_token);
            $pageId = $account->platform_page_id ?? $account->platform_user_id;

            $updates = [];

            if ($slug === 'facebook' && $pageId) {
                $res = Http::timeout(15)->get(
                    "https://graph.facebook.com/{$graphVersion}/{$pageId}",
                    ['fields' => 'fan_count,followers_count,posts.limit(0).summary(true)', 'access_token' => $token]
                );
                if ($res->successful()) {
                    $postsData = $res->json('posts');
                    $updates = [
                        'fan_count'       => (int) ($res->json('fan_count') ?? $account->fan_count),
                        'followers_count' => (int) ($res->json('followers_count') ?? $account->followers_count),
                        'posts_count'     => is_array($postsData)
                            ? (int) ($postsData['summary']['total_count'] ?? $account->posts_count)
                            : $account->posts_count,
                    ];
                }
                $followRes = Http::timeout(10)->get(
                    "https://graph.facebook.com/{$graphVersion}/{$pageId}/likes",
                    ['summary' => 'true', 'limit' => '0', 'access_token' => $token]
                );
                if ($followRes->successful()) {
                    $updates['following_count'] = (int) ($followRes->json('summary.total_count') ?? $account->following_count);
                }

            } elseif ($slug === 'instagram' && $pageId) {
                $res = Http::timeout(15)->get(
                    "https://graph.facebook.com/{$graphVersion}/{$pageId}",
                    ['fields' => 'followers_count,follows_count,media_count', 'access_token' => $token]
                );
                if ($res->successful()) {
                    $updates = [
                        'followers_count' => (int) ($res->json('followers_count') ?? $account->followers_count),
                        'following_count' => (int) ($res->json('follows_count') ?? $account->following_count),
                        'posts_count'     => (int) ($res->json('media_count') ?? $account->posts_count),
                    ];
                }
            }

            if (! empty($updates)) {
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
