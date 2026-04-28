<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserProfileService
{
    public function updateProfile(User $user, string $name, string $email, string $timezone): void
    {
        if ($name === '' || $email === '') {
            return;
        }

        $user->name = $name;
        $user->email = $email;
        $user->timezone = $timezone !== '' ? $timezone : 'UTC';
        $user->save();
    }

    public function updatePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
    ): void {
        if (! Hash::check($currentPassword, (string) $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => [__('The current password is incorrect.')],
            ]);
        }

        $user->password = $newPassword;
        $user->save();
    }
}
