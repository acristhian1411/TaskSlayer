<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskExecution;
use App\Models\User;
use App\Models\UserPointsLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function listForUser(User $user): Collection
    {
        return Task::query()
            ->where('user_id', $user->id)
            ->with(['executions', 'events'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function createForUser(User $user, array $data): Task
    {
        return Task::create([
            'user_id' => $user->id,
            'title_original' => $data['title_original'],
            'title_rpg' => $data['title_rpg'],
            'description' => $data['description'] ?? null,
            'difficulty_level' => $data['difficulty_level'],
            'reward_points' => $data['reward_points'],
            'status' => $data['status'] ?? 'pending',
        ])->load(['executions', 'events']);
    }

    public function findForUser(User $user, int $taskId): Task
    {
        return Task::query()
            ->where('user_id', $user->id)
            ->with(['executions', 'events'])
            ->findOrFail($taskId);
    }

    public function updateForUser(User $user, int $taskId, array $data): Task
    {
        $task = $this->findForUser($user, $taskId);

        $task->fill($data);
        $task->save();

        return $task->fresh(['executions', 'events']);
    }

    public function deleteForUser(User $user, int $taskId): void
    {
        $task = $this->findForUser($user, $taskId);
        $task->delete();
    }

    public function completeForUser(User $user, int $taskId): Task
    {
        return DB::transaction(function () use ($user, $taskId): Task {
            $task = $this->findForUser($user, $taskId);

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
                ]
            );

            return $task->fresh(['executions', 'events']);
        });
    }

    public function uncompleteForUser(User $user, int $taskId): Task
    {
        return DB::transaction(function () use ($user, $taskId): Task {
            $task = $this->findForUser($user, $taskId);

            if ($task->status === 'completed') {
                $task->status = 'pending';
                $task->save();
            }

            UserPointsLedger::query()
                ->where('user_id', $user->id)
                ->where('source_type', 'task_completed')
                ->where('source_id', $task->id)
                ->delete();

            $latestCompletedExecution = TaskExecution::query()
                ->where('user_id', $user->id)
                ->where('task_id', $task->id)
                ->where('was_completed', true)
                ->orderByDesc('ended_at')
                ->orderByDesc('id')
                ->first();

            if ($latestCompletedExecution !== null) {
                $latestCompletedExecution->was_completed = false;
                $latestCompletedExecution->save();

                TaskEvent::query()
                    ->where('execution_id', $latestCompletedExecution->id)
                    ->where('type', 'complete')
                    ->orderByDesc('timestamp')
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['type' => 'stop']);
            }

            return $task->fresh(['executions', 'events']);
        });
    }
}
