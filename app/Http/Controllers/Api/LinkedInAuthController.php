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

class LinkedInAuthController extends Controller
{
    public function redirectToLinkedIn(): RedirectResponse
    {
        $scopes = config('services.linkedin.oauth_scopes', [
            'openid',
            'profile',
            'email',
            'w_member_social',
            'r_member_profileAnalytics',
        ]);

        return Socialite::driver('linkedin-openid')
            ->scopes($scopes)
            ->with(['prompt' => 'login'])
            ->stateless()
            ->redirect();
    }

    public function handleCallback(Request $request): RedirectResponse
    {
        $toError = fn (string $code) => redirect()->route('login', ['error' => $code]);

        try {
            $socialUser = Socialite::driver('linkedin-openid')
                ->stateless()
                ->user();
        } catch (InvalidStateException) {
            return $toError('linkedin_state_invalid');
        } catch (Throwable) {
            return $toError('linkedin_oauth_failed');
        }

        $liId = (string) $socialUser->getId();
        $accessToken = $socialUser->token;
        if ($liId === '' || ! is_string($accessToken) || $accessToken === '') {
            return $toError('linkedin_incomplete');
        }

        $name = $socialUser->getName() ?: 'LinkedIn User';
        $email = $socialUser->getEmail();
        if (! $email) {
            $email = 'li_'.$liId.'@users.linkedin.local';
        }
        $email = Str::lower((string) $email);

        $expiresAt = $socialUser->expiresIn
            ? now()->addSeconds((int) $socialUser->expiresIn)
            : now()->addDays(60);

        $avatar = $socialUser->getAvatar();
        $refreshToken = $socialUser->refreshToken;

        // If user is already authenticated, attach LinkedIn to their account and connect
        if (Auth::check()) {
            $user = Auth::user();

            $user->linkedin_id = $liId;
            $user->linkedin_access_token = $accessToken;
            $user->linkedin_refresh_token = $refreshToken ?: null;
            $user->linkedin_token_expires_at = $expiresAt;
            if ($avatar) {
                $user->avatar = $avatar;
            }
            $user->save();

            $workspace = $user->workspaces()->wherePivot('is_active', true)->first();
            if ($workspace) {
                $decryptedAccessToken  = $user->linkedin_access_token;
                $decryptedRefreshToken = $user->linkedin_refresh_token;

                $vanityName = SocialAccountProvisioner::fetchLinkedInVanityName($decryptedAccessToken);
                $stats = SocialAccountProvisioner::fetchLinkedInPersonStats($decryptedAccessToken, $liId);

                SocialAccountProvisioner::connectLinkedInForWorkspace(
                    $workspace,
                    $liId,
                    $decryptedAccessToken,
                    $decryptedRefreshToken,
                    $expiresAt,
                    $name,
                    $avatar,
                    $vanityName,
                    $email,
                    $stats['followers'] ?? null,
                    $stats['following'] ?? null,
                    $stats['posts'] ?? null,
                );

                $this->fetchLinkedInOrganizations($workspace, $liId, $decryptedAccessToken, $expiresAt);
            }

            return redirect()->route('dashboard');
        }

        // Not authenticated: proceed with existing signup/login flow
        $user = User::query()->where('linkedin_id', $liId)->first();
        if (! $user) {
            $byEmail = User::query()->where('email', $email)->first();
            if ($byEmail && $byEmail->linkedin_id && $byEmail->linkedin_id !== $liId) {
                return $toError('linkedin_email_conflict');
            }
            $user = $byEmail;
        }

        if ($user) {
            if ($user->is_active === false) {
                return $toError('account_disabled');
            }
            $user->name = $name;
            if (
                ! $user->email ||
                str_contains((string) $user->email, '@users.linkedin.local')
            ) {
                $emailTakenByOther = User::query()
                    ->where('email', $email)
                    ->where('id', '!=', $user->id)
                    ->exists();
                if (! $emailTakenByOther) {
                    $user->email = $email;
                }
            }
            $user->linkedin_id = $liId;
            $user->linkedin_access_token = $accessToken;
            $user->linkedin_refresh_token = $refreshToken ?: null;
            $user->linkedin_token_expires_at = $expiresAt;
            if ($avatar) {
                $user->avatar = $avatar;
            }
            $user->save();
        } else {
            $user = DB::transaction(function () use ($name, $email, $liId, $accessToken, $refreshToken, $expiresAt, $avatar) {
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::password(32)),
                    'linkedin_id' => $liId,
                    'linkedin_access_token' => $accessToken,
                    'linkedin_refresh_token' => $refreshToken ?: null,
                    'linkedin_token_expires_at' => $expiresAt,
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

        Auth::login($user);

        $workspace = $user->workspaces()->wherePivot('is_active', true)->first();
        if ($workspace) {
            $vanityName = SocialAccountProvisioner::fetchLinkedInVanityName($user->linkedin_access_token);
            $stats = SocialAccountProvisioner::fetchLinkedInPersonStats($user->linkedin_access_token, $liId);

            SocialAccountProvisioner::connectLinkedInForWorkspace(
                $workspace,
                $liId,
                $user->linkedin_access_token,
                $user->linkedin_refresh_token,
                $expiresAt,
                $name,
                $avatar,
                $vanityName,
                $email,
                $stats['followers'] ?? null,
                $stats['following'] ?? null,
                $stats['posts'] ?? null,
            );

            $this->fetchLinkedInOrganizations($workspace, $liId, $user->linkedin_access_token, $expiresAt);
        }

        return redirect()->route('dashboard');
    }

    private function fetchLinkedInOrganizations(Workspace $workspace, string $userUrn, string $accessToken, \Carbon\Carbon $expiresAt): void
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$accessToken}",
                    'LinkedIn-Version' => '202406',
                ])
                ->get('https://api.linkedin.com/v2/organizationAcls', [
                    'q' => 'roleAssignee',
                    'role' => 'ADMINISTRATOR',
                    'projection' => '(elements*(organization~(id,localizedName,logoV2(original~:playbackUrl),followingInfo)))',
                ]);

            if ($response->successful() && isset($response['elements'])) {
                foreach ($response['elements'] as $element) {
                    $org = $element['organization~'] ?? null;
                    if (! is_array($org)) {
                        continue;
                    }

                    $orgId = (string) ($org['id'] ?? '');
                    $orgName = (string) ($org['localizedName'] ?? 'LinkedIn Organization');
                    $avatar = null;

                    if (isset($org['logoV2']['original~']['sourceUrl'])) {
                        $avatar = (string) $org['logoV2']['original~']['sourceUrl'];
                    }

                    // followingInfo is already requested in the projection above —
                    // surface its followerCount so the accounts UI can render it.
                    $followers = null;
                    $followingInfo = $org['followingInfo'] ?? null;
                    if (is_array($followingInfo) && isset($followingInfo['followerCount'])
                        && is_numeric($followingInfo['followerCount'])) {
                        $followers = (int) $followingInfo['followerCount'];
                    }

                    $postsCount = SocialAccountProvisioner::fetchLinkedInOrganizationPostsCount($orgId, $accessToken);

                    SocialAccountProvisioner::connectLinkedInOrganizationForWorkspace(
                        $workspace,
                        $userUrn,
                        $orgId,
                        $accessToken,
                        null,
                        $expiresAt,
                        $orgName,
                        $avatar,
                        $followers,
                        $postsCount,
                    );
                }
            }
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to fetch LinkedIn organizations', ['error' => $e->getMessage()]);
        }
    }

    private function makeWorkspaceSlug(int $userId, string $workspaceName): string
    {
        $base = Str::slug($workspaceName);
        $slug = $base;
        $counter = 1;
        while (Workspace::query()->where('slug', $slug)->where('owner_id', '!=', $userId)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
