<?php

namespace App\Services;

use App\Models\SocialAccount;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class LinkedInPostService
{
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
            $client = new Client([
                'base_uri' => self::LINKEDIN_API_BASE,
                'timeout' => 30,
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'LinkedIn-Version' => self::LINKEDIN_VERSION,
                    'Content-Type' => 'application/json',
                ],
            ]);

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

            $response = $client->post('ugcPosts', [
                'json' => $payload,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (! is_array($body) || ! isset($body['id'])) {
                Log::error('LinkedIn publish failed - missing post id', [
                    'author' => $authorUrn,
                    'response' => $body,
                ]);

                return ['success' => false, 'error' => 'LinkedIn response missing post id.'];
            }

            return ['success' => true, 'post_id' => (string) $body['id']];
        } catch (GuzzleException $e) {
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
            $client = new Client([
                'base_uri' => self::LINKEDIN_API_BASE,
                'timeout' => 30,
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'LinkedIn-Version' => self::LINKEDIN_VERSION,
                    'Content-Type' => 'application/json',
                ],
            ]);

            // First, upload the asset
            $uploadResponse = $client->post('assets?action=registerUpload', [
                'json' => [
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
                ],
            ]);

            $uploadBody = json_decode((string) $uploadResponse->getBody(), true);

            if (! is_array($uploadBody) || ! isset($uploadBody['value']['uploadMechanism'])) {
                Log::error('LinkedIn asset upload registration failed', ['response' => $uploadBody]);

                return ['success' => false, 'error' => 'LinkedIn asset upload registration failed.'];
            }

            $uploadUrl = $uploadBody['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn = $uploadBody['value']['asset'];

            // Upload the image
            $this->uploadImageToLinkedIn($uploadUrl, $imageUrl);

            // Create the post with the asset
            $payload = [
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
            ];

            $postResponse = $client->post('ugcPosts', [
                'json' => $payload,
            ]);

            $postBody = json_decode((string) $postResponse->getBody(), true);

            if (! is_array($postBody) || ! isset($postBody['id'])) {
                Log::error('LinkedIn image post failed - missing post id', [
                    'author' => $authorUrn,
                    'response' => $postBody,
                ]);

                return ['success' => false, 'error' => 'LinkedIn response missing post id.'];
            }

            return ['success' => true, 'post_id' => (string) $postBody['id']];
        } catch (GuzzleException $e) {
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
            $client = new Client([
                'base_uri' => self::LINKEDIN_API_BASE,
                'timeout' => 60,
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'LinkedIn-Version' => self::LINKEDIN_VERSION,
                    'Content-Type' => 'application/json',
                ],
            ]);

            // First, upload the asset
            $uploadResponse = $client->post('assets?action=registerUpload', [
                'json' => [
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
                ],
            ]);

            $uploadBody = json_decode((string) $uploadResponse->getBody(), true);

            if (! is_array($uploadBody) || ! isset($uploadBody['value']['uploadMechanism'])) {
                Log::error('LinkedIn video upload registration failed', ['response' => $uploadBody]);

                return ['success' => false, 'error' => 'LinkedIn video upload registration failed.'];
            }

            $uploadUrl = $uploadBody['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
            $assetUrn = $uploadBody['value']['asset'];

            // Upload the video
            $this->uploadVideoToLinkedIn($uploadUrl, $videoUrl);

            // Create the post with the asset
            $payload = [
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
            ];

            $postResponse = $client->post('ugcPosts', [
                'json' => $payload,
            ]);

            $postBody = json_decode((string) $postResponse->getBody(), true);

            if (! is_array($postBody) || ! isset($postBody['id'])) {
                Log::error('LinkedIn video post failed - missing post id', [
                    'author' => $authorUrn,
                    'response' => $postBody,
                ]);

                return ['success' => false, 'error' => 'LinkedIn response missing post id.'];
            }

            return ['success' => true, 'post_id' => (string) $postBody['id']];
        } catch (GuzzleException $e) {
            Log::error('LinkedIn video publish failed', [
                'error' => $e->getMessage(),
                'author' => $authorUrn,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function uploadImageToLinkedIn(string $uploadUrl, string $imageUrl): void
    {
        $path = str_replace(url('/storage'), storage_path('app/public'), $imageUrl);

        if (! file_exists($path)) {
            throw new \RuntimeException('Image file not found: '.$path);
        }

        $client = new Client(['timeout' => 60]);
        $client->put($uploadUrl, [
            'body' => fopen($path, 'r'),
            'headers' => [
                'Content-Type' => 'image/jpeg',
            ],
        ]);
    }

    private function uploadVideoToLinkedIn(string $uploadUrl, string $videoUrl): void
    {
        $path = str_replace(url('/storage'), storage_path('app/public'), $videoUrl);

        if (! file_exists($path)) {
            throw new \RuntimeException('Video file not found: '.$path);
        }

        $client = new Client(['timeout' => 600]);
        $client->put($uploadUrl, [
            'body' => fopen($path, 'r'),
            'headers' => [
                'Content-Type' => 'video/mp4',
            ],
        ]);
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
