<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskExecution;
use App\Models\User;
use App\Models\UserPointsLedger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskExecutionService
{
    public function listForUser(User $user): Collection
    {
        return TaskExecution::query()
            ->where('user_id', $user->id)
            ->with(['task', 'events'])
            ->orderByDesc('started_at')
            ->get();
    }

    public function createForUser(User $user, array $data): TaskExecution
    {
        return DB::transaction(function () use ($user, $data): TaskExecution {
            $task = Task::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['task_id']);

            $startedAt = Carbon::parse($data['started_at']);
            $endedAt = isset($data['ended_at']) ? Carbon::parse($data['ended_at']) : null;
            $wasCompleted = (bool) ($data['was_completed'] ?? false);

            $durationSeconds = (int) ($data['duration_seconds'] ?? 0);
            if ($endedAt !== null && !isset($data['duration_seconds'])) {
                $durationSeconds = max(0, $startedAt->diffInSeconds($endedAt));
            }

            $execution = TaskExecution::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'duration_seconds' => $durationSeconds,
                'was_completed' => $wasCompleted,
            ]);

            $this->createEvent($task->id, $execution->id, 'start', $startedAt, ['source' => 'manual_create']);

            if ($endedAt !== null) {
                $eventType = $wasCompleted ? 'complete' : 'stop';
                $this->createEvent($task->id, $execution->id, $eventType, $endedAt, ['source' => 'manual_create']);

                if ($wasCompleted) {
                    $this->awardCompletionPoints($user, $task, $endedAt);
                }
            }

            return $execution->fresh(['task', 'events']);
        });
    }

    public function findForUser(User $user, int $executionId): TaskExecution
    {
        return TaskExecution::query()
            ->where('user_id', $user->id)
            ->with(['task', 'events'])
            ->findOrFail($executionId);
    }

    public function updateForUser(User $user, int $executionId, array $data): TaskExecution
    {
        return DB::transaction(function () use ($user, $executionId, $data): TaskExecution {
            $execution = $this->findForUser($user, $executionId);

            $fillable = array_intersect_key($data, array_flip([
                'started_at',
                'ended_at',
                'duration_seconds',
                'was_completed',
            ]));

            $execution->fill($fillable);
            $execution->save();

            if (($fillable['was_completed'] ?? false) === true) {
                $this->awardCompletionPoints($user, $execution->task, Carbon::now());
            }

            return $execution->fresh(['task', 'events']);
        });
    }

    public function deleteForUser(User $user, int $executionId): void
    {
        $execution = $this->findForUser($user, $executionId);
        $execution->delete();
    }

    public function startForUser(User $user, int $taskId, ?string $startedAt = null): TaskExecution
    {
        return DB::transaction(function () use ($user, $taskId, $startedAt): TaskExecution {
            $task = Task::query()
                ->where('user_id', $user->id)
                ->findOrFail($taskId);

            $activeExecution = TaskExecution::query()
                ->where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->whereNull('ended_at')
                ->latest('started_at')
                ->first();

            if ($activeExecution !== null) {
                return $activeExecution->load(['task', 'events']);
            }

            $effectiveStart = $startedAt ? Carbon::parse($startedAt) : Carbon::now();

            $execution = TaskExecution::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => $effectiveStart,
                'duration_seconds' => 0,
                'was_completed' => false,
            ]);

            $this->createEvent($task->id, $execution->id, 'start', $effectiveStart, ['source' => 'api']);

            return $execution->fresh(['task', 'events']);
        });
    }

    public function pauseForUser(User $user, int $executionId, ?string $pausedAt = null): TaskExecution
    {
        return DB::transaction(function () use ($user, $executionId, $pausedAt): TaskExecution {
            $execution = $this->findForUser($user, $executionId);

            if ($execution->ended_at !== null || $this->isPaused($execution)) {
                return $execution;
            }

            $effectivePause = $pausedAt ? Carbon::parse($pausedAt) : Carbon::now();
            $lastRunningAt = $this->lastRunningMarker($execution);

            if ($effectivePause->greaterThan($lastRunningAt)) {
                $execution->duration_seconds += $lastRunningAt->diffInSeconds($effectivePause);
                $execution->save();
            }

            $this->createEvent($execution->task_id, $execution->id, 'pause', $effectivePause, ['source' => 'api']);

            return $execution->fresh(['task', 'events']);
        });
    }

    public function resumeForUser(User $user, int $executionId, ?string $resumedAt = null): TaskExecution
    {
        return DB::transaction(function () use ($user, $executionId, $resumedAt): TaskExecution {
            $execution = $this->findForUser($user, $executionId);

            if ($execution->ended_at !== null || !$this->isPaused($execution)) {
                return $execution;
            }

            $effectiveResume = $resumedAt ? Carbon::parse($resumedAt) : Carbon::now();
            $this->createEvent($execution->task_id, $execution->id, 'resume', $effectiveResume, ['source' => 'api']);

            return $execution->fresh(['task', 'events']);
        });
    }

    public function stopForUser(User $user, int $executionId, ?string $endedAt = null, bool $wasCompleted = false): TaskExecution
    {
        return DB::transaction(function () use ($user, $executionId, $endedAt, $wasCompleted): TaskExecution {
            $execution = $this->findForUser($user, $executionId);

            if ($execution->ended_at !== null) {
                return $execution;
            }

            $effectiveEnd = $endedAt ? Carbon::parse($endedAt) : Carbon::now();

            if (!$this->isPaused($execution)) {
                $lastRunningAt = $this->lastRunningMarker($execution);

                if ($effectiveEnd->greaterThan($lastRunningAt)) {
                    $execution->duration_seconds += $lastRunningAt->diffInSeconds($effectiveEnd);
                }
            }

            $execution->ended_at = $effectiveEnd;
            $execution->was_completed = $wasCompleted;
            $execution->save();

            $eventType = $wasCompleted ? 'complete' : 'stop';
            $this->createEvent($execution->task_id, $execution->id, $eventType, $effectiveEnd, ['source' => 'api']);

            if ($wasCompleted) {
                $this->awardCompletionPoints($user, $execution->task, $effectiveEnd);
            }

            return $execution->fresh(['task', 'events']);
        });
    }

    public function completeForUser(User $user, int $executionId, ?string $endedAt = null): TaskExecution
    {
        return $this->stopForUser($user, $executionId, $endedAt, true);
    }

    private function awardCompletionPoints(User $user, Task $task, Carbon $awardedAt): void
    {
        if ($task->status !== 'completed') {
            $task->status = 'completed';
            $task->save();
        }

        UserPointsLedger::firstOrCreate(
            [
                'user_id' => $user->id,
                'source_type' => 'task_completed',
                'source_id' => $task->id,
            ],
            [
                'points' => $task->reward_points,
                'created_at' => $awardedAt,
            ]
        );
    }

    private function isPaused(TaskExecution $execution): bool
    {
        $lastEvent = $this->lastLifecycleEvent($execution);

        return $lastEvent?->type === 'pause';
    }

    private function lastRunningMarker(TaskExecution $execution): Carbon
    {
        $lastRunningEvent = TaskEvent::query()
            ->where('execution_id', $execution->id)
            ->whereIn('type', ['start', 'resume'])
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->first();

        if ($lastRunningEvent === null) {
            return Carbon::parse($execution->started_at);
        }

        return Carbon::parse($lastRunningEvent->timestamp);
    }

    private function lastLifecycleEvent(TaskExecution $execution): ?TaskEvent
    {
        return TaskEvent::query()
            ->where('execution_id', $execution->id)
            ->whereIn('type', ['start', 'pause', 'resume', 'stop', 'complete'])
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->first();
    }

    private function createEvent(int $taskId, int $executionId, string $type, Carbon $at, array $metadata = []): TaskEvent
    {
        return TaskEvent::create([
            'task_id' => $taskId,
            'execution_id' => $executionId,
            'type' => $type,
            'timestamp' => $at,
            'metadata' => $metadata,
        ]);
    }
}
