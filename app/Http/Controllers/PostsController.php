<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Services\PostListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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


    public function importTemplate(Request $request): JsonResponse
    {
        $workspace = $this->postListing->resolveWorkspaceForUser($request->user());
        if ($workspace === null) {
            abort(404);
        }

        $payload = [
            'schema' => 'skoolyst-posts-import-v1',
            'instructions' => [
                'Upload this JSON from the Posts page to create many scheduled posts at once.',
                'scheduled_at must be a future date/time. ISO 8601 values are recommended, for example 2026-09-01T09:00:00Z.',
                'Use platform slugs: facebook, instagram, linkedin, or twitter. If social_account_ids is empty, all active connected accounts for those platforms will be targeted.',
                'Media URLs are optional and must already be publicly accessible.',
            ],
            'posts' => [
                [
                    'caption' => 'Back-to-school admissions are open. Book a visit with Skoolyst today!',
                    'scheduled_at' => now()->addDay()->setMinute(0)->setSecond(0)->toIso8601String(),
                    'platforms' => ['facebook', 'instagram', 'linkedin'],
                    'social_account_ids' => [],
                    'link_url' => 'https://example.com/admissions',
                    'media' => [
                        [
                            'url' => 'https://example.com/image.jpg',
                            'type' => 'image',
                            'mime_type' => 'image/jpeg',
                        ],
                    ],
                ],
                [
                    'caption' => 'Reminder: parent orientation starts next week.',
                    'scheduled_at' => now()->addDays(2)->setMinute(30)->setSecond(0)->toIso8601String(),
                    'platforms' => ['linkedin'],
                    'social_account_ids' => [],
                    'link_url' => null,
                    'media' => [],
                ],
            ],
        ];

        return response()
            ->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="skoolyst-posts-import-template.json"',
            ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $workspace = $this->postListing->resolveWorkspaceForUser($request->user());
        if ($workspace === null) {
            abort(404);
        }

        $request->validate([
            'posts_file' => ['required', 'file', 'mimetypes:application/json,text/plain,text/json,application/octet-stream', 'max:2048'],
        ]);

        $json = file_get_contents($request->file('posts_file')->getRealPath());
        $payload = json_decode((string) $json, true);
        if (! is_array($payload)) {
            return back()->withErrors(['posts_file' => __('The uploaded file must contain valid JSON.')]);
        }

        $rows = $payload['posts'] ?? $payload;
        if (! is_array($rows) || $rows === []) {
            return back()->withErrors(['posts_file' => __('The JSON file must include a non-empty posts array.')]);
        }

        $created = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $workspace, $request, &$created, &$errors): void {
            foreach (array_values($rows) as $index => $row) {
                if (! is_array($row)) {
                    $errors[] = __('Row :row must be an object.', ['row' => $index + 1]);
                    continue;
                }

                $row = $this->normalizeImportRow($row);
                $validator = Validator::make($row, [
                    'caption' => ['required', 'string', 'min:1', 'max:2200'],
                    'scheduled_at' => ['required', 'date', 'after:now'],
                    'platforms' => ['nullable', 'array'],
                    'platforms.*' => ['string', Rule::in(['facebook', 'instagram', 'linkedin', 'twitter'])],
                    'social_account_ids' => ['nullable', 'array'],
                    'social_account_ids.*' => ['integer', 'min:1'],
                    'link_url' => ['nullable', 'url', 'max:2048'],
                    'media' => ['nullable', 'array'],
                    'media.*.url' => ['required_with:media', 'url', 'max:2048'],
                    'media.*.type' => ['nullable', 'string', Rule::in(['image', 'video', 'gif'])],
                    'media.*.mime_type' => ['nullable', 'string', 'max:255'],
                ]);

                if ($validator->fails()) {
                    $errors[] = __('Row :row: :message', [
                        'row' => $index + 1,
                        'message' => $validator->errors()->first(),
                    ]);
                    continue;
                }

                $accountIds = $this->resolveImportAccountIds($workspace->id, $row);
                if ($accountIds === [] && ((array) ($row['platforms'] ?? [])) !== []) {
                    $errors[] = __('Row :row: no active connected accounts were found for the selected platforms.', ['row' => $index + 1]);
                    continue;
                }

                $post = Post::query()->create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $request->user()->id,
                    'caption' => (string) $row['caption'],
                    'content' => $row['content'] ?? null,
                    'image_url' => $row['image_url'] ?? null,
                    'link_url' => $row['link_url'] ?? null,
                    'platforms' => array_values(array_unique((array) ($row['platforms'] ?? []))),
                    'status' => 'scheduled',
                    'scheduled_at' => Post::parseScheduledInput((string) $row['scheduled_at']),
                    'published_at' => null,
                    'timezone' => (string) ($request->user()?->timezone ?? config('app.timezone', 'UTC')),
                    'ai_generated' => false,
                ]);

                foreach ((array) ($row['media'] ?? []) as $mediaIndex => $media) {
                    PostMedia::query()->create([
                        'post_id' => $post->id,
                        'media_asset_id' => null,
                        'url' => $media['url'],
                        'type' => $media['type'] ?? $this->guessMediaType((string) $media['url']),
                        'size' => 0,
                        'mime_type' => $media['mime_type'] ?? null,
                        'sort_order' => $mediaIndex,
                    ]);
                }

                $accounts = SocialAccount::query()
                    ->where('workspace_id', $workspace->id)
                    ->whereIn('id', $accountIds)
                    ->where('is_connected', true)
                    ->where('is_active', true)
                    ->get();

                foreach ($accounts as $account) {
                    PostTarget::query()->create([
                        'post_id' => $post->id,
                        'social_account_id' => $account->id,
                        'social_platform_id' => $account->social_platform_id,
                        'status' => 'pending',
                    ]);
                }

                $created++;
            }
        });

        if ($created === 0) {
            return back()->withErrors(['posts_file' => $errors[0] ?? __('No posts were imported.')]);
        }

        return redirect()
            ->route('posts.scheduled')
            ->with('success', trans_choice('{1} Imported :count scheduled post.|[2,*] Imported :count scheduled posts.', $created, ['count' => $created]))
            ->with('warning', $errors === [] ? null : __('Some rows were skipped: :errors', ['errors' => implode(' ', array_slice($errors, 0, 3))]));
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

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeImportRow(array $row): array
    {
        if (! isset($row['caption']) && isset($row['content'])) {
            $row['caption'] = $row['content'];
        }

        $platforms = $row['platforms'] ?? $row['platform_slugs'] ?? [];
        $row['platforms'] = is_array($platforms) ? array_values(array_filter(array_map('strval', $platforms))) : [$platforms];
        $row['platforms'] = array_map(fn (string $slug): string => strtolower($slug) === 'x' ? 'twitter' : strtolower($slug), $row['platforms']);

        $ids = $row['social_account_ids'] ?? [];
        $row['social_account_ids'] = is_array($ids) ? array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric')))) : [];

        if (! isset($row['media']) && ! empty($row['image_url'])) {
            $row['media'] = [[
                'url' => $row['image_url'],
                'type' => $this->guessMediaType((string) $row['image_url']),
            ]];
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, int>
     */
    private function resolveImportAccountIds(int $workspaceId, array $row): array
    {
        $accountIds = (array) ($row['social_account_ids'] ?? []);
        if ($accountIds !== []) {
            return SocialAccount::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('id', $accountIds)
                ->where('is_connected', true)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
        }

        $platforms = array_values(array_unique((array) ($row['platforms'] ?? [])));
        if ($platforms === []) {
            return [];
        }

        return SocialAccount::query()
            ->where('workspace_id', $workspaceId)
            ->where('is_connected', true)
            ->where('is_active', true)
            ->whereHas('platform', fn ($query) => $query->whereIn('slug', $platforms))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function guessMediaType(string $url): string
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return match (true) {
            Str::endsWith($path, ['.mp4', '.mov', '.webm']) => 'video',
            Str::endsWith($path, ['.gif']) => 'gif',
            default => 'image',
        };
    }
}
