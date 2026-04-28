<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPlatform extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon',
        'color',
        'is_active',
        'supports_scheduling',
        'supports_media',
        'character_limit',
        'connection_options',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'supports_scheduling' => 'boolean',
            'supports_media' => 'boolean',
            'connection_options' => 'array',
        ];
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }
}
