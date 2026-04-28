<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\SocialPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialPostController extends Controller
{
    public function __construct(private readonly SocialPostService $socialPostService) {}

    public function publish(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:2200'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'string', 'in:facebook,instagram'],
            'image_url' => ['nullable', 'url'],
            'link' => ['nullable', 'url'],
        ]);

        $results = [];
        $fbPostId = null;
        $igPostId = null;
        $fbError = null;
        $igError = null;

        foreach ($data['platforms'] as $platform) {
            if ($platform === 'facebook') {
                $result = $this->socialPostService->postToFacebook($data['content'], $data['link'] ?? null);
                $results['facebook'] = $result;
                if (($result['success'] ?? false) === true) {
                    $fbPostId = $result['post_id'] ?? null;
                } else {
                    $fbError = $result['error'] ?? 'Facebook post failed';
                }
            }

            if ($platform === 'instagram') {
                if (empty($data['image_url'])) {
                    $result = ['success' => false, 'error' => 'Instagram requires image_url'];
                } else {
                    $result = $this->socialPostService->postToInstagram((string) $data['image_url'], $data['content']);
                }
                $results['instagram'] = $result;
                if (($result['success'] ?? false) === true) {
                    $igPostId = $result['post_id'] ?? null;
                } else {
                    $igError = $result['error'] ?? 'Instagram post failed';
                }
            }
        }

        $successCount = collect($results)->filter(fn (array $r) => ($r['success'] ?? false) === true)->count();
        $status = match (true) {
            $successCount === 0 => 'failed',
            $successCount < count($results) => 'partial',
            default => 'published',
        };

        Post::query()->create([
            'workspace_id' => $request->user()?->workspaces()->value('workspaces.id'),
            'user_id' => $request->user()?->id,
            'caption' => $data['content'],
            'content' => $data['content'],
            'image_url' => $data['image_url'] ?? null,
            'link_url' => $data['link'] ?? null,
            'platforms' => $data['platforms'],
            'status' => $status,
            'fb_post_id' => $fbPostId,
            'ig_post_id' => $igPostId,
            'fb_error' => $fbError,
            'ig_error' => $igError,
            'published_at' => $status === 'published' || $status === 'partial' ? now() : null,
            'timezone' => $request->user()?->timezone ?? 'UTC',
            'ai_generated' => false,
        ]);

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    public function getSocialAccounts(Request $request): JsonResponse
    {
        $facebookConnected = (string) env('FB_PAGE_ID', '') !== '' && (string) env('FB_PAGE_ACCESS_TOKEN', '') !== '';
        $instagramConnected = (string) env('IG_USER_ID', '') !== '' && (string) env('IG_ACCESS_TOKEN', '') !== '';

        return response()->json([
            'facebook' => [
                'connected' => $facebookConnected,
                'page_id' => env('FB_PAGE_ID'),
            ],
            'instagram' => [
                'connected' => $instagramConnected,
                'ig_user_id' => env('IG_USER_ID'),
            ],
        ]);
    }
}

