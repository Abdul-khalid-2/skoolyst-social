<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SocialAccountProvisioner;
use App\Support\AvatarUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class XAuthController extends Controller
{
    private const SCOPES = ['tweet.read', 'tweet.write', 'users.read', 'offline.access'];

    public function redirectToX(Request $request): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()
                ->route('login')
                ->with('error', __('Please log in to Skoolyst before connecting an X account.'));
        }

        Log::info('X OAuth redirect started', [
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'redirect_uri' => config('services.twitteroauth2.redirect'),
            'scopes' => self::SCOPES,
        ]);

        $response = Socialite::driver('twitteroauth2')
            ->scopes(self::SCOPES)
            ->redirect();

        $request->session()->save();

        Log::info('X OAuth redirect session persisted', [
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'has_code_verifier' => $request->session()->has('code_verifier'),
        ]);

        return $response;
    }

    public function handleCallback(Request $request): RedirectResponse
    {
        Log::info('X OAuth callback received', [
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'has_code' => $request->filled('code'),
            'has_code_verifier' => $request->session()->has('code_verifier'),
            'has_error' => $request->filled('error'),
            'error' => $request->query('error'),
            'error_description' => $request->query('error_description'),
        ]);

        if ($request->filled('error')) {
            $errorCode = (string) $request->query('error');
            $description = urldecode((string) $request->query('error_description', $errorCode));

            $message = match ($errorCode) {
                'access_denied' => __('X sign-in was cancelled.'),
                default => __('X authorization failed: :detail', ['detail' => $description]),
            };

            return $this->oauthFailureRedirect('twitter_oauth_denied', $message, [
                'twitter_error' => $errorCode,
                'twitter_error_description' => $description,
            ]);
        }

        if (! $request->filled('code')) {
            return $this->oauthFailureRedirect(
                'twitter_missing_code',
                __('X did not return an authorization code. Open Accounts, click Connect, then Continue — do not open the callback URL directly.'),
                ['session_id' => $request->session()->getId()]
            );
        }

        if (! $request->session()->has('code_verifier')) {
            return $this->oauthFailureRedirect(
                'twitter_pkce_lost',
                __('OAuth session was lost (PKCE verifier missing). Log in on the same browser, then connect X again from Accounts.'),
                ['session_id' => $request->session()->getId()]
            );
        }

        try {
            $socialUser = Socialite::driver('twitteroauth2')->user();
        } catch (InvalidStateException $e) {
            return $this->oauthFailureRedirect(
                'twitter_state_invalid',
                __('X sign-in session expired. Please try again.'),
                ['exception' => $e->getMessage()]
            );
        } catch (Throwable $e) {
            return $this->oauthFailureRedirect(
                'twitter_oauth_failed',
                __('X connection failed. Please try again.'),
                ['exception' => $e->getMessage()]
            );
        }

        $twitterId = (string) $socialUser->getId();
        $accessToken = $socialUser->token;
        if ($twitterId === '' || ! is_string($accessToken) || $accessToken === '') {
            return $this->oauthFailureRedirect(
                'twitter_incomplete',
                __('X did not return a complete profile. Please try again.'),
                ['twitter_id' => $twitterId]
            );
        }

        if (! Auth::check()) {
            return $this->oauthFailureRedirect(
                'twitter_not_authenticated',
                __('You must be logged in to connect an X account.')
            );
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->is_active === false) {
            return $this->oauthFailureRedirect(
                'account_disabled',
                __('Your account is disabled. Contact your administrator.')
            );
        }

        $workspaceId = session('current_workspace_id');
        $workspace = $workspaceId
            ? Workspace::query()->find($workspaceId)
            : $user->workspaces()->wherePivot('is_active', true)->first();

        if (! $workspace) {
            return $this->oauthFailureRedirect(
                'twitter_no_workspace',
                __('No active workspace found. Please select a workspace first.')
            );
        }

        $platform = SocialPlatform::query()
            ->where('slug', 'twitter')
            ->where('is_active', true)
            ->first();

        if (! $platform) {
            return $this->oauthFailureRedirect(
                'twitter_platform_missing',
                __('X platform is not configured. Contact your administrator.')
            );
        }

        $name = $socialUser->getName() ?: 'X User';
        $username = (string) ($socialUser->getNickname() ?? '');
        $avatar = AvatarUrl::forStorage($socialUser->getAvatar());
        $refreshToken = $socialUser->refreshToken;
        $expiresAt = $socialUser->expiresIn
            ? now()->addSeconds((int) $socialUser->expiresIn)
            : null;

        try {
            SocialAccountProvisioner::upsertWorkspaceAccount(
                $workspace,
                (int) $platform->id,
                $twitterId,
                [
                    'platform_user_id' => $twitterId,
                    'account_name' => $name,
                    'account_handle' => $username !== '' ? '@'.$username : $name,
                    'avatar' => $avatar,
                    'access_token' => encrypt($accessToken),
                    'refresh_token' => $refreshToken ? encrypt($refreshToken) : null,
                    'token_expires_at' => $expiresAt,
                    'scopes' => self::SCOPES,
                    'is_connected' => true,
                    'is_active' => true,
                ]
            );
        } catch (Throwable $e) {
            return $this->oauthFailureRedirect(
                'twitter_save_failed',
                __('X account could not be saved. Please try again.'),
                ['exception' => $e->getMessage()]
            );
        }

        Log::info('X account connected', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'twitter_id' => $twitterId,
        ]);

        return redirect()->route('accounts')->with('success', __('X account connected successfully.'));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function oauthFailureRedirect(string $code, string $message, array $context = []): RedirectResponse
    {
        Log::warning('X OAuth failed', array_merge([
            'code' => $code,
            'message' => $message,
            'user_id' => Auth::id(),
        ], $context));

        return redirect()->route('accounts')->with('error', $message);
    }
}
