<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PublishJob;
use App\Models\PublishLog;
use App\Models\SocialAccount;
use App\Models\PostTarget;
use App\Support\SocialPublishErrorFormatter;
use Illuminate\Support\Facades\Http;
use Throwable;

class InstagramPostService
{
    public function __construct(
        private readonly PublicMediaUrlService $publicMediaUrlService,
    ) {}

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
                $result = $this->publishVideo($version, $igUserId, $token, $post->caption, $mediaUrl, $job);
            } else {
                $result = $this->publishImage($version, $igUserId, $token, $post->caption, $mediaUrl, $job);
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

            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($e->getMessage())];
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
        PublishJob $job
    ): array {
        $publicUrl = $this->publicMediaUrlService->prepare($mediaUrl, 'image', $job);
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

            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($this->extractGraphErrorMessage($create->json(), $create->body()))];
        }

        $creationId = $create->json('id');
        if (! is_string($creationId) || $creationId === '') {
            return ['success' => false, 'error' => 'Instagram media creation response missing id.'];
        }

        $poll = $this->waitForContainerFinished($version, $creationId, $token, $job);
        if (! $poll['success']) {
            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($poll['error'] ?? 'Instagram container did not finish.')];
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
        PublishJob $job
    ): array {
        $publicUrl = $this->publicMediaUrlService->prepare($mediaUrl, 'video', $job);
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

            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($this->extractGraphErrorMessage($create->json(), $create->body()))];
        }

        $creationId = $create->json('id');
        if (! is_string($creationId) || $creationId === '') {
            return ['success' => false, 'error' => 'Instagram Reels container response missing id.'];
        }

        $poll = $this->waitForContainerFinished($version, $creationId, $token, $job);
        if (! $poll['success']) {
            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($poll['error'] ?? 'Instagram Reels container did not finish.')];
        }

        return $this->publishCreation($version, $igUserId, $token, $creationId, $job);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    private function waitForContainerFinished(string $version, string $creationId, string $token, PublishJob $job): array
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
                $this->writeLog($job, 'warning', 'Instagram container status check failed', [
                    'body' => $statusRes->json() ?? $statusRes->body(),
                    'attempt' => $attempt + 1,
                ], $statusRes->status());

                if ($attempt === $maxAttempts - 1) {
                    return ['success' => false, 'error' => 'Could not read Instagram media status.'];
                }

                continue;
            }

            $code = $statusRes->json('status_code');
            $this->writeLog($job, 'info', 'Instagram container status', ['status_code' => $code, 'attempt' => $attempt + 1]);

            if ($code === 'FINISHED') {
                return ['success' => true];
            }

            if ($code === 'ERROR') {
                return ['success' => false, 'error' => 'Instagram reported ERROR while processing media.'];
            }
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

            return ['success' => false, 'error' => SocialPublishErrorFormatter::format($this->extractGraphErrorMessage($publish->json(), $publish->body()))];
        }

        $postId = $publish->json('id');
        if (! is_string($postId) || $postId === '') {
            return ['success' => false, 'error' => 'Instagram publish response missing id.'];
        }

        $this->writeLog($job, 'info', 'Instagram publish succeeded', ['media_id' => $postId]);

        return ['success' => true, 'post_id' => $postId];
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
