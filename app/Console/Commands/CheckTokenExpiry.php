<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckTokenExpiry extends Command
{
    protected $signature = 'tokens:check-expiry';

    protected $description = 'Check Instagram token expiry and log warning';

    public function handle(): int
    {
        $igToken = (string) env('IG_ACCESS_TOKEN', '');
        $appId = (string) env('FACEBOOK_APP_ID', '');
        $appSecret = (string) env('FACEBOOK_APP_SECRET', '');
        if ($igToken === '' || $appId === '' || $appSecret === '') {
            $this->warn('Missing IG token or app credentials.');
            return self::SUCCESS;
        }

        $appToken = $appId.'|'.$appSecret;
        $res = Http::timeout(20)->get('https://graph.facebook.com/debug_token', [
            'input_token' => $igToken,
            'access_token' => $appToken,
        ]);

        if (! $res->successful()) {
            Log::warning('Token expiry check failed', ['status' => $res->status(), 'body' => $res->body()]);
            return self::SUCCESS;
        }

        $expiresAt = (int) ($res->json('data.expires_at') ?? 0);
        if ($expiresAt > 0) {
            $secondsLeft = $expiresAt - now()->timestamp;
            if ($secondsLeft < 864000) {
                Log::warning('Instagram token expiring soon. Renew within 10 days.', [
                    'seconds_left' => $secondsLeft,
                ]);
            }
        }

        return self::SUCCESS;
    }
}

