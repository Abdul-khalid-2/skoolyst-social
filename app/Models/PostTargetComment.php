<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostTargetComment extends Model
{
    protected $fillable = [
        'post_target_id',
        'platform_comment_id',
        'parent_id',
        'author_name',
        'message',
        'platform_created_at',
    ];

    protected function casts(): array
    {
        return [
            'platform_created_at' => 'datetime',
        ];
    }

    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('platform_created_at');
    }
}
