<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use App\Services\SocialAccountProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $workspaces = $user->workspaces()->get([
            'workspaces.id',
            'workspaces.name',
            'workspaces.slug',
            'workspaces.plan',
            'workspaces.is_active',
        ]);

        return response()->json(['workspaces' => $workspaces]);
    }

    public function publishingAccounts(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [SocialAccount::class, $workspace]);

        SocialAccountProvisioner::ensureForWorkspace($workspace);

        $rows = $workspace->socialAccounts()
            ->where('is_connected', true)
            ->where('access_token', '!=', 'oauth-pending')
            ->with('platform')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'platform_slug' => $a->platform?->slug,
                'account_name' => $a->account_name,
            ])
            ->values();

        return response()->json(['accounts' => $rows]);
    }

    public function socialPlatforms(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [SocialAccount::class, $workspace]);

        SocialAccountProvisioner::ensureForWorkspace($workspace);

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

                return [
                    'id' => $platform->id,
                    'name' => $platform->name,
                    'slug' => $platform->slug,
                    'color' => $platform->color,
                    'character_limit' => $platform->character_limit,
                    'supports_scheduling' => (bool) $platform->supports_scheduling,
                    'supports_media' => (bool) $platform->supports_media,
                    'connected' => (bool) ($account?->is_connected ?? false),
                    'account_name' => $account?->account_name,
                    'account_handle' => $account?->account_handle,
                    'followers_count' => (int) ($account?->followers_count ?? 0),
                    'following_count' => (int) ($account?->following_count ?? 0),
                    'posts_count' => (int) ($account?->posts_count ?? 0),
                ];
            })
            ->values();

        return response()->json(['platforms' => $rows]);
    }

    public function connectPlatform(Request $request, Workspace $workspace, SocialPlatform $platform): JsonResponse
    {
        $this->authorize('connect', [SocialAccount::class, $workspace]);
        $user = $request->user();

        SocialAccountProvisioner::ensureForWorkspace($workspace);

        if ($platform->slug !== 'facebook') {
            return response()->json([
                'message' => __('OAuth connection for :platform is not enabled yet.', ['platform' => $platform->name]),
            ], 422);
        }

        if (! $user->facebook_id || ! $user->facebook_access_token) {
            return response()->json([
                'message' => __('Please login with Facebook first to connect Facebook publishing.'),
            ], 422);
        }

        $account = SocialAccount::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'social_platform_id' => $platform->id,
            ],
            [
                'platform_user_id' => (string) $user->facebook_id,
                'account_name' => $user->name ?: 'Facebook',
                'account_handle' => $user->email,
                'avatar' => $user->avatar,
                'access_token' => (string) $user->facebook_access_token,
                'token_expires_at' => $user->facebook_token_expires_at,
                'is_connected' => true,
            ],
        );

        return response()->json([
            'message' => __(':platform connected successfully.', ['platform' => $platform->name]),
            'account' => [
                'id' => $account->id,
                'account_name' => $account->account_name,
                'account_handle' => $account->account_handle,
                'followers_count' => (int) $account->followers_count,
                'connected' => (bool) $account->is_connected,
            ],
        ]);
    }

    public function disconnectPlatform(Request $request, Workspace $workspace, SocialPlatform $platform): JsonResponse
    {
        $this->authorize('disconnect', [SocialAccount::class, $workspace]);

        $account = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('social_platform_id', $platform->id)
            ->first();

        if ($account) {
            $account->update([
                'is_connected' => false,
                'access_token' => 'oauth-pending',
                'account_handle' => null,
                'followers_count' => 0,
                'following_count' => 0,
                'posts_count' => 0,
            ]);
        }

        return response()->json([
            'message' => __(':platform disconnected successfully.', ['platform' => $platform->name]),
        ]);
    }

}
