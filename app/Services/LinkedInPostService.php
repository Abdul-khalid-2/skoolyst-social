<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Traits\ResolvesMediaPath;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInPostService
{
    use ResolvesMediaPath;
    private const LINKEDIN_VERSION = '202406';
    private const LINKEDIN_API_BASE = 'https://api.linkedin.com/v2/';

    public function publishTextPost(
        SocialAccount $account,
        string $text,
        ?string $linkUrl = null
    ): array {
        $token = $this->resolveToken((string) $account->access_token);
        $authorUrn = $this->resolveAuthorUrn($account);

        if ($token === '' || $authorUrn === '') {
            return ['success' => false, 'error' => 'LinkedIn access token or author URN is missing.'];
        }

        try {
            $payload = [
                'author' => $authorUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $text,
                        ],
                        'shareMediaCategory' => 'NONE',
                    ],
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                ],
            ];

            if ($linkUrl) {
                $payload['specificContent']['com.linkedin.ugc.ShareContent']['media'] = [
                    [
                        'status' => 'READY',
                        'description' => [
                            'text' => substr($text, 0, 200),
                        ],
                        'originalUrl' => $linkUrl,
                    ],
                ];
                $payload['specificContent']['com.linkedin.ugc.ShareContent']['shareMediaCategory'] = 'ARTICLE';
            }

            $response = $this->client($token)->post(self::LINKEDIN_API_BASE.'ugcPosts', $payload);

            if (! $response->successful()) {
                Log::error('LinkedIn publish failed', [
                    'author' => $authorUrn,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return ['success' => false, 'error' => 'LinkedIn publish failed with status '.$response->status().'.'];
            }

            $body = $response->json();

            if (! is_array($body) || ! isset($body['id'])) {
                Log::error('LinkedIn publish failed - missing post id', [
                    'author' => $authorUrn,
                    'response' => $body,
                ]);

                return ['success' => false, 'error' => 'LinkedIn response missing post id.'];
            }

            return ['success' => true, 'post_id' => (string) $body['id']];
        } catch (ConnectionException $e) {
            Log::error('LinkedIn publish failed', [
                'error' => $e->getMessage(),
                'author' => $authorUrn,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function publishImagePost(
        SocialAccount $account,
        string $text,
        string $imageUrl
    ): array {
        $token = $this->resolveToken((string) $account->access_token);
        $authorUrn = $this->resolveAuthorUrn($account);

        if ($token === '' || $authorUrn === '') {
            return ['success' => false, 'error' => 'LinkedIn access token or author URN is missing.'];
        }

        try {
            $uploadResponse = $this->client($token)->post(
                self::LINKEDIN_API_BASE.'assets?action=registerUpload',
                [
                    'registerUploadRequest' => [
                        'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                        'owner' => $authorUrn,
                        'serviceRelationships' => [
                            [
                                'relationshipType' => 'OWNER',
                                'identifier' => 'urn:li:userGeneratedContent',
                            ],
                        ],
                    ],
                ]
            );

            $uploadBody = $uploadResponse->json();

            if (! is_array($uploadBody) || ! isset($uploadBody['value']['uploadMechanism'])) {
                Log::error('LinkedIn asset upload registration failed', ['response' => $uploadBody]);

                return ['success' => false, 'error' => 'LinkedIn asset upload registration failed.'];
            }

            $uploadUrl = $uploadBody['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn = $uploadBody['value']['asset'];

            $this->uploadImageToLinkedIn($uploadUrl, $imageUrl);

            $postResponse = $this->client($token)->post(
                self::LINKEDIN_API_BASE.'ugcPosts',
                [
                    'author' => $authorUrn,
                    'lifecycleState' => 'PUBLISHED',
                    'specificContent' => [
                        'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => [
                                'text' => $text,
                            ],
                            'shareMediaCategory' => 'IMAGE',
                            'media' => [
                                [
                                    'status' => 'READY',
                                    'media' => $assetUrn,
                                ],
                            ],
                        ],
                    ],
                    'visibility' => [
                        'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                    ],
                ]
            );

            $postBody = $postResponse->json();

            if (! is_array($postBody) || ! isset($postBody['id'])) {
                Log::error('LinkedIn image post failed - missing post id', [
                    'author' => $authorUrn,
                    'response' => $postBody,
                ]);

                return ['success' => false, 'error' => 'LinkedIn response missing post id.'];
            }

            return ['success' => true, 'post_id' => (string) $postBody['id']];
        } catch (ConnectionException $e) {
            Log::error('LinkedIn image publish failed', [
                'error' => $e->getMessage(),
                'author' => $authorUrn,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function publishVideoPost(
        SocialAccount $account,
        string $text,
        string $videoUrl
    ): array {
        $token = $this->resolveToken((string) $account->access_token);
        $authorUrn = $this->resolveAuthorUrn($account);

        if ($token === '' || $authorUrn === '') {
            return ['success' => false, 'error' => 'LinkedIn access token or author URN is missing.'];
        }

        try {
            $uploadResponse = $this->client($token)->post(
                self::LINKEDIN_API_BASE.'assets?action=registerUpload',
                [
                    'registerUploadRequest' => [
                        'recipes' => ['urn:li:digitalmediaRecipe:feedshare-video'],
                        'owner' => $authorUrn,
                        'serviceRelationships' => [
                            [
                                'relationshipType' => 'OWNER',
                                'identifier' => 'urn:li:userGeneratedContent',
                            ],
                        ],
                    ],
                ]
            );

            $uploadBody = $uploadResponse->json();

            if (! is_array($uploadBody) || ! isset($uploadBody['value']['uploadMechanism'])) {
                Log::error('LinkedIn video upload registration failed', ['response' => $uploadBody]);

                return ['success' => false, 'error' => 'LinkedIn video upload registration failed.'];
            }

            $uploadUrl = $uploadBody['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn = $uploadBody['value']['asset'];

            $this->uploadVideoToLinkedIn($uploadUrl, $videoUrl);

            $postResponse = $this->client($token)->post(
                self::LINKEDIN_API_BASE.'ugcPosts',
                [
                    'author' => $authorUrn,
                    'lifecycleState' => 'PUBLISHED',
                    'specificContent' => [
                        'com.linkedin.ugc.ShareContent' => [
                            'shareCommentary' => [
                                'text' => $text,
                            ],
                            'shareMediaCategory' => 'VIDEO',
                            'media' => [
                                [
                                    'status' => 'READY',
                                    'media' => $assetUrn,
                                ],
                            ],
                        ],
                    ],
                    'visibility' => [
                        'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
                    ],
                ]
            );

            $postBody = $postResponse->json();

            if (! is_array($postBody) || ! isset($postBody['id'])) {
                Log::error('LinkedIn video post failed - missing post id', [
                    'author' => $authorUrn,
                    'response' => $postBody,
                ]);

                return ['success' => false, 'error' => 'LinkedIn response missing post id.'];
            }

            return ['success' => true, 'post_id' => (string) $postBody['id']];
        } catch (ConnectionException $e) {
            Log::error('LinkedIn video publish failed', [
                'error' => $e->getMessage(),
                'author' => $authorUrn,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function client(string $token): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'LinkedIn-Version' => self::LINKEDIN_VERSION,
            'Content-Type' => 'application/json',
        ]);
    }

    private function uploadImageToLinkedIn(string $uploadUrl, string $imageUrl): void
    {
        $path = $this->resolveMediaPath($imageUrl);

        if ($path === null || ! file_exists($path)) {
            throw new \RuntimeException(
                'Image file not found. URL: '.$imageUrl.' | Resolved path: '.($path ?? 'null')
            );
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';

        Http::withBody(fopen($path, 'r'), $mime)
            ->withoutRedirecting()
            ->put($uploadUrl);
    }

    private function uploadVideoToLinkedIn(string $uploadUrl, string $videoUrl): void
    {
        $path = $this->resolveMediaPath($videoUrl);

        if ($path === null || ! file_exists($path)) {
            throw new \RuntimeException(
                'Video file not found. URL: '.$videoUrl.' | Resolved path: '.($path ?? 'null')
            );
        }

        $mime = mime_content_type($path) ?: 'video/mp4';

        Http::withBody(fopen($path, 'r'), $mime)
            ->withoutRedirecting()
            ->put($uploadUrl);
    }

    private function resolveAuthorUrn(SocialAccount $account): string
    {
        $meta = $account->meta ?? [];
        if (is_array($meta) && isset($meta['li_member_id']) && is_string($meta['li_member_id'])) {
            return (string) $meta['li_member_id'];
        }

        return '';
    }

    private function resolveToken(string $encryptedToken): string
    {
        try {
            return decrypt($encryptedToken);
        } catch (\Exception) {
            return $encryptedToken;
        }
    }
}
