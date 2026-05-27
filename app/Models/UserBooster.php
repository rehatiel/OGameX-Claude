<?php

namespace OGame\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBooster extends Model
{
    protected $fillable = [
        'user_id',
        'ref',
        'amount',
        'activated_at',
        'expires_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->expires_at !== null && $this->expires_at->timestamp > now()->timestamp;
    }

    public function secondsLeft(): int
    {
        if (!$this->isActive()) {
            return 0;
        }
        return max(0, $this->expires_at->timestamp - now()->timestamp);
    }
}
