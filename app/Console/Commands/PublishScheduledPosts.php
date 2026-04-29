<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Services\SocialPostService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PublishScheduledPosts extends Command
{
    protected $signature = 'posts:publish-scheduled';

    protected $description = 'Publish posts that are scheduled for the current time or earlier.';

    public function handle(SocialPostService $socialPostService): int
    {
        $processed = 0;

        while (true) {
            $postId = DB::transaction(function (): ?int {
                $post = Post::query()
                    ->where('status', 'scheduled')
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<=', now())
                    ->orderBy('scheduled_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($post === null) {
                    return null;
                }

                Post::query()->whereKey($post->id)->update(['status' => 'publishing']);

                return (int) $post->id;
            });

            if ($postId === null) {
                break;
            }

            $processed++;

            $post = Post::with(['postTargets.socialPlatform', 'postTargets.socialAccount', 'postMedia'])
                ->findOrFail($postId);

            $this->info("Publishing Post ID: {$post->id}");

            try {
                $this->publishSingleScheduledPost($post, $socialPostService);
            } catch (Throwable $e) {
                report($e);
                $post->refresh();
                $post->status = 'failed';
                $post->fb_error = $e->getMessage();
                $post->save();
                $this->error("Post {$postId} failed: {$e->getMessage()}");
            }

            $this->info('Finished Post ID: '.$postId.' with status: '.$post->fresh()?->status);
        }

        if ($processed === 0) {
            $this->info('No scheduled posts to publish at this time.');
        } else {
            $this->info("Processed {$processed} scheduled post(s).");
        }

        return self::SUCCESS;
    }

    private function publishSingleScheduledPost(Post $post, SocialPostService $socialPostService): void
    {
        $media = $post->postMedia->first();
        $mediaUrl = $media ? $media->url : null;
        $mediaType = $media ? $media->type : null;

        $published = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($post->postTargets as $target) {
            $platform = $target->socialPlatform?->slug;

            if ($platform !== 'facebook') {
                $skipped++;
                $target->update([
                    'status' => 'skipped',
                    'error_message' => __('Immediate publishing is currently enabled for Facebook only.'),
                ]);

                continue;
            }

            if (! $target->socialAccount) {
                $failed++;
                $target->update([
                    'status' => 'failed',
                    'error_message' => __('Connected Facebook account was not found.'),
                ]);

                continue;
            }

            $target->update(['status' => 'publishing', 'error_message' => null]);

            $result = $socialPostService->postToFacebookAccount(
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
        }

        $post->status = match (true) {
            $published > 0 && $failed === 0 && $skipped === 0 => 'published',
            $published > 0 => 'partial',
            default => 'failed',
        };
        $post->published_at = $published > 0 ? now() : null;
        $post->save();
    }
}
