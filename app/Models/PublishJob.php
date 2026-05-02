<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishJob extends Model
{
    protected $fillable = [
        'post_target_id',
        'job_id',
        'status',
        'attempts',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function postTarget(): BelongsTo
    {
        return $this->belongsTo(PostTarget::class);
    }

    public function publishLogs(): HasMany
    {
        return $this->hasMany(PublishLog::class);
    }
}
