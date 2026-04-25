<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'task_id',
    'execution_id',
    'type',
    'timestamp',
    'metadata',
])]
class TaskEvent extends Model
{
    use HasFactory;

    public const CREATED_AT = null;
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'execution_id' => 'integer',
            'timestamp' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(TaskExecution::class, 'execution_id');
    }
}
