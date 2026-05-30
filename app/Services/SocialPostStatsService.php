<?php

namespace App\Services;

use App\Models\PostTarget;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialPostStatsService
{
    private const META_VERSION = 'v21.0';

    private const HTTP_TIMEOUT = 8;

    private const CONNECT_TIMEOUT = 5;

    private const POOL_SIZE = 6;

    /**
     * @return array{success: bool, likes?: ?int, comments?: ?int, shares?: ?int, reactions?: ?int, error?: string}
     */
    public function fetchStats(PostTarget $target): array
    {
        $account = $target->socialAccount;
        $platformPostId = (string) ($target->platform_post_id ?? '');

        if ($account === null || $platformPostId === '') {
            return ['success' => false, 'error' => 'Missing account or platform post id.'];
        }

        $slug = $target->socialPlatform?->slug ?? $account->platform?->slug;

        try {
            $token = decrypt($account->access_token);
        } catch (Throwable) {
            return ['success' => false, 'error' => 'Could not decrypt access token.'];
        }

        return match ($slug) {
            'facebook' => $this->fetchFacebookStats($platformPostId, $token),
            'instagram' => $this->fetchInstagramStats($platformPostId, $token),
            'linkedin' => $this->fetchLinkedInStats($platformPostId, $token),
            default => ['success' => false, 'error' => "Unsupported platform: {$slug}"],
        };
    }

    /**
     * @return array{success: bool, comments?: array<int, array<string, mixed>>, error?: string}
     */
    public function fetchComments(PostTarget $target, int $limit = 10): array
    {
        $account = $target->socialAccount;
        $platformPostId = (string) ($target->platform_post_id ?? '');

        if ($account === null || $platformPostId === '') {
            return ['success' => false, 'error' => 'Missing account or platform post id.'];
        }

        $slug = $target->socialPlatform?->slug ?? $account->platform?->slug;

        try {
            $token = decrypt($account->access_token);
        } catch (Throwable) {
            return ['success' => false, 'error' => 'Could not decrypt access token.'];
        }

        return match ($slug) {
            'facebook' => $this->fetchFacebookComments($platformPostId, $token, $limit),
            'instagram' => $this->fetchInstagramComments($platformPostId, $token, $limit),
            'linkedin' => ['success' => true, 'comments' => []],
            default => ['success' => false, 'error' => "Unsupported platform: {$slug}"],
        };
    }

    public function syncTargetStats(PostTarget $target): bool
    {
        $result = $this->fetchStats($target);

        if (($result['success'] ?? false) !== true) {
            return false;
        }

        $this->applyStatsToTarget($target, $result);

        return true;
    }

    /**
     * @return array{synced: int, failed: int, errors: array<int, array{target_id: int, account: ?string}>}
     */
    public function syncManyTargets(Collection $targets): array
    {
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($targets->chunk(self::POOL_SIZE) as $chunk) {
            $responses = Http::pool(function (Pool $pool) use ($chunk) {
                foreach ($chunk as $target) {
                    $key = (string) $target->id;
                    $pending = $this->buildStatsHttpRequest($target);

                    if ($pending === null) {
                        continue;
                    }

                    $request = $pool->as($key)
                        ->connectTimeout(self::CONNECT_TIMEOUT)
                        ->timeout(self::HTTP_TIMEOUT);

                    if ($pending['headers'] !== []) {
                        $request = $request->withHeaders($pending['headers']);
                    }

                    $request->get($pending['url'], $pending['query'] ?? []);
                }
            });

            foreach ($chunk as $target) {
                $key = (string) $target->id;
                $response = $responses[$key] ?? null;

                if (! $response instanceof Response) {
                    $failed++;
                    $errors[] = [
                        'target_id' => $target->id,
                        'account' => $target->socialAccount?->account_name,
                    ];

                    continue;
                }

                $result = $this->parseStatsResponse($target, $response);

                if (($result['success'] ?? false) !== true) {
                    $failed++;
                    $errors[] = [
                        'target_id' => $target->id,
                        'account' => $target->socialAccount?->account_name,
                    ];

                    continue;
                }

                $this->applyStatsToTarget($target, $result);
                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function applyStatsToTarget(PostTarget $target, array $result): void
    {
        $slug = $target->socialPlatform?->slug ?? $target->socialAccount?->platform?->slug;

        $target->update([
            'likes_count' => $result['likes'] ?? null,
            'comments_count' => $result['comments'] ?? null,
            'shares_count' => $result['shares'] ?? null,
            'reactions_count' => $slug === 'linkedin'
                ? ($result['reactions'] ?? $result['likes'] ?? null)
                : ($result['reactions'] ?? null),
            'stats_synced_at' => now(),
        ]);
    }

    /**
     * @return array{url: string, query: array<string, mixed>, headers: array<string, string>}|null
     */
    private function buildStatsHttpRequest(PostTarget $target): ?array
    {
        $account = $target->socialAccount;
        $platformPostId = (string) ($target->platform_post_id ?? '');

        if ($account === null || $platformPostId === '') {
            return null;
        }

        try {
            $token = decrypt($account->access_token);
        } catch (Throwable) {
            return null;
        }

        $slug = $target->socialPlatform?->slug ?? $account->platform?->slug;

        return match ($slug) {
            'facebook' => [
                'url' => 'https://graph.facebook.com/'.self::META_VERSION.'/'.$platformPostId,
                'query' => [
                    'fields' => 'likes.summary(true),comments.summary(true),shares',
                    'access_token' => $token,
                ],
                'headers' => [],
            ],
            'instagram' => [
                'url' => 'https://graph.facebook.com/'.self::META_VERSION.'/'.$platformPostId,
                'query' => [
                    'fields' => 'like_count,comments_count',
                    'access_token' => $token,
                ],
                'headers' => [],
            ],
            'linkedin' => [
                'url' => 'https://api.linkedin.com/rest/socialMetadata/'.rawurlencode($platformPostId),
                'query' => [],
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Linkedin-Version' => (string) env('LINKEDIN_API_VERSION', '202406'),
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
            ],
            default => null,
        };
    }

    /**
     * @return array{success: bool, likes?: ?int, comments?: ?int, shares?: ?int, reactions?: ?int, error?: string}
     */
    private function parseStatsResponse(PostTarget $target, Response $response): array
    {
        $slug = $target->socialPlatform?->slug ?? $target->socialAccount?->platform?->slug;

        if (! $response->successful()) {
            return ['success' => false, 'error' => 'Stats request failed.'];
        }

        $data = $response->json() ?? [];

        return match ($slug) {
            'facebook' => [
                'success' => true,
                'likes' => (int) ($data['likes']['summary']['total_count'] ?? 0),
                'comments' => (int) ($data['comments']['summary']['total_count'] ?? 0),
                'shares' => (int) ($data['shares']['count'] ?? 0),
            ],
            'instagram' => [
                'success' => true,
                'likes' => (int) ($data['like_count'] ?? 0),
                'comments' => (int) ($data['comments_count'] ?? 0),
                'shares' => 0,
            ],
            'linkedin' => [
                'success' => true,
                'reactions' => (int) ($data['reactionSummaries'][0]['count'] ?? $data['reactionSummary']['count'] ?? 0),
                'likes' => (int) ($data['reactionSummaries'][0]['count'] ?? $data['reactionSummary']['count'] ?? 0),
                'comments' => (int) ($data['commentSummary']['count'] ?? $data['commentsSummary']['count'] ?? 0),
                'shares' => 0,
            ],
            default => ['success' => false, 'error' => 'Unsupported platform.'],
        };
    }

    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::connectTimeout(self::CONNECT_TIMEOUT)->timeout(self::HTTP_TIMEOUT);
    }

    /**
     * @return array{success: bool, likes?: ?int, comments?: ?int, shares?: ?int, error?: string}
     */
    private function fetchFacebookStats(string $postId, string $token): array
    {
        $response = $this->httpClient()->get(
            'https://graph.facebook.com/'.self::META_VERSION.'/'.$postId,
            [
                'fields' => 'likes.summary(true),comments.summary(true),shares',
                'access_token' => $token,
            ]
        );

        if (! $response->successful()) {
            Log::warning('Facebook post stats failed', ['post_id' => $postId, 'body' => $response->json()]);

            return ['success' => false, 'error' => $response->json('error.message') ?? 'Facebook stats request failed.'];
        }

        $data = $response->json();

        return [
            'success' => true,
            'likes' => (int) ($data['likes']['summary']['total_count'] ?? 0),
            'comments' => (int) ($data['comments']['summary']['total_count'] ?? 0),
            'shares' => (int) ($data['shares']['count'] ?? 0),
        ];
    }

    /**
     * @return array{success: bool, likes?: ?int, comments?: ?int, shares?: ?int, error?: string}
     */
    private function fetchInstagramStats(string $mediaId, string $token): array
    {
        $response = $this->httpClient()->get(
            'https://graph.facebook.com/'.self::META_VERSION.'/'.$mediaId,
            [
                'fields' => 'like_count,comments_count',
                'access_token' => $token,
            ]
        );

        if (! $response->successful()) {
            Log::warning('Instagram post stats failed', ['media_id' => $mediaId, 'body' => $response->json()]);

            return ['success' => false, 'error' => $response->json('error.message') ?? 'Instagram stats request failed.'];
        }

        $data = $response->json();

        return [
            'success' => true,
            'likes' => (int) ($data['like_count'] ?? 0),
            'comments' => (int) ($data['comments_count'] ?? 0),
            'shares' => 0,
        ];
    }

    /**
     * @return array{success: bool, likes?: ?int, comments?: ?int, shares?: ?int, reactions?: ?int, error?: string}
     */
    private function fetchLinkedInStats(string $postUrn, string $token): array
    {
        $encoded = rawurlencode($postUrn);
        $version = (string) env('LINKEDIN_API_VERSION', '202406');

        $response = $this->httpClient()
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Linkedin-Version' => $version,
                'X-Restli-Protocol-Version' => '2.0.0',
            ])
            ->get('https://api.linkedin.com/rest/socialMetadata/'.$encoded);

        if (! $response->successful()) {
            Log::warning('LinkedIn post stats failed', ['post_urn' => $postUrn, 'status' => $response->status(), 'body' => $response->json()]);

            return ['success' => false, 'error' => 'LinkedIn stats unavailable (check API permissions).'];
        }

        $data = $response->json() ?? [];

        $reactions = (int) ($data['reactionSummaries'][0]['count'] ?? $data['reactionSummary']['count'] ?? 0);
        $comments = (int) ($data['commentSummary']['count'] ?? $data['commentsSummary']['count'] ?? 0);

        return [
            'success' => true,
            'reactions' => $reactions,
            'likes' => $reactions,
            'comments' => $comments,
            'shares' => 0,
        ];
    }

    /**
     * @return array{success: bool, comments?: array<int, array<string, mixed>>, error?: string}
     */
    private function fetchFacebookComments(string $postId, string $token, int $limit): array
    {
        $response = $this->httpClient()->get(
            'https://graph.facebook.com/'.self::META_VERSION.'/'.$postId.'/comments',
            [
                'fields' => 'from{name},message,created_time,comments.limit(5){from{name},message,created_time}',
                'limit' => $limit,
                'access_token' => $token,
            ]
        );

        if (! $response->successful()) {
            return ['success' => false, 'error' => $response->json('error.message') ?? 'Could not load Facebook comments.'];
        }

        $items = $response->json('data') ?? [];

        return [
            'success' => true,
            'comments' => collect($items)->map(fn (array $row) => $this->mapFacebookComment($row))->values()->all(),
        ];
    }

    /**
     * @return array{author: string, text: string, date: string, replies: array<int, array<string, string>>}
     */
    private function mapFacebookComment(array $row): array
    {
        $replies = collect($row['comments']['data'] ?? [])
            ->map(fn (array $reply) => [
                'author' => (string) ($reply['from']['name'] ?? 'Unknown'),
                'text' => (string) ($reply['message'] ?? ''),
                'date' => $this->formatCommentDate($reply['created_time'] ?? null),
            ])
            ->values()
            ->all();

        return [
            'author' => (string) ($row['from']['name'] ?? 'Unknown'),
            'text' => (string) ($row['message'] ?? ''),
            'date' => $this->formatCommentDate($row['created_time'] ?? null),
            'replies' => $replies,
        ];
    }

    /**
     * @return array{success: bool, comments?: array<int, array<string, mixed>>, error?: string}
     */
    private function fetchInstagramComments(string $mediaId, string $token, int $limit): array
    {
        $response = $this->httpClient()->get(
            'https://graph.facebook.com/'.self::META_VERSION.'/'.$mediaId.'/comments',
            [
                'fields' => 'username,text,timestamp,replies{username,text,timestamp}',
                'limit' => $limit,
                'access_token' => $token,
            ]
        );

        if (! $response->successful()) {
            return ['success' => false, 'error' => $response->json('error.message') ?? 'Could not load Instagram comments.'];
        }

        $items = $response->json('data') ?? [];

        return [
            'success' => true,
            'comments' => collect($items)->map(fn (array $row) => $this->mapInstagramComment($row))->values()->all(),
        ];
    }

    /**
     * @return array{author: string, text: string, date: string, replies: array<int, array<string, string>>}
     */
    private function mapInstagramComment(array $row): array
    {
        $replies = collect($row['replies']['data'] ?? [])
            ->map(fn (array $reply) => [
                'author' => (string) ($reply['username'] ?? 'Unknown'),
                'text' => (string) ($reply['text'] ?? ''),
                'date' => $this->formatCommentDate($reply['timestamp'] ?? null),
            ])
            ->values()
            ->all();

        return [
            'author' => (string) ($row['username'] ?? 'Unknown'),
            'text' => (string) ($row['text'] ?? ''),
            'date' => $this->formatCommentDate($row['timestamp'] ?? null),
            'replies' => $replies,
        ];
    }

    private function formatCommentDate(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d H:i');
        } catch (Throwable) {
            return $value;
        }
    }
}
