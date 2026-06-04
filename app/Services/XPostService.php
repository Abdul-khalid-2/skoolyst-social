<?php

namespace App\Services;

use App\Models\Post;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Traits\ResolvesMediaPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class XPostService
{
    use ResolvesMediaPath;

    private const API_BASE    = 'https://api.twitter.com/2/';
    private const UPLOAD_BASE = 'https://upload.twitter.com/1.1/';
    private const HTTP_TIMEOUT    = 30;
    private const CONNECT_TIMEOUT = 10;

    /**
     * @return array{success: bool, post_id?: string, error?: string}
     */
    public function publishPost(SocialAccount $account, Post $post, PostTarget $target): array
    {
        try {
            $token = decrypt($account->access_token);
        } catch (Throwable $e) {
            Log::error('XPostService: could not decrypt access token', ['account_id' => $account->id, 'error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Could not decrypt X access token.'];
        }

        $text = mb_substr((string) $post->caption, 0, 280);
        $payload = ['text' => $text];

        if ($post->postMedia->first() !== null) {
            Log::info('XPostService: media upload skipped pending OAuth 1.0a implementation; posting text only', [
                'post_id' => $post->id,
            ]);
        }

        $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::HTTP_TIMEOUT)
            ->withToken($token)
            ->post(self::API_BASE . 'tweets', $payload);

        if ($response->successful()) {
            $tweetId = (string) ($response->json('data.id') ?? '');

            return ['success' => true, 'post_id' => $tweetId];
        }

        $detail = $response->json('detail') ?? $response->json('errors.0.message') ?? $response->body();
        Log::error('XPostService: tweet creation failed', [
            'post_id' => $post->id,
            'status'  => $response->status(),
            'detail'  => $detail,
            'body'    => $response->json(),
        ]);

        if ($response->status() === 402) {
            return [
                'success' => false,
                'error' => 'X API monthly write limit reached. Please try again next month or upgrade your X developer plan.',
            ];
        }

        return ['success' => false, 'error' => "X API error: {$detail}"];
    }

    /**
     * @return array{success: bool, media_id?: string, error?: string}
     */
    private function uploadMedia(string $token, string $filePath): array
    {
        try {
            $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::HTTP_TIMEOUT)
                ->withToken($token)
                ->attach('media', file_get_contents($filePath), basename($filePath))
                ->post(self::UPLOAD_BASE . 'media/upload.json');

            if ($response->successful()) {
                return ['success' => true, 'media_id' => (string) ($response->json('media_id_string') ?? '')];
            }

            $body = trim((string) $response->body());
            $detail = $response->json('detail') ?? $response->json('errors.0.message') ?? ($body !== '' ? $body : 'HTTP '.$response->status());

            return ['success' => false, 'error' => 'X media upload failed: '.$detail];
        } catch (Throwable $e) {
            return ['success' => false, 'error' => 'X media upload exception: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, likes?: int, comments?: int, shares?: int, reactions?: int, error?: string}
     */
    public function fetchTweetStats(string $tweetId, string $token): array
    {
        $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::HTTP_TIMEOUT)
            ->withToken($token)
            ->get(self::API_BASE . "tweets/{$tweetId}", ['tweet.fields' => 'public_metrics']);

        if (! $response->successful()) {
            Log::warning('XPostService: fetchTweetStats failed', [
                'tweet_id' => $tweetId,
                'status'   => $response->status(),
                'body'     => $response->json(),
            ]);

            return ['success' => false, 'error' => 'Could not fetch X tweet stats.'];
        }

        $metrics = $response->json('data.public_metrics') ?? [];
        $likes     = (int) ($metrics['like_count'] ?? 0);
        $replies   = (int) ($metrics['reply_count'] ?? 0);
        $retweets  = (int) ($metrics['retweet_count'] ?? 0);
        $bookmarks = (int) ($metrics['bookmark_count'] ?? 0);

        return [
            'success'   => true,
            'likes'     => $likes,
            'comments'  => $replies,
            'shares'    => $retweets,
            'reactions' => $likes + $bookmarks,
        ];
    }

    /**
     * @return array{success: bool, comments?: array<int, array<string, mixed>>, error?: string}
     */
    public function fetchReplies(string $tweetId, string $token, int $maxResults = 10): array
    {
        $response = Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::HTTP_TIMEOUT)
            ->withToken($token)
            ->get(self::API_BASE . 'tweets/search/recent', [
                'query'        => "conversation_id:{$tweetId} is:reply",
                'max_results'  => min($maxResults, 100),
                'tweet.fields' => 'author_id,created_at,text',
                'expansions'   => 'author_id',
                'user.fields'  => 'name,username',
            ]);

        if (! $response->successful()) {
            $error = $response->json('detail') ?? $response->json('errors.0.message') ?? $response->body();
            Log::warning('XPostService: fetchReplies failed', [
                'tweet_id' => $tweetId,
                'status'   => $response->status(),
                'error'    => $error,
            ]);

            return ['success' => false, 'error' => "X API error: {$error}"];
        }

        $tweets = $response->json('data') ?? [];
        $includes = $response->json('includes.users') ?? [];

        $users = [];
        foreach ($includes as $u) {
            $users[(string) ($u['id'] ?? '')] = $u;
        }

        $comments = [];
        foreach ($tweets as $tweet) {
            $authorId = (string) ($tweet['author_id'] ?? '');
            $authorName = $users[$authorId]['name'] ?? 'Unknown';
            $createdAt = $tweet['created_at'] ?? null;

            $comments[] = [
                'platform_comment_id' => (string) ($tweet['id'] ?? ''),
                'author'              => $authorName,
                'text'                => (string) ($tweet['text'] ?? ''),
                'date'                => is_string($createdAt) ? $createdAt : '',
                'replies'             => [],
            ];
        }

        return ['success' => true, 'comments' => $comments];
    }
}
