<?php

namespace App\Services;

use App\Models\PublishJob;
use App\Models\PublishLog;
use App\Traits\ResolvesMediaPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class PublicMediaUrlService
{
    use ResolvesMediaPath;

    /**
     * Resolve a publicly fetchable HTTPS URL for Meta Graph API (Facebook / Instagram).
     */
    public function prepare(string $mediaUrl, string $kind = 'image', ?PublishJob $job = null): ?string
    {
        $localPath = $this->resolveMediaPath($mediaUrl);

        $candidateUrl = null;
        if (str_starts_with($mediaUrl, 'http://') || str_starts_with($mediaUrl, 'https://')) {
            $candidateUrl = $mediaUrl;
        } elseif ($localPath !== null) {
            $urlPath = parse_url($mediaUrl, PHP_URL_PATH);
            if (is_string($urlPath) && $urlPath !== '') {
                $candidateUrl = rtrim(URL::to('/'), '/').'/'.ltrim($urlPath, '/');
            } else {
                $relative = ltrim(str_replace(storage_path('app/public'), '', $localPath), DIRECTORY_SEPARATOR);
                $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                $candidateUrl = rtrim(URL::to('/storage'), '/').'/'.ltrim($relative, '/');
            }
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
            $this->log($job, 'warning', 'Mirror skipped: local file unavailable for upload', ['stored_url' => $mediaUrl]);

            return $candidateUrl;
        }

        $mirroredUrl = $this->mirrorToPublicHost($localPath, $kind, $job);
        if ($mirroredUrl !== null) {
            $this->log($job, 'info', 'Mirrored media to public host', [
                'driver' => $driver,
                'original' => $candidateUrl,
                'mirrored' => $mirroredUrl,
            ]);

            return $mirroredUrl;
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

        if (str_contains($host, 'staging') || str_starts_with($host, 'dev.')) {
            return true;
        }

        return false;
    }

    private function downloadRemoteToTemp(string $url, ?PublishJob $job): ?string
    {
        try {
            $response = Http::timeout(60)
                ->withHeaders(['ngrok-skip-browser-warning' => 'true'])
                ->get($url);
            if (! $response->successful()) {
                $this->log($job, 'warning', 'Could not pre-fetch media for mirroring', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'socialmedia_');
            if ($tmp === false) {
                return null;
            }
            file_put_contents($tmp, $response->body());

            return $tmp;
        } catch (Throwable $e) {
            $this->log($job, 'warning', 'Pre-fetch for mirror failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function mirrorToPublicHost(string $localPath, string $kind, ?PublishJob $job): ?string
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
                        $this->log($job, 'info', 'Media mirror used fallback driver', [
                            'primary' => $primary,
                            'used' => $driver,
                        ]);
                    }

                    return $url;
                }
            } catch (Throwable $e) {
                $this->log($job, 'warning', 'Media mirror driver attempt failed', [
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

    private function uploadToCatbox(string $localPath, ?PublishJob $job): ?string
    {
        $response = Http::timeout(120)
            ->attach('fileToUpload', fopen($localPath, 'r'), basename($localPath))
            ->asMultipart()
            ->post('https://catbox.moe/user/api.php', ['reqtype' => 'fileupload']);

        $body = trim((string) $response->body());
        if (! $response->successful() || ! str_starts_with($body, 'https://')) {
            $this->log($job, 'error', 'catbox.moe upload failed', ['status' => $response->status(), 'body' => mb_substr($body, 0, 500)]);

            return null;
        }

        return $body;
    }

    private function uploadToZeroXZero(string $localPath, ?PublishJob $job): ?string
    {
        $response = Http::timeout(120)
            ->attach('file', fopen($localPath, 'r'), basename($localPath))
            ->asMultipart()
            ->post('https://0x0.st');

        $body = trim((string) $response->body());
        if (! $response->successful() || ! str_starts_with($body, 'https://')) {
            $this->log($job, 'error', '0x0.st upload failed', ['status' => $response->status(), 'body' => mb_substr($body, 0, 500)]);

            return null;
        }

        return $body;
    }

    private function uploadToImgbb(string $localPath, string $kind, ?PublishJob $job): ?string
    {
        if ($kind !== 'image') {
            $this->log($job, 'warning', 'imgbb mirror only supports images; falling back to catbox.', ['kind' => $kind]);

            return $this->uploadToCatbox($localPath, $job);
        }

        $key = (string) config('services.media_mirror.imgbb_key', '');
        if ($key === '') {
            $this->log($job, 'error', 'MEDIA_MIRROR_IMGBB_KEY is not set', []);

            return null;
        }

        $response = Http::timeout(120)
            ->asMultipart()
            ->attach('image', fopen($localPath, 'r'), basename($localPath))
            ->post('https://api.imgbb.com/1/upload?key='.$key);

        if (! $response->successful()) {
            $this->log($job, 'error', 'imgbb upload failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $url = $response->json('data.url') ?? $response->json('data.display_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function uploadToCloudinary(string $localPath, string $kind, ?PublishJob $job): ?string
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
            $this->log($job, 'error', 'Cloudinary credentials not configured', []);

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
            $this->log($job, 'error', 'Cloudinary upload failed', ['status' => $response->status(), 'body' => $response->body()]);

            return null;
        }

        $secureUrl = $response->json('secure_url') ?? $response->json('url');

        return is_string($secureUrl) && $secureUrl !== '' ? $secureUrl : null;
    }

    private function log(?PublishJob $job, string $level, string $message, ?array $response = null, ?int $httpStatus = null): void
    {
        if ($job !== null) {
            PublishLog::query()->create([
                'publish_job_id' => $job->id,
                'level' => $level,
                'message' => $message,
                'response' => $response,
                'http_status' => $httpStatus,
            ]);

            return;
        }

        Log::log($level, $message, $response ?? []);
    }
}
