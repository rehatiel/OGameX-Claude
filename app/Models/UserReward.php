<?php

namespace OGame\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $reward_key
 * @property Carbon $claimed_at
 * @property-read User $user
 * @mixin \Eloquent
 */
#[Fillable(['user_id', 'reward_key', 'claimed_at'])]
class UserReward extends Model
{
    public $timestamps = false;

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
