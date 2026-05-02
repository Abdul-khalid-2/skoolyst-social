<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostTarget;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use App\Services\InstagramPostService;
use App\Services\SocialPostService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function __construct(
        private readonly SocialPostService $socialPostService,
        private readonly InstagramPostService $instagramPostService,
    ) {}

    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [Post::class, $workspace]);

        $posts = Post::query()
            ->where('workspace_id', $workspace->id)
            ->with(['author', 'postMedia', 'postTargets.socialPlatform', 'postTargets.socialAccount'])
            ->latest()
            ->get();

        return response()->json([
            'workspace_id' => $workspace->id,
            'posts' => $posts->map(fn (Post $post) => $this->formatPost($post))->values()->all(),
        ]);
    }

    public function store(StorePostRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('create', [Post::class, $workspace]);

        $mode = (string) $request->input('mode');
        if ($mode === 'now') {
            $this->authorize('publish', [Post::class, $workspace]);
        } elseif ($mode === 'schedule') {
            $this->authorize('schedule', [Post::class, $workspace]);
        }

        $raw = $request->input('platform_slugs', []);
        $rawList = is_array($raw) ? $raw : ($raw !== null && $raw !== '' ? [$raw] : []);
        $slugs = array_values(array_unique(array_filter($rawList, fn ($s) => is_string($s) && $s !== '')));

        $status = match ($mode) {
            'draft' => 'draft',
            'schedule' => 'scheduled',
            'now' => 'publishing',
            default => 'draft',
        };

        $scheduledAt = null;
        if ($mode === 'schedule' && $request->filled('scheduled_at')) {
            $scheduledAt = Carbon::parse($request->input('scheduled_at'));
        }

        $tz = (string) ($request->user()?->timezone ?? 'UTC');

        $post = DB::transaction(function () use ($request, $workspace, $slugs, $status, $scheduledAt, $tz) {
            $post = Post::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'caption' => $request->string('caption')->toString(),
                'status' => $status,
                'scheduled_at' => $scheduledAt,
                'published_at' => null,
                'timezone' => $tz,
                'ai_generated' => $request->boolean('ai_generated'),
            ]);

            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $dir = 'workspaces/'.$workspace->id.'/posts/'.$post->id;
                $ext = $file->getClientOriginalExtension() !== ''
                    ? $file->getClientOriginalExtension()
                    : (str_starts_with($file->getMimeType() ?? '', 'video/') ? 'mp4' : 'bin');
                $name = (string) Str::uuid().'.'.$ext;
                $path = $file->storeAs($dir, $name, 'public');
                $mime = $file->getMimeType() ?? 'application/octet-stream';
                $type = $this->mapMediaType($mime, (string) $file->getClientOriginalName());

                PostMedia::query()->create([
                    'post_id' => $post->id,
                    'media_asset_id' => null,
                    'url' => URL::to(Storage::disk('public')->url($path)),
                    'type' => $type,
                    'size' => $file->getSize() ?: 0,
                    'mime_type' => $mime,
                    'sort_order' => 0,
                ]);
            }

            if (count($slugs) > 0) {
                $this->assertSocialAccounts($workspace, $slugs);
                $this->createPostTargets($post, $workspace, $slugs);
            }

            return $post;
        });

        if ($mode === 'now' && count($slugs) > 0) {
            $this->publishPostTargets($post);
        }

        $post->load(['postMedia', 'postTargets.socialPlatform', 'postTargets.socialAccount']);

        return response()->json($this->formatPost($post), 201);
    }

    public function update(Request $request, Workspace $workspace, Post $post): JsonResponse
    {
        $this->assertPostInWorkspace($workspace, $post);
        $this->authorize('update', $post);

        $validated = $request->validate([
            'caption' => ['sometimes', 'string', 'min:1', 'max:2200'],
            'action' => ['sometimes', 'string', Rule::in(['draft', 'publish_now', 'reschedule'])],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        if (($validated['action'] ?? null) === 'reschedule' && empty($validated['scheduled_at'])) {
            throw ValidationException::withMessages([
                'scheduled_at' => [__('Please choose a date and time in the future to reschedule.')],
            ]);
        }

        if (($validated['action'] ?? null) !== 'reschedule' && ! empty($validated['scheduled_at'])) {
            throw ValidationException::withMessages([
                'scheduled_at' => [__('Use action=reschedule when setting scheduled time.')],
            ]);
        }

        if (array_key_exists('caption', $validated)) {
            $post->caption = (string) $validated['caption'];
        }

        $action = (string) ($validated['action'] ?? '');
        if ($action === 'draft') {
            $post->status = 'draft';
            $post->scheduled_at = null;
        } elseif ($action === 'publish_now') {
            $this->authorize('publish', $post);
            $post->status = 'publishing';
            $post->scheduled_at = null;
        } elseif ($action === 'reschedule') {
            $this->authorize('schedule', $post);
            $post->status = 'scheduled';
            $post->scheduled_at = Carbon::parse((string) $validated['scheduled_at']);
        }

        $post->save();
        $post->load(['author', 'postMedia', 'postTargets.socialPlatform', 'postTargets.socialAccount']);

        return response()->json($this->formatPost($post));
    }

    public function destroy(Request $request, Workspace $workspace, Post $post): JsonResponse
    {
        $this->assertPostInWorkspace($workspace, $post);
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully.']);
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function assertSocialAccounts(Workspace $workspace, array $slugs): void
    {
        $messages = [];
        foreach ($slugs as $slug) {
            $platform = SocialPlatform::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
            if (! $platform) {
                $messages['platform_slugs'] = [__('The platform :slug is not available.', ['slug' => $slug])];
                throw ValidationException::withMessages($messages);
            }
            $account = SocialAccount::query()
                ->where('workspace_id', $workspace->id)
                ->where('social_platform_id', $platform->id)
                ->where('is_connected', true)
                ->first();
            if (! $account) {
                $messages['platform_slugs'] = [__('No connected :platform account. Add or connect the account in Accounts (Settings) first.', ['platform' => $platform->name])];
                throw ValidationException::withMessages($messages);
            }
        }
    }

    /**
     * @param  array<int, string>  $slugs
     */
    private function createPostTargets(Post $post, Workspace $workspace, array $slugs): void
    {
        foreach ($slugs as $slug) {
            $platform = SocialPlatform::query()->where('slug', $slug)->firstOrFail();
            
            // Get all connected accounts for this platform in this workspace
            $accounts = SocialAccount::query()
                ->where('workspace_id', $workspace->id)
                ->where('social_platform_id', $platform->id)
                ->where('is_connected', true)
                ->get();

            if ($accounts->isEmpty()) {
                throw ValidationException::withMessages([
                    'platform_slugs' => [__('No connected :platform account. Add or connect the account in Accounts (Settings) first.', ['platform' => $platform->name])],
                ]);
            }

            foreach ($accounts as $account) {
                PostTarget::query()->create([
                    'post_id' => $post->id,
                    'social_account_id' => $account->id,
                    'social_platform_id' => $platform->id,
                    'status' => 'pending',
                ]);
            }
        }
    }

    private function publishPostTargets(Post $post): void
    {
        $post->load(['postTargets.socialPlatform', 'postTargets.socialAccount', 'postMedia']);

        $media = $post->postMedia->first();
        $mediaUrl = $media ? $media->url : null;
        $mediaType = $media ? $media->type : null;

        $published = 0;
        $failed = 0;
        $skipped = 0;
        foreach ($post->postTargets as $target) {
            $platform = $target->socialPlatform?->slug;

            if (! in_array($platform, ['facebook', 'instagram'], true)) {
                $skipped++;
                $target->update([
                    'status' => 'skipped',
                    'error_message' => __('Publishing is enabled for Facebook and Instagram only.'),
                ]);

                continue;
            }

            if (! $target->socialAccount) {
                $failed++;
                $target->update([
                    'status' => 'failed',
                    'error_message' => __('Connected social account was not found.'),
                ]);

                continue;
            }

            $target->update(['status' => 'publishing', 'error_message' => null]);

            if ($platform === 'facebook') {
                $result = $this->socialPostService->postToFacebookAccount(
                    $target->socialAccount,
                    $post->caption,
                    null,
                    $mediaUrl,
                    $mediaType
                );

                if (($result['success'] ?? false) === true) {
                    $published++;
                    $target->update([
                        'status' => 'published',
                        'platform_post_id' => $result['post_id'] ?? null,
                        'published_at' => now(),
                        'error_message' => null,
                    ]);
                    $post->fb_post_id = $result['post_id'] ?? null;

                    continue;
                }

                $failed++;
                $error = (string) ($result['error'] ?? 'Facebook publish failed.');
                $target->update([
                    'status' => 'failed',
                    'error_message' => $error,
                ]);
                $post->fb_error = $error;

                continue;
            }

            if ($platform === 'instagram') {
                $result = $this->instagramPostService->publishPost($target->socialAccount, $post, $target);

                if (($result['success'] ?? false) === true) {
                    $published++;
                    $target->update([
                        'status' => 'published',
                        'platform_post_id' => $result['post_id'] ?? null,
                        'published_at' => now(),
                        'error_message' => null,
                    ]);
                    $post->ig_post_id = $result['post_id'] ?? null;

                    continue;
                }

                $failed++;
                $error = (string) ($result['error'] ?? 'Instagram publish failed.');
                $target->update([
                    'status' => 'failed',
                    'error_message' => $error,
                ]);
                $post->ig_error = $error;
            }
        }

        $post->status = match (true) {
            $published > 0 && $failed === 0 && $skipped === 0 => 'published',
            $published > 0 => 'partial',
            default => 'failed',
        };
        $post->published_at = $published > 0 ? now() : null;
        $post->save();
    }

    private function connectedAccountForPlatform(Workspace $workspace, SocialPlatform $platform): ?SocialAccount
    {
        $base = SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('social_platform_id', $platform->id)
            ->where('is_connected', true);

        if ($platform->slug === 'facebook') {
            $preferredPageId = (string) env('FB_PAGE_ID', '');
            if ($preferredPageId !== '') {
                $preferred = (clone $base)->where('platform_page_id', $preferredPageId)->first();
                if ($preferred) {
                    return $preferred;
                }
            }

            $pageAccount = (clone $base)
                ->whereNotNull('platform_page_id')
                ->latest()
                ->first();
            if ($pageAccount) {
                return $pageAccount;
            }
        }

        return $base->latest()->first();
    }

    private function mapMediaType(string $mime, string $fileName): string
    {
        if (str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if ($mime === 'image/gif' || str_ends_with(strtolower($fileName), '.gif')) {
            return 'gif';
        }

        return 'image';
    }

    private function formatPost(Post $post): array
    {
        return [
            'id' => $post->id,
            'caption' => $post->caption,
            'status' => $post->status,
            'scheduled_at' => $post->scheduled_at?->toIso8601String(),
            'published_at' => $post->published_at?->toIso8601String(),
            'workspace_id' => $post->workspace_id,
            'user_id' => $post->user_id,
            'author_name' => $post->author?->name,
            'ai_generated' => (bool) $post->ai_generated,
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
            'post_media' => $post->postMedia->map(fn (PostMedia $m) => [
                'id' => $m->id,
                'url' => $m->url,
                'type' => $m->type,
                'mime_type' => $m->mime_type,
            ])->values()->all(),
            'post_targets' => $post->postTargets->map(fn (PostTarget $t) => [
                'id' => $t->id,
                'platform' => $t->socialPlatform?->slug,
                'status' => $t->status,
                'social_account_id' => $t->social_account_id,
                'platform_post_id' => $t->platform_post_id,
                'error_message' => $t->error_message,
            ])->values()->all(),
        ];
    }

    private function assertPostInWorkspace(Workspace $workspace, Post $post): void
    {
        if ($post->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
