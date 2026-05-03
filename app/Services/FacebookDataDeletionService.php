<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacebookDataDeletionService
{
    /**
     * Parse and verify a Meta `signed_request` (HMAC-SHA256).
     *
     * @return array<string, mixed>|null
     */
    public function parseSignedRequest(string $signedRequest): ?array
    {
        $secret = (string) config('services.facebook.client_secret');
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $signedRequest, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedSig, $payload] = $parts;
        $sig = $this->base64UrlDecode($encodedSig);
        if ($sig === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $payload, $secret, true);
        if (! hash_equals($expected, $sig)) {
            return null;
        }

        $json = $this->base64UrlDecode($payload);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }

        if (($data['algorithm'] ?? '') !== 'HMAC-SHA256') {
            return null;
        }

        return $data;
    }

    /**
     * Remove data received from Facebook for this Meta user id (Graph user id).
     * Returns a confirmation code for Meta’s callback response and the status page.
     */
    public function purgeForFacebookUserId(string $facebookUserId): string
    {
        $facebookUserId = trim($facebookUserId);
        $code = (string) Str::uuid();

        if ($facebookUserId === '') {
            return $code;
        }

        DB::transaction(function () use ($facebookUserId, $code): void {
            $platformIds = SocialPlatform::query()
                ->whereIn('slug', ['facebook', 'instagram'])
                ->where('is_active', true)
                ->pluck('id');

            if ($platformIds->isNotEmpty()) {
                SocialAccount::query()
                    ->where('platform_user_id', $facebookUserId)
                    ->whereIn('social_platform_id', $platformIds)
                    ->delete();
            }

            $users = User::query()->where('facebook_id', $facebookUserId)->get();
            foreach ($users as $user) {
                $user->forceFill([
                    'facebook_id' => null,
                    'facebook_access_token' => null,
                    'facebook_token_expires_at' => null,
                ])->save();
                $user->tokens()->delete();
            }

            Log::info('facebook_data_deletion_completed', [
                'facebook_user_id' => $facebookUserId,
                'confirmation_code' => $code,
                'users_affected' => $users->count(),
            ]);
        });

        return $code;
    }

    public function purgeForAuthenticatedUser(User $user): ?string
    {
        $fbId = $user->facebook_id;
        if ($fbId === null || $fbId === '') {
            return null;
        }

        return $this->purgeForFacebookUserId((string) $fbId);
    }

    private function base64UrlDecode(string $input): false|string
    {
        $input = strtr($input, '-_', '+/');
        $pad = strlen($input) % 4;
        if ($pad > 0) {
            $input .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($input, true);
    }
}
