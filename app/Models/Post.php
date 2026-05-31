<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'caption',
        'content',
        'image_url',
        'link_url',
        'platforms',
        'status',
        'scheduled_at',
        'published_at',
        'timezone',
        'ai_generated',
        'fb_post_id',
        'ig_post_id',
        'fb_error',
        'ig_error',
    ];

    protected function casts(): array
    {
        return [
            'ai_generated' => 'boolean',
            'platforms' => 'array',
        ];
    }

    /**
     * DB stores UTC; expose app-local time on read, persist UTC on write.
     */
    protected function scheduledAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : Carbon::parse($value, 'UTC')->timezone(config('app.timezone')),
            set: fn ($value) => $value === null
                ? null
                : Carbon::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }

    protected function publishedAt(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : Carbon::parse($value, 'UTC')->timezone(config('app.timezone')),
            set: fn ($value) => $value === null
                ? null
                : Carbon::parse($value)->utc()->format('Y-m-d H:i:s'),
        );
    }

    public static function parseScheduledInput(string $value): Carbon
    {
        return Carbon::parse($value, config('app.timezone'));
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function postMedia(): HasMany
    {
        return $this->hasMany(PostMedia::class);
    }

    public function postTargets(): HasMany
    {
        return $this->hasMany(PostTarget::class);
    }
}
