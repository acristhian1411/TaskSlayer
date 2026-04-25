<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskExecution;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskEventService
{
    public function listForUser(User $user, array $filters = []): Collection
    {
        $query = TaskEvent::query()
            ->whereHas('task', function ($taskQuery) use ($user): void {
                $taskQuery->where('user_id', $user->id);
            })
            ->with(['task', 'execution'])
            ->orderByDesc('timestamp')
            ->orderByDesc('id');

        if (!empty($filters['task_id'])) {
            $query->where('task_id', $filters['task_id']);
        }

        if (!empty($filters['execution_id'])) {
            $query->where('execution_id', $filters['execution_id']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (!empty($filters['from'])) {
            $query->where('timestamp', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (!empty($filters['to'])) {
            $query->where('timestamp', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query->get();
    }

    public function createForUser(User $user, array $data): TaskEvent
    {
        return DB::transaction(function () use ($user, $data): TaskEvent {
            $task = Task::query()
                ->where('user_id', $user->id)
                ->findOrFail($data['task_id']);

            $executionId = $data['execution_id'] ?? null;

            if ($executionId !== null) {
                $execution = TaskExecution::query()
                    ->where('user_id', $user->id)
                    ->findOrFail($executionId);

                if ($execution->task_id !== $task->id) {
                    throw ValidationException::withMessages([
                        'execution_id' => 'La ejecucion no pertenece a la tarea indicada.',
                    ]);
                }
            }

            return TaskEvent::create([
                'task_id' => $task->id,
                'execution_id' => $executionId,
                'type' => $data['type'],
                'timestamp' => Carbon::parse($data['timestamp']),
                'metadata' => $data['metadata'] ?? null,
            ])->load(['task', 'execution']);
        });
    }

    public function findForUser(User $user, int $eventId): TaskEvent
    {
        return TaskEvent::query()
            ->whereHas('task', function ($taskQuery) use ($user): void {
                $taskQuery->where('user_id', $user->id);
            })
            ->with(['task', 'execution'])
            ->findOrFail($eventId);
    }

    public function deleteForUser(User $user, int $eventId): void
    {
        $event = $this->findForUser($user, $eventId);
        $event->delete();
    }

    public function timelineForExecution(User $user, int $executionId): Collection
    {
        $this->findExecutionForUser($user, $executionId);

        return TaskEvent::query()
            ->where('execution_id', $executionId)
            ->with(['task', 'execution'])
            ->orderBy('timestamp')
            ->orderBy('id')
            ->get();
    }

    public function statsForExecution(User $user, int $executionId): array
    {
        $this->findExecutionForUser($user, $executionId);

        $baseQuery = TaskEvent::query()
            ->where('execution_id', $executionId);

        $counts = (clone $baseQuery)
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return [
            'execution_id' => $executionId,
            'total_events' => (int) (clone $baseQuery)->count(),
            'pause_count' => (int) ($counts['pause'] ?? 0),
            'resume_count' => (int) ($counts['resume'] ?? 0),
            'start_count' => (int) ($counts['start'] ?? 0),
            'stop_count' => (int) ($counts['stop'] ?? 0),
            'complete_count' => (int) ($counts['complete'] ?? 0),
            'first_event_at' => (clone $baseQuery)->orderBy('timestamp')->value('timestamp'),
            'last_event_at' => (clone $baseQuery)->orderByDesc('timestamp')->value('timestamp'),
        ];
    }

    private function findExecutionForUser(User $user, int $executionId): TaskExecution
    {
        return TaskExecution::query()
            ->where('user_id', $user->id)
            ->findOrFail($executionId);
    }
}
