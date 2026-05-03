<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialAccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class FacebookAuthController extends Controller
{
    public function redirectToFacebook(): RedirectResponse
    {
        $version = (string) config('services.facebook.graph_version', 'v24.0');

        return Socialite::driver('facebook')
        ->usingGraphVersion($version)
        ->scopes([
            'email',
            'public_profile',
            'pages_show_list',
            'pages_read_engagement',
            'pages_manage_posts',
            'pages_manage_metadata',
            'instagram_basic',
            'instagram_content_publish',
            'instagram_manage_insights',
            'instagram_manage_comments',
            'business_management',
        ])
        ->with(['config_id' => env('FACEBOOK_LOGIN_CONFIG_ID')])
        ->stateless()
        ->redirect();
    }

    public function handleCallback(Request $request): RedirectResponse
    {
        $toError = fn (string $code) => redirect()->route('login', ['error' => $code]);

        $version = (string) config('services.facebook.graph_version', 'v24.0');
        try {
            $socialUser = Socialite::driver('facebook')
                ->usingGraphVersion($version)
                ->stateless()
                ->user();
        } catch (InvalidStateException) {
            return $toError('facebook_state_invalid');
        } catch (Throwable) {
            return $toError('facebook_oauth_failed');
        }

        $fbId = (string) $socialUser->getId();
        $shortToken = $socialUser->token;
        if ($fbId === '' || ! is_string($shortToken) || $shortToken === '') {
            return $toError('facebook_incomplete');
        }

        $name = $socialUser->getName() ?: 'Facebook User';
        $email = $socialUser->getEmail();
        if (! $email) {
            $email = 'fb_'.$fbId.'@users.facebook.local';
        }
        $email = Str::lower((string) $email);

        $longLived = $this->exchangeLongLivedUserToken($shortToken);
        $accessToken = is_string($longLived['access_token'] ?? null)
            ? $longLived['access_token']
            : $shortToken;
        $expiresAt = isset($longLived['expires_in'])
            ? now()->addSeconds((int) $longLived['expires_in'])
            : null;

        $avatar = $socialUser->getAvatar();

        $user = User::query()->where('facebook_id', $fbId)->first();
        if (! $user) {
            $byEmail = User::query()->where('email', $email)->first();
            if ($byEmail && $byEmail->facebook_id && $byEmail->facebook_id !== $fbId) {
                return $toError('facebook_email_conflict');
            }
            $user = $byEmail;
        }

        if ($user) {
            if ($user->is_active === false) {
                return $toError('account_disabled');
            }
            $user->name = $name;
            // ✅ Email sirf tab update karo jab user ke paas
            // koi email nahi ya FB placeholder email hai
            if (
                !$user->email ||
                str_contains($user->email, '@users.facebook.local')
            ) {
                $user->email = $email;
            }
            $user->facebook_id               = $fbId;
            $user->facebook_access_token     = $accessToken;
            $user->facebook_token_expires_at = $expiresAt;
            if ($avatar) {
                $user->avatar = $avatar;
            }
            $user->save();
        } else {
            $user = DB::transaction(function () use ($name, $email, $fbId, $accessToken, $expiresAt, $avatar) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::password(32)),
                    'facebook_id' => $fbId,
                    'facebook_access_token' => $accessToken,
                    'facebook_token_expires_at' => $expiresAt,
                    'avatar' => $avatar,
                ]);

                $workspaceName = $name."'s Workspace";
                $workspace = Workspace::query()->create([
                    'owner_id' => $user->id,
                    'name' => $workspaceName,
                    'slug' => $this->makeWorkspaceSlug($user->id, $workspaceName),
                    'plan' => 'free',
                ]);
                $workspace->members()->attach($user->id, [
                    'role' => 'owner',
                    'is_active' => true,
                ]);
                if (function_exists('setPermissionsTeamId')) {
                    setPermissionsTeamId((int) $workspace->id);
                }
                app(PermissionRegistrar::class)->setPermissionsTeamId((int) $workspace->id);
                Role::findOrCreate('owner', 'web');
                $user->syncRoles(['owner']);
                return $user;
            });
        }

        $workspace = $user->workspaces()
            ->wherePivot('is_active', true)
            ->orderBy('workspaces.id')
            ->first();
        if (! $workspace) {
            $workspace = Workspace::query()
                ->where('owner_id', $user->id)
                ->orderBy('id')
                ->first();
        }
        if (! $workspace) {
            $workspaceName = $user->name."'s Workspace";
            $workspace = Workspace::query()->create([
                'owner_id' => $user->id,
                'name' => $workspaceName,
                'slug' => $this->makeWorkspaceSlug($user->id, $workspaceName),
                'plan' => 'free',
            ]);
        }

        $hasMembership = $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->wherePivot('is_active', true)
            ->exists();
        if (! $hasMembership) {
            $workspace->members()->syncWithoutDetaching([
                $user->id => [
                    'role' => 'owner',
                    'is_active' => true,
                ],
            ]);
        }

        if ($workspace) {
            $connectedPages = SocialAccountProvisioner::connectFacebookPagesForWorkspace($workspace, $fbId, $accessToken, $expiresAt);
            if ($connectedPages < 1) {
                SocialAccountProvisioner::connectFacebookOnlyForWorkspace(
                    $workspace,
                    $fbId,
                    $accessToken,
                    $expiresAt,
                    $name
                );
            }
        }

        $user->tokens()->where('name', 'auth')->delete();
        $user->createToken('auth');

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('current_workspace_id', (int) $workspace->id);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * @return array{access_token?: string, expires_in?: int}
     */
    private function exchangeLongLivedUserToken(string $shortLived): array
    {
        $v = (string) config('services.facebook.graph_version', 'v24.0');
        $id = (string) config('services.facebook.client_id');
        $secret = (string) config('services.facebook.client_secret');
        if ($id === '' || $secret === '') {
            return ['access_token' => $shortLived];
        }
        $url = "https://graph.facebook.com/{$v}/oauth/access_token";
        $res = Http::timeout(20)->get($url, [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $id,
            'client_secret' => $secret,
            'fb_exchange_token' => $shortLived,
        ]);
        if (! $res->successful()) {
            return ['access_token' => $shortLived];
        }

        return $res->json() ?? [];
    }

    private function makeWorkspaceSlug(int $userId, string $workspaceName): string
    {
        $segment = Str::slug($workspaceName);
        if ($segment === '') {
            $segment = 'workspace';
        }

        return $segment.'-ws-'.$userId;
    }
}
