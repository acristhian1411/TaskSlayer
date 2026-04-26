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
    public function summaryForUser(User $user, array $filters = []): array
    {
        $query = TaskExecution::query()
            ->where('user_id', $user->id);

        if (!empty($filters['from'])) {
            $query->where('started_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (!empty($filters['to'])) {
            $query->where('started_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        $totalSessions = (int) (clone $query)->count();
        $completedSessions = (int) (clone $query)->where('was_completed', true)->count();
        $totalDurationSeconds = (int) (clone $query)->sum('duration_seconds');

        return [
            'total_sessions' => $totalSessions,
            'completed_sessions' => $completedSessions,
            'completion_rate' => $totalSessions > 0 ? round($completedSessions / $totalSessions, 4) : 0,
            'total_duration_seconds' => $totalDurationSeconds,
        ];
    }

    public function timeByTaskForUser(User $user, array $filters = []): array
    {
        $query = DB::table('task_executions as te')
            ->join('tasks as t', 't.id', '=', 'te.task_id')
            ->where('te.user_id', $user->id);

        if (!empty($filters['from'])) {
            $query->where('te.started_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (!empty($filters['to'])) {
            $query->where('te.started_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query
            ->selectRaw('te.task_id, t.title_original, t.title_rpg, SUM(te.duration_seconds) as total_duration_seconds, COUNT(*) as sessions')
            ->groupBy('te.task_id', 't.title_original', 't.title_rpg')
            ->orderByDesc('total_duration_seconds')
            ->get()
            ->map(fn($row): array => [
                'task_id' => (int) $row->task_id,
                'title_original' => $row->title_original,
                'title_rpg' => $row->title_rpg,
                'total_duration_seconds' => (int) $row->total_duration_seconds,
                'sessions' => (int) $row->sessions,
            ])
            ->values()
            ->all();
    }

    public function dailyProductivityForUser(User $user, array $filters = []): array
    {
        $query = DB::table('task_executions as te')
            ->where('te.user_id', $user->id);

        if (!empty($filters['from'])) {
            $query->where('te.started_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (!empty($filters['to'])) {
            $query->where('te.started_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query
            ->selectRaw('DATE(te.started_at) as day, SUM(te.duration_seconds) as total_duration_seconds, COUNT(*) as sessions')
            ->groupBy(DB::raw('DATE(te.started_at)'))
            ->orderBy('day')
            ->get()
            ->map(fn($row): array => [
                'day' => $row->day,
                'total_duration_seconds' => (int) $row->total_duration_seconds,
                'sessions' => (int) $row->sessions,
            ])
            ->values()
            ->all();
    }

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

            $durationSeconds = $this->normalizeDurationSeconds($data['duration_seconds'] ?? 0);
            if ($endedAt !== null && !isset($data['duration_seconds'])) {
                $durationSeconds = $this->secondsBetween($startedAt, $endedAt);
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

    public function currentForTaskUser(User $user, int $taskId): ?TaskExecution
    {
        Task::query()
            ->where('user_id', $user->id)
            ->findOrFail($taskId);

        return TaskExecution::query()
            ->where('user_id', $user->id)
            ->where('task_id', $taskId)
            ->with(['task', 'events'])
            ->orderByRaw('CASE WHEN ended_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('started_at')
            ->first();
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

            if (array_key_exists('duration_seconds', $fillable)) {
                $fillable['duration_seconds'] = $this->normalizeDurationSeconds($fillable['duration_seconds']);
            }

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
                $execution->duration_seconds = $this->normalizeDurationSeconds($execution->duration_seconds)
                    + $this->secondsBetween($lastRunningAt, $effectivePause);
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
                    $execution->duration_seconds = $this->normalizeDurationSeconds($execution->duration_seconds)
                        + $this->secondsBetween($lastRunningAt, $effectiveEnd);
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

    private function normalizeDurationSeconds(mixed $seconds): int
    {
        if (!is_numeric($seconds)) {
            return 0;
        }

        return max(0, (int) floor((float) $seconds));
    }

    private function secondsBetween(Carbon $startedAt, Carbon $endedAt): int
    {
        return $this->normalizeDurationSeconds($startedAt->diffInSeconds($endedAt));
    }
}
