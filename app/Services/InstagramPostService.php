<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PublishJob;
use App\Models\PublishLog;
use App\Models\SocialAccount;
use App\Models\PostTarget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class InstagramPostService
{
    /**
     * Publish a Post to Instagram using a connected Business/Creator account (Graph API).
     *
     * @return array{success: bool, post_id?: string, error?: string}
     */
    public function publishPost(SocialAccount $account, Post $post, PostTarget $target): array
    {
        $version = (string) config('services.facebook.graph_version', 'v24.0');
        $igUserId = (string) ($account->platform_page_id ?? '');
        $token = $this->resolveToken((string) $account->access_token);

        if ($igUserId === '' || $token === '') {
            return ['success' => false, 'error' => 'Instagram Business account ID or Page token is missing.'];
        }

        $job = PublishJob::query()->create([
            'post_target_id' => $target->id,
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => 1,
        ]);

        $this->writeLog($job, 'info', 'Instagram publish started', ['ig_user_id' => $igUserId]);

        /** @var PostMedia|null $media */
        $media = $post->postMedia->first();

        if ($media === null) {
            $this->writeLog($job, 'error', 'Instagram requires at least one image or video.', null, null);
            $this->finishJob($job, 'failed');

            return ['success' => false, 'error' => 'Instagram posts require media (image or video).'];
        }

        $mediaUrl = $media->url;
        $mediaType = $media->type ?? 'image';

        try {
            if ($mediaType === 'video') {
                $result = $this->publishVideo($version, $igUserId, $token, $post->caption, $mediaUrl, $media, $job);
            } else {
                $result = $this->publishImage($version, $igUserId, $token, $post->caption, $mediaUrl, $media, $job);
            }

            if (($result['success'] ?? false) === true) {
                $this->finishJob($job, 'done');
            } else {
                $this->finishJob($job, 'failed');
            }

            return $result;
        } catch (Throwable $e) {
            report($e);
            $this->writeLog($job, 'error', $e->getMessage(), ['exception' => \get_class($e)], null);
            $this->finishJob($job, 'failed');

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, post_id?: string, error?: string}
     */
    private function publishImage(
        string $version,
        string $igUserId,
        string $token,
        string $caption,
        string $mediaUrl,
        PostMedia $media,
        PublishJob $job
    ): array {
        $publicUrl = $this->prepareMediaForInstagram($mediaUrl, 'image', $job);
        if ($publicUrl === null) {
            $this->writeLog($job, 'error', 'Could not resolve public URL for Instagram image.', ['stored_url' => $mediaUrl]);

            return ['success' => false, 'error' => 'Could not produce a public HTTPS URL Facebook can fetch. Set MEDIA_MIRROR_DRIVER (catbox|0x0|imgbb|cloudinary) or deploy media to a public host.'];
        }

        $this->writeLog($job, 'info', 'Creating Instagram image container', ['image_url' => $publicUrl]);

        $create = Http::timeout(60)->asForm()->post(
            "https://graph.facebook.com/{$version}/{$igUserId}/media",
            [
                'image_url' => $publicUrl,
                'caption' => mb_substr($caption, 0, 2200),
                'access_token' => $token,
            ]
        );

        if (! $create->successful()) {
            $body = $create->json() ?: $create->body();
            $this->writeLog($job, 'error', 'Instagram /media (image) failed', is_array($body) ? $body : ['body' => $body], $create->status());

            return ['success' => false, 'error' => $this->extractGraphErrorMessage($create->json(), $create->body())];
        }

        $creationId = $create->json('id');
        if (! is_string($creationId) || $creationId === '') {
            return ['success' => false, 'error' => 'Instagram media creation response missing id.'];
        }

        $poll = $this->waitForContainerFinished($version, $creationId, $token, $job);
        if (! $poll['success']) {
            return ['success' => false, 'error' => $poll['error'] ?? 'Instagram container did not finish.'];
        }

        return $this->publishCreation($version, $igUserId, $token, $creationId, $job);
    }

    /**
     * Video feed posts: Instagram deprecated media_type=VIDEO; use REELS + share_to_feed.
     *
     * @return array{success: bool, post_id?: string, error?: string}
     */
    private function publishVideo(
        string $version,
        string $igUserId,
        string $token,
        string $caption,
        string $mediaUrl,
        PostMedia $media,
        PublishJob $job
    ): array {
        $publicUrl = $this->prepareMediaForInstagram($mediaUrl, 'video', $job);
        if ($publicUrl === null) {
            $this->writeLog($job, 'error', 'Could not resolve public URL for Instagram video.', ['stored_url' => $mediaUrl]);

            return ['success' => false, 'error' => 'Could not produce a public HTTPS URL Facebook can fetch. Set MEDIA_MIRROR_DRIVER (catbox|0x0|imgbb|cloudinary) or deploy media to a public host.'];
        }

        $this->writeLog($job, 'info', 'Creating Instagram Reels container', ['video_url' => $publicUrl]);

        $payload = [
            'video_url' => $publicUrl,
            'caption' => mb_substr($caption, 0, 2200),
            'media_type' => 'REELS',
            'share_to_feed' => 'true',
            'access_token' => $token,
        ];

        $create = Http::timeout(120)->asForm()->post(
            "https://graph.facebook.com/{$version}/{$igUserId}/media",
            $payload
        );

        if (! $create->successful()) {
            $body = $create->json() ?: $create->body();
            $this->writeLog($job, 'error', 'Instagram /media (reels) failed', is_array($body) ? $body : ['body' => $body], $create->status());

            return ['success' => false, 'error' => $this->extractGraphErrorMessage($create->json(), $create->body())];
        }

        $creationId = $create->json('id');
        if (! is_string($creationId) || $creationId === '') {
            return ['success' => false, 'error' => 'Instagram Reels container response missing id.'];
        }

        $poll = $this->waitForContainerFinished($version, $creationId, $token, $job);
        if (! $poll['success']) {
            return ['success' => false, 'error' => $poll['error'] ?? 'Instagram Reels container did not finish.'];
        }

        return $this->publishCreation($version, $igUserId, $token, $creationId, $job);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function waitForContainerFinished(string $version, string $creationId, string $token, PublishJob $job): array
    {
        $maxAttempts = 45;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusRes = Http::timeout(30)->get(
                "https://graph.facebook.com/{$version}/{$creationId}",
                [
                    'fields' => 'status_code',
                    'access_token' => $token,
                ]
            );

            if (! $statusRes->successful()) {
                $this->writeLog($job, 'warning', 'Instagram container status check failed', ['body' => $statusRes->json() ?? $statusRes->body()], $statusRes->status());

                return ['success' => false, 'error' => 'Could not read Instagram media status.'];
            }

            $code = $statusRes->json('status_code');
            $this->writeLog($job, 'info', 'Instagram container status', ['status_code' => $code, 'attempt' => $i + 1]);

            if ($code === 'FINISHED') {
                return ['success' => true];
            }

            if ($code === 'ERROR') {
                return ['success' => false, 'error' => 'Instagram reported ERROR while processing media.'];
            }

            sleep(2);
        }

        return ['success' => false, 'error' => 'Timeout waiting for Instagram media container to finish processing.'];
    }

    /**
     * @return array{success: bool, post_id?: string, error?: string}
     */
    private function publishCreation(string $version, string $igUserId, string $token, string $creationId, PublishJob $job): array
    {
        $this->writeLog($job, 'info', 'Publishing Instagram media', ['creation_id' => $creationId]);

        $publish = Http::timeout(60)->asForm()->post(
            "https://graph.facebook.com/{$version}/{$igUserId}/media_publish",
            [
                'creation_id' => $creationId,
                'access_token' => $token,
            ]
        );

        if (! $publish->successful()) {
            $body = $publish->json() ?: $publish->body();
            $this->writeLog($job, 'error', 'Instagram media_publish failed', is_array($body) ? $body : ['body' => $body], $publish->status());

            return ['success' => false, 'error' => is_string($publish->body()) ? $publish->body() : 'Instagram publish failed.'];
        }

        $postId = $publish->json('id');
        if (! is_string($postId) || $postId === '') {
            return ['success' => false, 'error' => 'Instagram publish response missing id.'];
        }

        $this->writeLog($job, 'info', 'Instagram publish succeeded', ['media_id' => $postId]);

        return ['success' => true, 'post_id' => $postId];
    }

    /**
     * Prepare a media URL for Instagram's /media endpoint.
     *
     * Instagram fetches `image_url` / `video_url` from its servers, so the
     * URL must be publicly reachable and not behind ngrok-free's interstitial,
     * localhost, *.test, etc. When the configured driver detects a non-public
     * host, the local file is uploaded to a public mirror (catbox.moe by
     * default) and the mirror URL is returned. Hostnames containing "staging"
     * or starting with "dev." also trigger mirroring (Meta often cannot fetch those URLs).
     */
    private function prepareMediaForInstagram(string $mediaUrl, string $kind, PublishJob $job): ?string
    {
        $localPath = $this->localPathForMediaUrl($mediaUrl);

        $candidateUrl = null;
        if (str_starts_with($mediaUrl, 'http://') || str_starts_with($mediaUrl, 'https://')) {
            $candidateUrl = $mediaUrl;
        } elseif ($localPath !== null) {
            $relative = ltrim(str_replace(storage_path('app/public'), '', $localPath), DIRECTORY_SEPARATOR);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $candidateUrl = rtrim(URL::to('/storage'), '/').'/'.ltrim($relative, '/');
        }

        if ($candidateUrl === null && $localPath === null) {
            return null;
        }

        $driver = (string) config('services.media_mirror.driver', 'catbox');
        $hasLocalFile = $localPath !== null && is_file($localPath);
        $alwaysMirrorLocal = (bool) config('services.media_mirror.always_mirror_local', false);
        $needsMirror = $driver !== 'none' && $candidateUrl !== null
            && ($this->urlNeedsMirror($candidateUrl) || ($hasLocalFile && $alwaysMirrorLocal));

        if (! $needsMirror) {
            return $candidateUrl;
        }

        if ($localPath === null) {
            $localPath = $this->downloadRemoteToTemp($candidateUrl, $job);
        }
        if ($localPath === null || ! is_file($localPath)) {
            $this->writeLog($job, 'warning', 'Mirror skipped: local file unavailable for upload', ['stored_url' => $mediaUrl]);

            return $candidateUrl;
        }

        $mirroredUrl = $this->mirrorToPublicHost($localPath, $kind, $job);
        if ($mirroredUrl !== null) {
            $this->writeLog($job, 'info', 'Mirrored media to public host for Instagram', [
                'driver' => $driver,
                'original' => $candidateUrl,
                'mirrored' => $mirroredUrl,
            ]);

            return $mirroredUrl;
        }

        return null;
    }

    private function localPathForMediaUrl(string $mediaUrl): ?string
    {
        if ($mediaUrl === '') {
            return null;
        }
        $candidates = [];

        if (str_starts_with($mediaUrl, 'http://') || str_starts_with($mediaUrl, 'https://')) {
            $path = parse_url($mediaUrl, PHP_URL_PATH);
            if (is_string($path) && str_contains($path, '/storage/')) {
                $relative = ltrim(substr($path, strpos($path, '/storage/') + strlen('/storage/')), '/');
                $candidates[] = storage_path('app/public/'.str_replace('/', DIRECTORY_SEPARATOR, $relative));
            }
        } else {
            $candidates[] = str_replace(url('/storage'), storage_path('app/public'), $mediaUrl);
            $candidates[] = str_replace(URL::to('/storage'), storage_path('app/public'), $mediaUrl);
        }

        foreach ($candidates as $path) {
            if (is_string($path) && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function urlNeedsMirror(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return false;
        }
        $host = strtolower($host);

        $needles = array_merge(
            (array) config('services.media_mirror.force_for_hosts', []),
            (array) config('services.media_mirror.extra_force_hosts', []),
        );
        foreach ($needles as $needle) {
            $needle = strtolower(trim((string) $needle));
            if ($needle === '') {
                continue;
            }
            if (str_starts_with($needle, '.')) {
                if (str_ends_with($host, $needle)) {
                    return true;
                }
            } elseif ($host === $needle || str_ends_with($host, '.'.$needle)) {
                return true;
            }
        }

        // Staging / dev hostnames are often unreachable by Meta's fetchers (VPN, IP allowlists, etc.).
        if (str_contains($host, 'staging') || str_starts_with($host, 'dev.')) {
            return true;
        }

        return false;
    }

    private function downloadRemoteToTemp(string $url, PublishJob $job): ?string
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                ->get($url);
            if (! $response->successful()) {
                $this->writeLog($job, 'warning', 'Could not pre-fetch media for mirroring', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'igmedia_');
            if ($tmp === false) {
                return null;
            }
            file_put_contents($tmp, $response->body());

            return $tmp;
        } catch (Throwable $e) {
            $this->writeLog($job, 'warning', 'Pre-fetch for mirror failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function mirrorToPublicHost(string $localPath, string $kind, PublishJob $job): ?string
    {
        $primary = strtolower(trim((string) config('services.media_mirror.driver', 'catbox')));
        foreach ($this->mirrorDriverAttempts($primary) as $driver) {
            try {
                $url = match ($driver) {
                    'catbox' => $this->uploadToCatbox($localPath, $job),
                    '0x0' => $this->uploadToZeroXZero($localPath, $job),
                    'imgbb' => $this->uploadToImgbb($localPath, $kind, $job),
                    'cloudinary' => $this->uploadToCloudinary($localPath, $kind, $job),
                    default => null,
                };
                if (is_string($url) && $url !== '') {
                    if ($driver !== $primary) {
                        $this->writeLog($job, 'info', 'Media mirror used fallback driver', [
                            'primary' => $primary,
                            'used' => $driver,
                        ]);
                    }

                    return $url;
                }
            } catch (Throwable $e) {
                $this->writeLog($job, 'warning', 'Media mirror driver attempt failed', [
                    'driver' => $driver,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function mirrorDriverAttempts(string $primary): array
    {
        $freeMirrors = ['catbox', '0x0'];

        return match ($primary) {
            'catbox' => ['catbox', '0x0'],
            '0x0' => ['0x0', 'catbox'],
            'imgbb' => ['imgbb', 'catbox', '0x0'],
            'cloudinary' => ['cloudinary', 'catbox', '0x0'],
            'none' => [],
            default => $freeMirrors,
        };
    }

    private function uploadToCatbox(string $localPath, PublishJob $job): ?string
    {
        $response = Http::timeout(120)
            ->attach('fileToUpload', fopen($localPath, 'r'), basename($localPath))
            ->asMultipart()
            ->post('https://catbox.moe/user/api.php', ['reqtype' => 'fileupload']);

        $body = trim((string) $response->body());
        if (! $response->successful() || ! str_starts_with($body, 'https://')) {
            $this->writeLog($job, 'error', 'catbox.moe upload failed', ['status' => $response->status(), 'body' => mb_substr($body, 0, 500)]);

            return null;
        }

        return $body;
    }

    private function uploadToZeroXZero(string $localPath, PublishJob $job): ?string
    {
        $response = Http::timeout(120)
            ->attach('file', fopen($localPath, 'r'), basename($localPath))
            ->asMultipart()
            ->post('https://0x0.st');

        $body = trim((string) $response->body());
        if (! $response->successful() || ! str_starts_with($body, 'https://')) {
            $this->writeLog($job, 'error', '0x0.st upload failed', ['status' => $response->status(), 'body' => mb_substr($body, 0, 500)]);

            return null;
        }

        return $body;
    }

    private function uploadToImgbb(string $localPath, string $kind, PublishJob $job): ?string
    {
        if ($kind !== 'image') {
            $this->writeLog($job, 'warning', 'imgbb mirror only supports images; falling back to catbox.', ['kind' => $kind]);

            return $this->uploadToCatbox($localPath, $job);
        }

        $key = (string) config('services.media_mirror.imgbb_key', '');
        if ($key === '') {
            $this->writeLog($job, 'error', 'MEDIA_MIRROR_IMGBB_KEY is not set', []);

            return null;
        }

        $response = Http::timeout(120)
            ->asMultipart()
            ->attach('image', fopen($localPath, 'r'), basename($localPath))
            ->post('https://api.imgbb.com/1/upload?key='.$key);

        if (! $response->successful()) {
            $this->writeLog($job, 'error', 'imgbb upload failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $url = $response->json('data.url') ?? $response->json('data.display_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function uploadToCloudinary(string $localPath, string $kind, PublishJob $job): ?string
    {
        $cfg = (array) config('services.media_mirror.cloudinary', []);
        $url = (string) ($cfg['url'] ?? '');
        if ($url !== '') {
            $parts = parse_url($url);
            $cfg['cloud_name'] = $cfg['cloud_name'] ?: ($parts['host'] ?? null);
            $cfg['api_key'] = $cfg['api_key'] ?: ($parts['user'] ?? null);
            $cfg['api_secret'] = $cfg['api_secret'] ?: ($parts['pass'] ?? null);
        }

        $cloud = (string) ($cfg['cloud_name'] ?? '');
        $key = (string) ($cfg['api_key'] ?? '');
        $secret = (string) ($cfg['api_secret'] ?? '');
        if ($cloud === '' || $key === '' || $secret === '') {
            $this->writeLog($job, 'error', 'Cloudinary credentials not configured', []);

            return null;
        }

        $resourceType = $kind === 'video' ? 'video' : 'image';
        $timestamp = time();
        $signature = sha1('timestamp='.$timestamp.$secret);

        $response = Http::timeout(180)
            ->asMultipart()
            ->attach('file', fopen($localPath, 'r'), basename($localPath))
            ->post("https://api.cloudinary.com/v1_1/{$cloud}/{$resourceType}/upload", [
                'api_key' => $key,
                'timestamp' => (string) $timestamp,
                'signature' => $signature,
            ]);

        if (! $response->successful()) {
            $this->writeLog($job, 'error', 'Cloudinary upload failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $secureUrl = $response->json('secure_url') ?? $response->json('url');

        return is_string($secureUrl) && $secureUrl !== '' ? $secureUrl : null;
    }

    private function extractGraphErrorMessage(mixed $json, string $rawBody): string
    {
        if (is_array($json)) {
            $msg = $json['error']['message'] ?? null;
            $type = $json['error']['type'] ?? null;
            $code = $json['error']['code'] ?? null;
            $sub = $json['error']['error_user_msg'] ?? null;
            $parts = array_filter([
                $msg ? (string) $msg : null,
                $sub ? (string) $sub : null,
                $code !== null ? '(code '.$code.($type ? ', '.$type : '').')' : null,
            ]);
            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }
        $rawBody = trim($rawBody);

        return $rawBody !== '' ? mb_substr($rawBody, 0, 500) : 'Instagram media creation failed.';
    }

    private function writeLog(PublishJob $job, string $level, string $message, ?array $response = null, ?int $httpStatus = null): void
    {
        PublishLog::query()->create([
            'publish_job_id' => $job->id,
            'level' => $level,
            'message' => $message,
            'response' => $response,
            'http_status' => $httpStatus,
        ]);
    }

    private function finishJob(PublishJob $job, string $status): void
    {
        $job->update([
            'status' => $status,
            'completed_at' => now(),
        ]);
    }

    private function resolveToken(string $token): string
    {
        try {
            $decrypted = decrypt($token);

            return is_string($decrypted) ? $decrypted : $token;
        } catch (Throwable) {
            return $token;
        }
    }
}
