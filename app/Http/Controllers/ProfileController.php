<?php

namespace App\Http\Controllers;

use App\Http\Requests\WebUpdatePasswordRequest;
use App\Http\Requests\WebUpdateProfileRequest;
use App\Services\UserProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserProfileService $userProfile,
    ) {}

    public function index(Request $request): View
    {
        return view('profile.index', [
            'user' => $request->user(),
            'title' => 'Profile',
            'description' => 'Update your account details and security.',
        ]);
    }

    public function update(WebUpdateProfileRequest $request): RedirectResponse
    {
        $this->userProfile->updateProfile(
            $request->user(),
            (string) $request->validated('name'),
            (string) $request->validated('email'),
            $request->profileTimezone()
        );

        return redirect()
            ->back(fallback: route('profile'))
            ->with('success', __('Profile updated successfully.'));
    }

    public function updatePassword(WebUpdatePasswordRequest $request): RedirectResponse
    {
        $this->userProfile->updatePassword(
            $request->user(),
            (string) $request->validated('current_password'),
            (string) $request->validated('password')
        );

        return redirect()
            ->back(fallback: route('profile'))
            ->with('success', __('Password changed successfully.'));
    }
}
