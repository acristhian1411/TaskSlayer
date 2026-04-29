<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'title_original',
    'title_rpg',
    'description',
    'difficulty_level',
    'reward_points',
    'status',
    'has_checkpoints',
])]
class Task extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'difficulty_level' => 'integer',
            'reward_points' => 'integer',
            'has_checkpoints' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(TaskExecution::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEvent::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(TaskCheckpoint::class)->orderBy('order_index');
    }
}
