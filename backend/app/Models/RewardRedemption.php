<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'reward_id',
    'points_spent',
    'redeemed_at',
])]
class RewardRedemption extends Model
{
    use HasFactory;

    public const CREATED_AT = 'redeemed_at';
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'points_spent' => 'integer',
            'redeemed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }
}
