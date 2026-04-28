<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialAccount extends Model
{
    protected $fillable = [
        'workspace_id',
        'social_platform_id',
        'platform_page_id',
        'platform_user_id',
        'account_name',
        'account_handle',
        'avatar',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'followers_count',
        'is_connected',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'meta' => 'array',
            'is_connected' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialPlatform::class, 'social_platform_id');
    }

    public function postTargets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }
}
