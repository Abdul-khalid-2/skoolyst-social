<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostTarget extends Model
{
    protected $fillable = [
        'post_id',
        'social_account_id',
        'social_platform_id',
        'status',
        'platform_post_id',
        'likes_count',
        'comments_count',
        'shares_count',
        'reactions_count',
        'stats_synced_at',
        'published_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'stats_synced_at' => 'datetime',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'shares_count' => 'integer',
            'reactions_count' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function socialPlatform(): BelongsTo
    {
        return $this->belongsTo(SocialPlatform::class, 'social_platform_id');
    }
}
