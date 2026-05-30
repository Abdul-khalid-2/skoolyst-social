<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Support\SocialPublishErrorFormatter;
use App\Traits\ResolvesMediaPath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialPostService
{
    use ResolvesMediaPath;

    public function __construct(
        private readonly PublicMediaUrlService $publicMediaUrlService,
    ) {}

    public function postToFacebook(string $message, ?string $link = null): array
    {
        $version = (string) env('META_API_VERSION', 'v21.0');
        $pageId = (string) env('FB_PAGE_ID', '');
        $token = (string) env('FB_PAGE_ACCESS_TOKEN', '');

        if ($pageId === '' || $token === '') {
            return ['success' => false, 'error' => 'Facebook credentials are missing.'];
        }

        $client = new Client(['base_uri' => "https://graph.facebook.com/{$version}/", 'timeout' => 20]);
        $form = [
            'message' => $message,
            'access_token' => $token,
        ];
        if ($link) {
            $form['link'] = $link;
        }

        try {
            $res = $client->post("{$pageId}/feed", ['form_params' => $form]);
            $json = json_decode((string) $res->getBody(), true);

            if (! is_array($json) || ! isset($json['id'])) {
                return ['success' => false, 'error' => 'Facebook response missing post id.'];
            }

            return ['success' => true, 'post_id' => (string) $json['id']];
        } catch (GuzzleException $e) {
            Log::error('Facebook publish failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($e->getMessage())];
        }
    }

    public function postToFacebookAccount(SocialAccount $account, string $message, ?string $link = null, ?string $mediaUrl = null, ?string $mediaType = null): array
    {
        $pageId = (string) ($account->platform_page_id ?: $account->account_handle);
        $token = $this->resolveToken((string) $account->access_token);

        if ($pageId === '' || $token === '') {
            return ['success' => false, 'error' => 'Facebook Page credentials are missing for this workspace.'];
        }

        return $this->postToFacebookPage($pageId, $token, $message, $link, $mediaUrl, $mediaType);
    }

    private function postToFacebookPage(string $pageId, string $token, string $message, ?string $link = null, ?string $mediaUrl = null, ?string $mediaType = null): array
    {
        $version = (string) env('META_API_VERSION', config('services.facebook.graph_version', 'v24.0'));
        $graphBase = "https://graph.facebook.com/{$version}/";

        if ($mediaUrl) {
            $path = $this->resolveMediaPath($mediaUrl);

            Log::info('Facebook publish media debug', [
                'mediaUrl' => $mediaUrl,
                'resolvedPath' => $path,
                'fileExists' => $path ? file_exists($path) : false,
                'mediaType' => $mediaType,
            ]);

            if ($path !== null && file_exists($path)) {
                if ($mediaType === 'video') {
                    return $this->publishFacebookVideo($graphBase, $pageId, $token, $message, $path);
                }

                return $this->publishFacebookPhoto($graphBase, $pageId, $token, $message, $mediaUrl, $path);
            }

            Log::error('Media file not found', [
                'mediaUrl' => $mediaUrl,
                'resolvedPath' => $path,
            ]);

            return [
                'success' => false,
                'error' => 'Media file not found. URL: '.$mediaUrl.' | Path tried: '.($path ?? 'null'),
            ];
        }

        $timeout = (int) config('services.social_publish.facebook_image_timeout', 120);

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 3000)
                ->asForm()
                ->post("{$graphBase}{$pageId}/feed", array_filter([
                    'message' => $message,
                    'link' => $link,
                    'access_token' => $token,
                ]));

            return $this->parseFacebookResponse($response->json(), $response->body(), $pageId, "{$pageId}/feed", $mediaUrl);
        } catch (Throwable $e) {
            return $this->facebookFailure($e->getMessage(), $pageId, "{$pageId}/feed", $mediaUrl);
        }
    }

    /**
     * @return array{success: bool, post_id?: string, error?: string}
     */
    private function publishFacebookPhoto(string $graphBase, string $pageId, string $token, string $message, string $mediaUrl, string $path): array
    {
        $this->compressImageIfNeeded($path);

        $publicUrl = $this->publicMediaUrlService->prepare($mediaUrl, 'image');
        if ($publicUrl === null) {
            return [
                'success' => false,
                'error' => 'Could not produce a public HTTPS URL for Facebook to fetch. Ensure storage is linked or configure MEDIA_MIRROR_DRIVER.',
            ];
        }

        $timeout = (int) config('services.social_publish.facebook_image_timeout', 120);
        $endpoint = "{$pageId}/photos";

        Log::info('Facebook photo publish via URL', ['public_url' => $publicUrl, 'endpoint' => $endpoint]);

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 3000)
                ->asForm()
                ->post("{$graphBase}{$endpoint}", [
                    'url' => $publicUrl,
                    'caption' => $message,
                    'access_token' => $token,
                ]);

            return $this->parseFacebookResponse($response->json(), $response->body(), $pageId, $endpoint, $mediaUrl);
        } catch (Throwable $e) {
            return $this->facebookFailure($e->getMessage(), $pageId, $endpoint, $mediaUrl);
        }
    }

    /**
     * @return array{success: bool, post_id?: string, error?: string}
     */
    private function publishFacebookVideo(string $graphBase, string $pageId, string $token, string $message, string $path): array
    {
        $timeout = (int) config('services.social_publish.facebook_video_timeout', 600);
        $endpoint = "{$pageId}/videos";

        try {
            $response = Http::timeout($timeout)
                ->retry(2, 3000)
                ->attach('source', fopen($path, 'r'), basename($path))
                ->post("{$graphBase}{$endpoint}", [
                    'access_token' => $token,
                    'description' => $message,
                ]);

            return $this->parseFacebookResponse($response->json(), $response->body(), $pageId, $endpoint, null);
        } catch (Throwable $e) {
            return $this->facebookFailure($e->getMessage(), $pageId, $endpoint, null);
        }
    }

    /**
     * @param  mixed  $json
     * @return array{success: bool, post_id?: string, error?: string}
     */
    private function parseFacebookResponse(mixed $json, string $rawBody, string $pageId, string $endpoint, ?string $mediaUrl): array
    {
        if (is_array($json) && isset($json['id'])) {
            $postId = $json['post_id'] ?? $json['id'];

            return ['success' => true, 'post_id' => (string) $postId];
        }

        $rawError = is_array($json) ? json_encode($json) : $rawBody;
        Log::error('Facebook workspace publish failed', [
            'error' => $rawError,
            'page_id' => $pageId,
            'endpoint' => $endpoint,
            'media_url' => $mediaUrl,
        ]);

        return ['success' => false, 'error' => SocialPublishErrorFormatter::format($rawError)];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function facebookFailure(string $errorMsg, string $pageId, string $endpoint, ?string $mediaUrl): array
    {
        Log::error('Facebook workspace publish failed', [
            'error' => $errorMsg,
            'page_id' => $pageId,
            'endpoint' => $endpoint,
            'media_url' => $mediaUrl,
        ]);

        return ['success' => false, 'error' => SocialPublishErrorFormatter::format($errorMsg)];
    }

    private function compressImageIfNeeded(string $path, int $maxSizeBytes = 4194304): void
    {
        if (! is_file($path) || filesize($path) <= $maxSizeBytes) {
            return;
        }

        if (! function_exists('imagecreatefromjpeg')) {
            Log::warning('GD extension unavailable; skipping Facebook image compression.', ['path' => $path]);

            return;
        }

        try {
            $info = getimagesize($path);
            if ($info === false) {
                return;
            }

            $mime = $info['mime'] ?? '';
            $image = match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($path),
                'image/png' => imagecreatefrompng($path),
                'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
                default => false,
            };

            if ($image === false) {
                return;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $maxWidth = 1200;

            if ($width > $maxWidth) {
                $newHeight = (int) round($height * ($maxWidth / $width));
                $resized = imagecreatetruecolor($maxWidth, $newHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resized;
            }

            match ($mime) {
                'image/jpeg' => imagejpeg($image, $path, 80),
                'image/png' => imagepng($image, $path, 6),
                'image/webp' => function_exists('imagewebp') ? imagewebp($image, $path, 80) : null,
                default => null,
            };

            imagedestroy($image);

            Log::info('Compressed image for Facebook publish', [
                'path' => $path,
                'new_size' => filesize($path),
            ]);
        } catch (Throwable $e) {
            Log::warning('Facebook image compression failed', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    public function postToInstagram(string $imageUrl, string $caption): array
    {
        $version = (string) env('META_API_VERSION', config('services.facebook.graph_version', 'v24.0'));
        $igUserId = (string) env('IG_USER_ID', '');
        $token = (string) env('IG_ACCESS_TOKEN', '');

        if ($igUserId === '' || $token === '') {
            return ['success' => false, 'error' => 'Instagram credentials are missing.'];
        }

        $publicUrl = $this->publicMediaUrlService->prepare($imageUrl, 'image');
        if ($publicUrl === null) {
            return ['success' => false, 'error' => 'Could not produce a public HTTPS URL for Instagram.'];
        }

        try {
            $create = Http::timeout(60)->asForm()->post(
                "https://graph.facebook.com/{$version}/{$igUserId}/media",
                [
                    'image_url' => $publicUrl,
                    'caption' => $caption,
                    'access_token' => $token,
                ]
            );

            if (! $create->successful()) {
                return ['success' => false, 'error' => SocialPublishErrorFormatter::format($create->body())];
            }

            $creationId = $create->json('id');
            if (! is_string($creationId) || $creationId === '') {
                return ['success' => false, 'error' => 'Instagram media creation failed.'];
            }

            $poll = $this->waitForInstagramContainerFinished($version, $creationId, $token);
            if (! $poll['success']) {
                return ['success' => false, 'error' => $poll['error'] ?? 'Instagram container did not finish.'];
            }

            $publish = Http::timeout(60)->asForm()->post(
                "https://graph.facebook.com/{$version}/{$igUserId}/media_publish",
                [
                    'creation_id' => $creationId,
                    'access_token' => $token,
                ]
            );

            if (! $publish->successful()) {
                return ['success' => false, 'error' => SocialPublishErrorFormatter::format($publish->body())];
            }

            $postId = $publish->json('id');
            if (! is_string($postId) || $postId === '') {
                return ['success' => false, 'error' => 'Instagram publish response missing id.'];
            }

            return ['success' => true, 'post_id' => $postId];
        } catch (Throwable $e) {
            Log::error('Instagram publish failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($e->getMessage())];
        }
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function waitForInstagramContainerFinished(string $version, string $creationId, string $token): array
    {
        $maxAttempts = (int) config('services.social_publish.instagram_status_max_attempts', 10);
        $sleepSeconds = (int) config('services.social_publish.instagram_status_sleep_seconds', 5);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            if ($attempt > 0) {
                sleep($sleepSeconds);
            }

            $statusRes = Http::timeout(30)->get(
                "https://graph.facebook.com/{$version}/{$creationId}",
                [
                    'fields' => 'status_code',
                    'access_token' => $token,
                ]
            );

            if (! $statusRes->successful()) {
                if ($attempt === $maxAttempts - 1) {
                    return ['success' => false, 'error' => 'Could not read Instagram media status.'];
                }

                continue;
            }

            $code = $statusRes->json('status_code');

            if ($code === 'FINISHED') {
                return ['success' => true];
            }

            if ($code === 'ERROR') {
                return ['success' => false, 'error' => 'Instagram reported ERROR while processing media.'];
            }
        }

        return ['success' => false, 'error' => 'Timeout waiting for Instagram media container to finish processing.'];
    }

    public function commentOnFacebook(string $postId, string $message): array
    {
        $version = (string) env('META_API_VERSION', 'v21.0');
        $token = (string) env('FB_PAGE_ACCESS_TOKEN', '');
        $client = new Client(['base_uri' => "https://graph.facebook.com/{$version}/", 'timeout' => 20]);

        try {
            $res = $client->post("{$postId}/comments", [
                'form_params' => ['message' => $message, 'access_token' => $token],
            ]);
            $json = json_decode((string) $res->getBody(), true);

            return isset($json['id'])
                ? ['success' => true, 'comment_id' => (string) $json['id']]
                : ['success' => false, 'error' => 'Facebook comment failed.'];
        } catch (GuzzleException $e) {
            Log::error('Facebook comment failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function commentOnInstagram(string $mediaId, string $message): array
    {
        $version = (string) env('META_API_VERSION', 'v21.0');
        $token = (string) env('IG_ACCESS_TOKEN', '');
        $client = new Client(['base_uri' => "https://graph.facebook.com/{$version}/", 'timeout' => 20]);

        try {
            $res = $client->post("{$mediaId}/comments", [
                'form_params' => ['message' => $message, 'access_token' => $token],
            ]);
            $json = json_decode((string) $res->getBody(), true);

            return isset($json['id'])
                ? ['success' => true, 'comment_id' => (string) $json['id']]
                : ['success' => false, 'error' => 'Instagram comment failed.'];
        } catch (GuzzleException $e) {
            Log::error('Instagram comment failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function replyToComment(string $commentId, string $message, string $platform): array
    {
        $version = (string) env('META_API_VERSION', 'v21.0');
        $client = new Client(['base_uri' => "https://graph.facebook.com/{$version}/", 'timeout' => 20]);

        try {
            if ($platform === 'facebook') {
                $token = (string) env('FB_PAGE_ACCESS_TOKEN', '');
                $res = $client->post("{$commentId}/comments", [
                    'form_params' => ['message' => $message, 'access_token' => $token],
                ]);
            } else {
                $token = (string) env('IG_ACCESS_TOKEN', '');
                $res = $client->post("{$commentId}/replies", [
                    'form_params' => ['message' => $message, 'access_token' => $token],
                ]);
            }
            $json = json_decode((string) $res->getBody(), true);

            return isset($json['id'])
                ? ['success' => true, 'reply_id' => (string) $json['id']]
                : ['success' => false, 'error' => 'Reply failed.'];
        } catch (GuzzleException $e) {
            Log::error('Reply to comment failed', ['error' => $e->getMessage(), 'platform' => $platform]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
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
