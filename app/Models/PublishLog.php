<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLog extends Model
{
    protected $fillable = [
        'publish_job_id',
        'level',
        'message',
        'response',
        'http_status',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'http_status' => 'integer',
        ];
    }

    public function publishJob(): BelongsTo
    {
        return $this->belongsTo(PublishJob::class);
    }
}
