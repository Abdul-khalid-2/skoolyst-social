<?php

namespace App\Services;

use App\Models\SocialAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocialPostService
{
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
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function postToFacebookAccount(SocialAccount $account, string $message, ?string $link = null): array
    {
        $pageId = (string) ($account->platform_page_id ?: $account->account_handle);
        $token = $this->resolveToken((string) $account->access_token);

        if ($pageId === '' || $token === '') {
            return ['success' => false, 'error' => 'Facebook Page credentials are missing for this workspace.'];
        }

        return $this->postToFacebookPage($pageId, $token, $message, $link);
    }

    private function postToFacebookPage(string $pageId, string $token, string $message, ?string $link = null): array
    {
        $version = (string) env('META_API_VERSION', config('services.facebook.graph_version', 'v24.0'));
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
            Log::error('Facebook workspace publish failed', [
                'error' => $e->getMessage(),
                'page_id' => $pageId,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function postToInstagram(string $imageUrl, string $caption): array
    {
        $version = (string) env('META_API_VERSION', 'v21.0');
        $igUserId = (string) env('IG_USER_ID', '');
        $token = (string) env('IG_ACCESS_TOKEN', '');

        if ($igUserId === '' || $token === '') {
            return ['success' => false, 'error' => 'Instagram credentials are missing.'];
        }

        $client = new Client(['base_uri' => "https://graph.facebook.com/{$version}/", 'timeout' => 20]);

        try {
            $create = $client->post("{$igUserId}/media", [
                'form_params' => [
                    'image_url' => $imageUrl,
                    'caption' => $caption,
                    'access_token' => $token,
                ],
            ]);
            $createJson = json_decode((string) $create->getBody(), true);
            $creationId = is_array($createJson) ? ($createJson['id'] ?? null) : null;
            if (! is_string($creationId) || $creationId === '') {
                return ['success' => false, 'error' => 'Instagram media creation failed.'];
            }

            $publish = $client->post("{$igUserId}/media_publish", [
                'form_params' => [
                    'creation_id' => $creationId,
                    'access_token' => $token,
                ],
            ]);
            $publishJson = json_decode((string) $publish->getBody(), true);
            if (! is_array($publishJson) || ! isset($publishJson['id'])) {
                return ['success' => false, 'error' => 'Instagram publish response missing id.'];
            }

            return ['success' => true, 'post_id' => (string) $publishJson['id']];
        } catch (GuzzleException $e) {
            Log::error('Instagram publish failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
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

