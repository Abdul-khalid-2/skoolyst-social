<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\PostTarget;
use App\Models\PublishJob;
use App\Models\PublishLog;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class DiagnoseInstagramPost extends Command
{
    protected $signature = 'posts:diagnose-ig {post_id? : Optional post id; defaults to the latest post that has an Instagram target}';

    protected $description = 'Diagnose why an Instagram post target failed: shows account, token check, media URL reachability, and recent publish logs.';

    public function handle(): int
    {
        $postId = $this->argument('post_id');
        $target = $this->resolveTarget($postId);
        if ($target === null) {
            $this->error('No Instagram post target found.');

            return self::FAILURE;
        }

        $post = $target->post;
        $account = $target->socialAccount;

        $this->info('────────────────────────────────────────');
        $this->info('Post #'.$post->id.' / Target #'.$target->id);
        $this->info('────────────────────────────────────────');
        $this->line('Caption length : '.mb_strlen((string) $post->caption));
        $this->line('Status (target): '.$target->status);
        $this->line('Error message  : '.($target->error_message ?: '— none —'));
        $this->line('Platform PostID: '.($target->platform_post_id ?: '— none —'));

        $this->newLine();
        $this->info('Instagram Social Account');
        $this->info('────────────────────────────────────────');
        if ($account === null) {
            $this->error('Target has NO linked SocialAccount row. Provisioner did not save the IG account, or it was deleted.');
        } else {
            $this->line('Account name      : '.$account->account_name);
            $this->line('Account handle    : '.($account->account_handle ?: '—'));
            $this->line('platform_page_id  : '.($account->platform_page_id ?: '— EMPTY (this is the IG Business User ID; required) —'));
            $this->line('platform_user_id  : '.($account->platform_user_id ?: '—'));
            $this->line('is_connected      : '.($account->is_connected ? 'yes' : 'NO'));
            $this->line('token expires at  : '.optional($account->token_expires_at)->toIso8601String() ?: 'never');
            $meta = $account->meta ?: [];
            $this->line('meta.facebook_page_id : '.($meta['facebook_page_id'] ?? '— missing —'));
            $this->line('meta.instagram_user_id: '.($meta['instagram_user_id'] ?? '— missing —'));

            $this->newLine();
            $this->info('Token Probe (calls /me on FB Graph)');
            $this->info('────────────────────────────────────────');
            try {
                $token = $this->resolveToken((string) $account->access_token);
                $version = (string) config('services.facebook.graph_version', 'v24.0');
                $resp = Http::timeout(20)->get("https://graph.facebook.com/{$version}/me", [
                    'fields' => 'id,name',
                    'access_token' => $token,
                ]);
                $this->line('HTTP '.$resp->status().' '.$resp->body());

                $perm = Http::timeout(20)->get("https://graph.facebook.com/{$version}/me/permissions", [
                    'access_token' => $token,
                ]);
                if ($perm->successful()) {
                    $granted = collect($perm->json('data') ?? [])
                        ->filter(fn ($p) => ($p['status'] ?? null) === 'granted')
                        ->pluck('permission')
                        ->all();
                    $this->line('Granted scopes: '.(empty($granted) ? '(none)' : implode(', ', $granted)));
                    $must = ['instagram_basic', 'instagram_content_publish', 'pages_read_engagement', 'pages_show_list'];
                    $missing = array_diff($must, $granted);
                    if (! empty($missing)) {
                        $this->warn('MISSING required scopes: '.implode(', ', $missing));
                        $this->warn('-> User must reconnect Facebook and approve these scopes.');
                    }
                } else {
                    $this->line('Permission probe HTTP '.$perm->status().' '.$perm->body());
                }

                $igProbe = Http::timeout(20)->get("https://graph.facebook.com/{$version}/{$account->platform_page_id}", [
                    'fields' => 'id,username,name,profile_picture_url',
                    'access_token' => $token,
                ]);
                $this->line('IG node probe HTTP '.$igProbe->status().' '.$igProbe->body());
            } catch (Throwable $e) {
                $this->error('Token probe failed: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info('Post Media');
        $this->info('────────────────────────────────────────');
        $media = $post->postMedia->first();
        if ($media === null) {
            $this->error('No media attached. Instagram requires at least one image or video.');
        } else {
            $this->line('Type      : '.$media->type);
            $this->line('Mime type : '.$media->mime_type);
            $this->line('URL       : '.$media->url);
            $this->newLine();
            $this->info('Reachability check (simulating a non-browser fetch)');
            try {
                $head = Http::timeout(20)
                    ->withHeaders(['User-Agent' => 'facebookexternalhit/1.1', 'Accept' => 'image/*,video/*,*/*'])
                    ->get($media->url);
                $ct = $head->header('Content-Type');
                $size = strlen((string) $head->body());
                $this->line('HTTP '.$head->status().'  Content-Type: '.($ct ?: '?').'  Bytes: '.$size);
                if (str_starts_with((string) $ct, 'text/html')) {
                    $this->warn('-> Looks like an HTML interstitial (likely ngrok-free warning). Facebook CANNOT fetch this URL.');
                    $this->warn('-> Set MEDIA_MIRROR_DRIVER=catbox in .env (or use 0x0/imgbb/cloudinary) and retry.');
                }
            } catch (Throwable $e) {
                $this->error('Reachability check failed: '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info('Recent Publish Logs (latest 15) for this target');
        $this->info('────────────────────────────────────────');
        $jobIds = PublishJob::query()->where('post_target_id', $target->id)->pluck('id');
        if ($jobIds->isEmpty()) {
            $this->line('No publish jobs recorded yet.');
        } else {
            $logs = PublishLog::query()
                ->whereIn('publish_job_id', $jobIds)
                ->orderBy('id', 'desc')
                ->take(15)
                ->get();
            foreach ($logs->reverse() as $log) {
                $this->line('['.str_pad(strtoupper($log->level), 7).'] '
                    .($log->http_status ? 'HTTP '.$log->http_status.'  ' : '')
                    .$log->message);
                if (! empty($log->response)) {
                    $this->line('         '.mb_substr(json_encode($log->response, JSON_UNESCAPED_SLASHES), 0, 500));
                }
            }
        }

        $this->newLine();
        $this->info('Mirror Driver');
        $this->info('────────────────────────────────────────');
        $this->line('services.media_mirror.driver = '.config('services.media_mirror.driver'));
        $this->line('Force-mirror hosts: '.implode(', ', (array) config('services.media_mirror.force_for_hosts', [])));

        return self::SUCCESS;
    }

    private function resolveTarget(?string $postId): ?PostTarget
    {
        $instagram = SocialPlatform::query()->where('slug', 'instagram')->first();
        if ($instagram === null) {
            return null;
        }

        $query = PostTarget::query()
            ->where('social_platform_id', $instagram->id)
            ->with(['post.postMedia', 'socialAccount']);

        if ($postId !== null) {
            $query->where('post_id', (int) $postId);
        }

        return $query->latest('id')->first();
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
