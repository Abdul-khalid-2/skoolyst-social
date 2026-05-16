<?php

namespace App\Models;

use App\Support\PlanConfig;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'workspace_id', 'plan', 'status',
        'started_at', 'expires_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at'   => 'datetime',
            'expires_at'   => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' || $this->status === 'trialing';
    }

    public function planConfig(): array
    {
        return PlanConfig::get($this->plan);
    }

    public function daysRemaining(): ?int
    {
        if (! $this->expires_at) {
            return null;
        }
        $diff = Carbon::now()->diffInDays($this->expires_at, false);

        return max(0, (int) $diff);
    }
}
