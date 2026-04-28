<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class SocialAccountController extends Controller
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [SocialAccount::class, $workspace]);

        $accounts = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_connected', true)
            ->with('platform')
            ->get()
            ->map(fn (SocialAccount $account) => $this->formatAccount($account))
            ->values();

        return response()->json(['accounts' => $accounts]);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('connect', [SocialAccount::class, $workspace]);

        $data = $request->validate([
            'platform_id' => ['required', 'integer', 'exists:social_platforms,id'],
            'account_name' => ['required', 'string', 'max:255'],
            'page_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['required', 'string', 'min:10'],
            'token_expires_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['connected', 'disconnected', 'expired'])],
        ]);

        $status = (string) ($data['status'] ?? 'connected');
        $account = SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'social_platform_id' => (int) $data['platform_id'],
                'platform_page_id' => $data['page_id'] ?? null,
            ],
            [
                'account_name' => $data['account_name'],
                'access_token' => encrypt((string) $data['access_token']),
                'token_expires_at' => $data['token_expires_at'] ?? null,
                'is_connected' => $status === 'connected',
            ],
        );
        $account->load('platform');

        return response()->json(['account' => $this->formatAccount($account)], 201);
    }

    public function update(Request $request, Workspace $workspace, SocialAccount $account): JsonResponse
    {
        $this->assertAccountInWorkspace($workspace, $account);
        $this->authorize('connect', [SocialAccount::class, $workspace]);

        $data = $request->validate([
            'access_token' => ['required', 'string', 'min:10'],
            'token_expires_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(['connected', 'disconnected', 'expired'])],
        ]);

        $status = (string) ($data['status'] ?? 'connected');
        $account->update([
            'access_token' => encrypt((string) $data['access_token']),
            'token_expires_at' => $data['token_expires_at'] ?? null,
            'is_connected' => $status === 'connected',
        ]);
        $account->load('platform');

        return response()->json(['account' => $this->formatAccount($account)]);
    }

    public function destroy(Request $request, Workspace $workspace, SocialAccount $account): JsonResponse
    {
        $this->assertAccountInWorkspace($workspace, $account);
        $this->authorize('disconnect', $account);

        $account->delete();

        return response()->json(['message' => 'Social account disconnected successfully.']);
    }

    public function socialPlatforms(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [SocialAccount::class, $workspace]);

        $accounts = $workspace->socialAccounts()
            ->with('platform')
            ->get()
            ->keyBy('social_platform_id');

        $rows = SocialPlatform::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function (SocialPlatform $platform) use ($accounts) {
                /** @var SocialAccount|null $account */
                $account = $accounts->get($platform->id);
                $status = 'disconnected';
                if ($account?->is_connected) {
                    $status = ($account->token_expires_at && $account->token_expires_at->isPast()) ? 'expired' : 'connected';
                }

                return [
                    'id' => $platform->id,
                    'name' => $platform->name,
                    'slug' => $platform->slug,
                    'icon' => $platform->icon,
                    'color' => $platform->color,
                    'connection_options' => $platform->connection_options ?? [],
                    'connected' => $account?->is_connected ?? false,
                    'selected' => $account !== null,
                    'status' => $status,
                    'account_id' => $account?->id,
                    'account_name' => $account?->account_name,
                    'account_handle' => $account?->account_handle,
                    'followers_count' => (int) ($account?->followers_count ?? 0),
                    'token_expires_at' => $account?->token_expires_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json(['platforms' => $rows]);
    }

    public function updateFocusedPlatforms(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('connect', [SocialAccount::class, $workspace]);

        $data = $request->validate([
            'platform_slugs' => ['required', 'array', 'min:1'],
            'platform_slugs.*' => ['required', 'string'],
        ]);

        $slugs = collect($data['platform_slugs'])
            ->filter(fn ($slug) => is_string($slug) && trim($slug) !== '')
            ->map(fn ($slug) => trim((string) $slug))
            ->unique()
            ->values();

        $platforms = SocialPlatform::query()
            ->where('is_active', true)
            ->whereIn('slug', $slugs->all())
            ->get()
            ->keyBy('slug');

        if ($platforms->count() !== $slugs->count()) {
            return response()->json(['message' => 'One or more platform slugs are invalid.'], 422);
        }

        $selectedPlatformIds = $platforms->pluck('id')->all();
        $existing = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->get()
            ->keyBy('social_platform_id');

        foreach ($platforms as $platform) {
            SocialAccount::query()->firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'social_platform_id' => $platform->id,
                ],
                [
                    'account_name' => $platform->name.' (not connected)',
                    'access_token' => encrypt('selection-pending'),
                    'is_connected' => false,
                ],
            );
        }

        $removable = $existing
            ->filter(fn (SocialAccount $account) => ! in_array($account->social_platform_id, $selectedPlatformIds, true) && ! $account->is_connected);
        foreach ($removable as $account) {
            $account->delete();
        }

        return response()->json(['message' => 'Focused social channels updated.']);
    }

    public function connectPlatform(Request $request, Workspace $workspace, string $platformSlug): JsonResponse
    {
        $this->authorize('connect', [SocialAccount::class, $workspace]);
        $user = $request->user();
        $platform = SocialPlatform::query()->where('slug', $platformSlug)->where('is_active', true)->first();
        if (! $platform) {
            return response()->json(['message' => 'Social platform not found.'], 404);
        }

        if ($platform->slug !== 'facebook') {
            return response()->json([
                'message' => __('Connect flow for :platform is not enabled yet.', ['platform' => $platform->name]),
            ], 422);
        }

        $data = $request->validate([
            'user_token' => ['nullable', 'string'],
            'page_id' => ['nullable', 'string'],
        ]);

        $userToken = (string) ($data['user_token'] ?? $user?->facebook_access_token ?? '');
        if ($userToken === '') {
            return response()->json(['message' => 'Facebook user token is missing. Login with Facebook first.'], 422);
        }

        $graphVersion = (string) config('services.facebook.graph_version', 'v24.0');
        $res = Http::timeout(20)->get("https://graph.facebook.com/{$graphVersion}/me/accounts", [
            'fields' => 'id,name,access_token,picture',
            'access_token' => $userToken,
        ]);
        if (! $res->successful()) {
            return response()->json(['message' => 'Unable to fetch Facebook pages.'], 422);
        }

        $pages = $res->json('data');
        if (! is_array($pages)) {
            $pages = [];
        }

        $selectedPage = null;
        $preferredPageId = (string) ($data['page_id'] ?? '');
        foreach ($pages as $page) {
            if (! is_array($page) || ! isset($page['id'], $page['access_token'])) {
                continue;
            }
            if ($preferredPageId !== '' && (string) $page['id'] !== $preferredPageId) {
                continue;
            }
            $selectedPage = $page;
            break;
        }
        if (! is_array($selectedPage) && $preferredPageId === '' && count($pages) > 0) {
            /** @var array<string,mixed> $first */
            $first = $pages[0];
            $selectedPage = $first;
        }

        if (! is_array($selectedPage) && $preferredPageId !== '') {
            $direct = Http::timeout(20)->get("https://graph.facebook.com/{$graphVersion}/{$preferredPageId}", [
                'fields' => 'id,name,access_token,picture',
                'access_token' => $userToken,
            ]);
            if (! $direct->successful()) {
                return response()->json(['message' => 'Unable to resolve the requested Facebook Page.'], 422);
            }

            $id = (string) ($direct->json('id') ?? '');
            $pageToken = (string) ($direct->json('access_token') ?? '');
            if ($id === '' || $pageToken === '') {
                return response()->json(['message' => 'Facebook page access token is missing.'], 422);
            }

            $selectedPage = [
                'id' => $id,
                'name' => $direct->json('name'),
                'access_token' => $pageToken,
                'picture' => $direct->json('picture'),
            ];
        } elseif (! is_array($selectedPage)) {
            return response()->json(['message' => 'No Facebook pages found for this account.'], 422);
        }

        $pageToken = (string) ($selectedPage['access_token'] ?? '');
        $pageId = (string) ($selectedPage['id'] ?? '');
        if ($pageToken === '' || $pageId === '') {
            return response()->json(['message' => 'Facebook page access token is missing.'], 422);
        }

        $account = SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'social_platform_id' => $platform->id,
                'platform_page_id' => $pageId,
            ],
            [
                'platform_user_id' => (string) ($user?->facebook_id ?? ''),
                'account_name' => (string) ($selectedPage['name'] ?? 'Facebook Page'),
                'account_handle' => $pageId,
                'avatar' => $this->shortUrl($selectedPage['picture']['data']['url'] ?? null),
                'access_token' => encrypt($pageToken),
                'token_expires_at' => $user?->facebook_token_expires_at,
                'is_connected' => true,
            ],
        );
        $account->load('platform');

        return response()->json([
            'message' => 'Facebook connected successfully.',
            'account' => $this->formatAccount($account),
        ]);
    }

    public function disconnectPlatform(Request $request, Workspace $workspace, string $platformSlug): JsonResponse
    {
        $this->authorize('disconnect', [SocialAccount::class, $workspace]);
        $platform = SocialPlatform::query()->where('slug', $platformSlug)->where('is_active', true)->first();
        if (! $platform) {
            return response()->json(['message' => 'Social platform not found.'], 404);
        }

        SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('social_platform_id', $platform->id)
            ->delete();

        return response()->json(['message' => "{$platform->name} disconnected successfully."]);
    }

    private function shortUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return strlen($url) <= 255 ? $url : null;
    }

    private function assertAccountInWorkspace(Workspace $workspace, SocialAccount $account): void
    {
        if ($account->workspace_id !== $workspace->id) {
            abort(404);
        }
    }

    private function formatAccount(SocialAccount $account): array
    {
        $status = 'disconnected';
        if ($account->is_connected) {
            $status = ($account->token_expires_at && $account->token_expires_at->isPast()) ? 'expired' : 'connected';
        }

        return [
            'id' => $account->id,
            'workspace_id' => $account->workspace_id,
            'platform_id' => $account->social_platform_id,
            'platform_slug' => $account->platform?->slug,
            'platform_name' => $account->platform?->name,
            'account_name' => $account->account_name,
            'page_id' => $account->platform_page_id,
            'status' => $status,
            'token_expires_at' => $account->token_expires_at?->toIso8601String(),
            'account_handle' => $account->account_handle,
            'followers_count' => (int) $account->followers_count,
        ];
    }
}
