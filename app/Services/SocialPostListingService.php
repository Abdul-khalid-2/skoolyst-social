<?php

namespace App\Services;

use App\Models\PostTarget;
use App\Models\SocialAccount;
use Illuminate\Support\Collection;

class SocialPostListingService
{
    /**
     * @return array{
     *   facebook: array<int, array<string, mixed>>,
     *   instagram: array<int, array<string, mixed>>,
     *   linkedin: array<int, array<string, mixed>>,
     *   twitter: array<int, array<string, mixed>>,
     *   accounts: array<string, array<int, array{id: int, name: string}>>
     * }
     */
    public function listByPlatform(int $workspaceId): array
    {
        $targets = PostTarget::query()
            ->with([
                'post' => fn ($q) => $q->with('postMedia'),
                'socialAccount.platform',
                'socialPlatform',
            ])
            ->whereHas('post', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        $facebook = [];
        $instagram = [];
        $linkedin = [];
        $twitter = [];

        foreach ($targets as $target) {
            $row = $this->transformTarget($target);
            $slug = $row['platform'] ?? '';

            match ($slug) {
                'facebook' => $facebook[] = $row,
                'instagram' => $instagram[] = $row,
                'linkedin' => $linkedin[] = $row,
                'twitter' => $twitter[] = $row,
                default => null,
            };
        }

        $accounts = $this->connectedAccountsByPlatform($workspaceId);

        return [
            'facebook'  => $facebook,
            'instagram' => $instagram,
            'linkedin'  => $linkedin,
            'twitter'   => $twitter,
            'accounts'  => $accounts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformTarget(PostTarget $target): array
    {
        $post = $target->post;
        $account = $target->socialAccount;
        $slug = $target->socialPlatform?->slug ?? $account?->platform?->slug ?? '';
        $media = $post?->postMedia->first();
        $mediaType = $media?->type;

        $displayStatus = $this->resolveDisplayStatus($target, $post?->status);

        $accountName = (string) ($account?->account_name ?? 'Unknown');

        $base = [
            'id' => (string) $target->id,
            'target_id' => $target->id,
            'post_id' => $target->post_id,
            'platform' => $slug,
            'platform_post_id' => $target->platform_post_id,
            'caption' => (string) ($post?->caption ?? ''),
            'status' => $displayStatus,
            'likes' => $this->formatStat($target->likes_count),
            'comments' => $this->formatStat($target->comments_count),
            'shares' => $this->formatStat($target->shares_count),
            'reactions' => $this->formatStat($target->reactions_count ?? $target->likes_count),
            'published_at' => ($target->published_at ?? $post?->scheduled_at ?? $post?->created_at)?->format('Y-m-d H:i') ?? '—',
            'thumbnail' => $media?->url,
            'stats_synced_at' => $target->stats_synced_at?->toIso8601String(),
            'has_platform_id' => filled($target->platform_post_id),
        ];

        if ($slug === 'facebook') {
            $base['page'] = $accountName;
        } elseif ($slug === 'instagram') {
            $base['account'] = $accountName;
            $base['type'] = $this->instagramMediaLabel($mediaType);
        } elseif ($slug === 'linkedin') {
            $base['profile'] = $accountName;
        } elseif ($slug === 'twitter') {
            $base['handle'] = $accountName;
        }

        return $base;
    }

    private function resolveDisplayStatus(PostTarget $target, ?string $postStatus): string
    {
        if ($target->status === 'failed') {
            return 'failed';
        }

        if ($target->status === 'published') {
            return 'published';
        }

        if (in_array($postStatus, ['scheduled', 'draft'], true)) {
            return 'scheduled';
        }

        return $target->status;
    }

    private function instagramMediaLabel(?string $mediaType): string
    {
        return match ($mediaType) {
            'video' => 'Reel',
            'gif' => 'Video',
            'image' => 'Photo',
            default => 'Post',
        };
    }

    private function formatStat(mixed $value): string|int
    {
        if ($value === null) {
            return '—';
        }

        return (int) $value;
    }

    /**
     * @return array<string, array<int, array{id: int, name: string}>>
     */
    private function connectedAccountsByPlatform(int $workspaceId): array
    {
        $accounts = SocialAccount::query()
            ->with('platform')
            ->where('workspace_id', $workspaceId)
            ->where('is_connected', true)
            ->orderBy('account_name')
            ->get();

        $grouped = [
            'facebook'  => [],
            'instagram' => [],
            'linkedin'  => [],
            'twitter'   => [],
        ];

        foreach ($accounts as $account) {
            $slug = $account->platform?->slug;
            if (! isset($grouped[$slug])) {
                continue;
            }
            $grouped[$slug][] = [
                'id' => $account->id,
                'name' => $account->account_name,
            ];
        }

        return $grouped;
    }

    public function findTargetForWorkspace(int $workspaceId, int $targetId): ?PostTarget
    {
        return PostTarget::query()
            ->with(['post', 'socialAccount.platform', 'socialPlatform'])
            ->where('id', $targetId)
            ->whereHas('post', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->first();
    }

    public function countPublishedTargetsForRefresh(int $workspaceId, ?string $platform = null): int
    {
        return $this->publishedTargetsQuery($workspaceId, $platform)->count();
    }

    /**
     * @param  array<int, int|string>  $targetIds
     * @return Collection<int, PostTarget>
     */
    public function publishedTargetsForRefresh(
        int $workspaceId,
        ?string $platform = null,
        array $targetIds = [],
        int $limit = 12,
    ): Collection {
        $query = $this->publishedTargetsQuery($workspaceId, $platform);

        if ($targetIds !== []) {
            $query->whereIn('id', array_map('intval', $targetIds));
        } else {
            $query
                ->orderByRaw('stats_synced_at IS NULL DESC')
                ->orderBy('stats_synced_at')
                ->orderByDesc('id');
        }

        return $query->limit($limit)->get();
    }

    private function publishedTargetsQuery(int $workspaceId, ?string $platform): \Illuminate\Database\Eloquent\Builder
    {
        return PostTarget::query()
            ->with(['socialAccount.platform', 'socialPlatform'])
            ->where('status', 'published')
            ->whereNotNull('platform_post_id')
            ->whereHas('post', fn ($q) => $q->where('workspace_id', $workspaceId))
            ->when($platform, function ($q, string $platform) {
                $q->whereHas('socialPlatform', fn ($sq) => $sq->where('slug', $platform));
            });
    }
}
