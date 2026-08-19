<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\PostListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PostsController extends Controller
{
    public function __construct(
        private readonly PostListingService $postListing,
    ) {
    }

    public function index(Request $request): View
    {
        $raw = $request->query('status');
        $statusFilter = null;
        if (is_string($raw) && $raw !== '' && $raw !== 'all') {
            $statusFilter = $raw;
        }

        $data = $this->postListing->paginateForIndex($request->user(), $statusFilter, 20);

        return view('posts.index', array_merge($data, [
            'title' => 'Posts',
            'description' => 'View and manage your posts across connected platforms.',
        ]));
    }

    public function export(Request $request): JsonResponse
    {
        $workspace = $this->postListing->resolveWorkspaceForUser($request->user());
        if ($workspace === null) {
            abort(404);
        }

        $posts = $this->postListing->allForExport($request->user());
        $exportedAt = now()->utc();

        $payload = [
            'schema' => 'skoolyst-posts-export-v1',
            'exported_at' => $exportedAt->toIso8601String(),
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'posts_count' => $posts->count(),
            'posts' => $posts->map(fn (Post $post): array => [
                'id' => $post->id,
                'caption' => $post->caption,
                'content' => $post->content,
                'image_url' => $post->image_url,
                'link_url' => $post->link_url,
                'platforms' => $this->postListing->platformSlugsForPost($post),
                'status' => $post->status,
                'scheduled_at' => $post->scheduled_at?->copy()->utc()->toIso8601String(),
                'published_at' => $post->published_at?->copy()->utc()->toIso8601String(),
                'timezone' => $post->timezone,
                'ai_generated' => (bool) $post->ai_generated,
                'platform_post_ids' => [
                    'facebook' => $post->fb_post_id,
                    'instagram' => $post->ig_post_id,
                    'linkedin' => $post->li_post_id,
                    'x' => $post->twitter_post_id,
                ],
                'errors' => [
                    'facebook' => $post->fb_error,
                    'instagram' => $post->ig_error,
                    'linkedin' => $post->li_error,
                ],
                'media' => $post->postMedia->map(fn ($media): array => [
                    'url' => $media->url,
                    'type' => $media->type,
                    'size' => $media->size,
                    'mime_type' => $media->mime_type,
                    'sort_order' => $media->sort_order,
                ])->values()->all(),
                'targets' => $post->postTargets->map(fn ($target): array => [
                    'platform' => $target->socialPlatform?->slug,
                    'account_name' => $target->socialAccount?->account_name,
                    'status' => $target->status,
                    'platform_post_id' => $target->platform_post_id,
                    'published_at' => $target->published_at?->copy()->utc()->toIso8601String(),
                    'error_message' => $target->error_message,
                    'stats' => [
                        'likes' => $target->likes_count,
                        'comments' => $target->comments_count,
                        'shares' => $target->shares_count,
                        'reactions' => $target->reactions_count,
                    ],
                ])->values()->all(),
                'created_at' => $post->created_at?->copy()->utc()->toIso8601String(),
                'updated_at' => $post->updated_at?->copy()->utc()->toIso8601String(),
            ])->values()->all(),
        ];

        $workspaceSlug = Str::slug((string) $workspace->name) ?: 'workspace';
        $filename = sprintf('skoolyst-posts-%s-%s.json', $workspaceSlug, $exportedAt->format('Y-m-d-His'));

        return response()
            ->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
    }

    public function scheduled(Request $request): View
    {
        $data = $this->postListing->paginateScheduled($request->user(), 20);

        return view('posts.scheduled', array_merge($data, [
            'title' => 'Scheduled',
            'description' => 'Upcoming scheduled posts.',
        ]));
    }

    public function editScheduled(Request $request, Post $post): View|RedirectResponse
    {
        $workspace = $this->postListing->resolveWorkspaceForUser($request->user());
        if (! $workspace || $post->workspace_id !== $workspace->id) {
            abort(404);
        }

        return redirect()->route('posts.edit', ['post' => $post, 'focus' => 'schedule']);
    }

    public function updateScheduled(Request $request, Post $post): RedirectResponse
    {
        $workspace = $this->postListing->resolveWorkspaceForUser($request->user());
        if (! $workspace || $post->workspace_id !== $workspace->id) {
            abort(404);
        }

        $validated = $request->validate([
            'caption'      => ['required', 'string', 'min:1', 'max:2200'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $post->caption      = $validated['caption'];
        $post->scheduled_at = Post::parseScheduledInput($validated['scheduled_at']);
        $post->status       = 'scheduled';
        $post->save();

        return redirect()
            ->route('posts.scheduled')
            ->with('success', __('Scheduled post updated.'));
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->postListing->deleteForUser($request->user(), $post);

        return redirect()
            ->back()
            ->with('success', __('Post deleted.'));
    }
}
