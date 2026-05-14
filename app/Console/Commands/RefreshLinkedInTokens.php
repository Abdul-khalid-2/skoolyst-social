<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshLinkedInTokens extends Command
{
    protected $signature = 'linkedin:refresh-tokens';

    protected $description = 'Refresh expired or expiring LinkedIn access tokens.';

    public function handle(): int
    {
        $linkedin = SocialPlatform::query()
            ->where('slug', 'linkedin')
            ->where('is_active', true)
            ->first();

        if (! $linkedin) {
            $this->info('LinkedIn platform not found or inactive.');

            return self::SUCCESS;
        }

        // Find accounts with tokens expiring within 7 days
        $expiringThreshold = now()->addDays(7);
        $accounts = SocialAccount::query()
            ->where('social_platform_id', $linkedin->id)
            ->where('is_connected', true)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', $expiringThreshold)
            ->get();

        $this->info("Found {$accounts->count()} LinkedIn accounts with expiring tokens.");

        $refreshed = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            try {
                if (! $account->refresh_token) {
                    $this->warn("Account {$account->id} has no refresh token, skipping.");
                    $failed++;

                    continue;
                }

                $refreshToken = decrypt($account->refresh_token);

                $response = Http::timeout(30)->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => (string) config('services.linkedin.client_id'),
                    'client_secret' => (string) config('services.linkedin.client_secret'),
                ]);

                if (! $response->successful()) {
                    $this->error("Failed to refresh token for account {$account->id}: {$response->status()}");
                    Log::error('LinkedIn token refresh failed', [
                        'account_id' => $account->id,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    $failed++;

                    continue;
                }

                $data = $response->json();

                $newAccessToken = $data['access_token'] ?? null;
                $newRefreshToken = $data['refresh_token'] ?? $refreshToken;
                $expiresIn = (int) ($data['expires_in'] ?? 5184000); // 60 days default

                if (! $newAccessToken) {
                    $this->error("Access token missing from response for account {$account->id}");
                    $failed++;

                    continue;
                }

                $account->update([
                    'access_token' => encrypt($newAccessToken),
                    'refresh_token' => encrypt($newRefreshToken),
                    'token_expires_at' => now()->addSeconds($expiresIn),
                ]);

                $this->info("Refreshed token for account {$account->account_name} (ID: {$account->id})");
                $refreshed++;
            } catch (Throwable $e) {
                $this->error("Exception while refreshing account {$account->id}: {$e->getMessage()}");
                Log::error('LinkedIn token refresh exception', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        $this->info("LinkedIn token refresh completed: {$refreshed} refreshed, {$failed} failed.");

        return self::SUCCESS;
    }
}
