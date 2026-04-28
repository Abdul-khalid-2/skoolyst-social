<?php

namespace App\Http\Controllers;

use App\Actions\Auth\RegisterUserWithDefaultWorkspaceAction;
use App\Http\Requests\WebLoginRequest;
use App\Http\Requests\WebRegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): RedirectResponse|View
    {
        if ($request->query('error')) {
            $code = (string) $request->query('error');
            $map = [
                'facebook_state_invalid' => 'Facebook session expired. Please try again.',
                'facebook_oauth_failed' => 'Could not sign in with Facebook. Please try again.',
                'facebook_incomplete' => 'Facebook did not return a complete profile. Please try again.',
                'account_disabled' => 'This account is disabled.',
                'facebook_email_conflict' => 'This email is already linked to a different Facebook account.',
            ];

            return redirect()
                ->route('login')
                ->with('error', $map[$code] ?? 'Facebook sign-in failed.');
        }

        return view('auth.login', [
            'title' => 'Sign in',
            'description' => 'Sign in to your account to continue.',
        ]);
    }

    public function login(WebLoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => (string) $request->validated('email'),
            'password' => (string) $request->validated('password'),
        ];

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => [__('The provided credentials are incorrect.')],
            ]);
        }

        $user = Auth::user();
        if ($user->is_active === false) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => [__('This account is disabled.')],
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register', [
            'title' => 'Create an account',
            'description' => 'Create your Skoolyst Social AI account.',
        ]);
    }

    public function register(
        WebRegisterRequest $request,
        RegisterUserWithDefaultWorkspaceAction $action,
    ): RedirectResponse {
        $data = $request->validated();
        $user = $action->execute($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Welcome! Your account and default workspace are ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
