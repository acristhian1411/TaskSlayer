<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskCheckpoint;
use App\Models\TaskEvent;
use App\Models\TaskExecution;
use App\Models\User;
use App\Models\UserPointsLedger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class TaskService
{
    public function listForUser(User $user): Collection
    {
        return Task::query()
            ->where('user_id', $user->id)
            ->with(['executions', 'events', 'checkpoints'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function createForUser(User $user, array $data): Task
    {
        return DB::transaction(function () use ($user, $data): Task {
            $checkpoints = $this->normalizeCheckpointPayload($data['checkpoints'] ?? []);

            $task = Task::create([
                'user_id' => $user->id,
                'title_original' => $data['title_original'],
                'title_rpg' => $data['title_rpg'],
                'description' => $data['description'] ?? null,
                'difficulty_level' => $data['difficulty_level'],
                'reward_points' => $data['reward_points'],
                'status' => $data['status'] ?? 'pending',
                'has_checkpoints' => count($checkpoints) > 0,
            ]);

            $this->replaceCheckpoints($task, $checkpoints);

            return $task->fresh(['executions', 'events', 'checkpoints']);
        });
    }

    public function findForUser(User $user, int $taskId): Task
    {
        return Task::query()
            ->where('user_id', $user->id)
            ->with(['executions', 'events', 'checkpoints'])
            ->findOrFail($taskId);
    }

    public function updateForUser(User $user, int $taskId, array $data): Task
    {
        return DB::transaction(function () use ($user, $taskId, $data): Task {
            $task = $this->findForUser($user, $taskId);

            $checkpoints = null;
            if (array_key_exists('checkpoints', $data)) {
                $checkpoints = $this->normalizeCheckpointPayload($data['checkpoints'] ?? []);
                unset($data['checkpoints']);
            }

            $task->fill($data);

            if ($checkpoints !== null) {
                $task->has_checkpoints = count($checkpoints) > 0;
            }

            $task->save();

            if ($checkpoints !== null) {
                $this->replaceCheckpoints($task, $checkpoints);
            } elseif ($task->has_checkpoints) {
                $this->rebalanceCheckpointRewards($task);
            }

            return $task->fresh(['executions', 'events', 'checkpoints']);
        });
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

            $this->assertTaskCanBeCompleted($task);

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
                    'points' => $this->bossRewardPoints($task),
                ]
            );

            return $task->fresh(['executions', 'events', 'checkpoints']);
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

            return $task->fresh(['executions', 'events', 'checkpoints']);
        });
    }

    public function completeCheckpointForUser(User $user, int $taskId, int $checkpointId): Task
    {
        return DB::transaction(function () use ($user, $taskId, $checkpointId): Task {
            $task = $this->findForUser($user, $taskId);

            $checkpoint = TaskCheckpoint::query()
                ->where('task_id', $task->id)
                ->where('id', $checkpointId)
                ->firstOrFail();

            if (!$checkpoint->is_completed) {
                $checkpoint->is_completed = true;
                $checkpoint->save();

                UserPointsLedger::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'source_type' => 'task_checkpoint_completed',
                        'source_id' => $checkpoint->id,
                    ],
                    [
                        'points' => $checkpoint->reward_points_small,
                    ]
                );
            }

            return $task->fresh(['executions', 'events', 'checkpoints']);
        });
    }

    public function uncompleteCheckpointForUser(User $user, int $taskId, int $checkpointId): Task
    {
        return DB::transaction(function () use ($user, $taskId, $checkpointId): Task {
            $task = $this->findForUser($user, $taskId);

            $checkpoint = TaskCheckpoint::query()
                ->where('task_id', $task->id)
                ->where('id', $checkpointId)
                ->firstOrFail();

            if ($checkpoint->is_completed) {
                $checkpoint->is_completed = false;
                $checkpoint->save();

                UserPointsLedger::query()
                    ->where('user_id', $user->id)
                    ->where('source_type', 'task_checkpoint_completed')
                    ->where('source_id', $checkpoint->id)
                    ->delete();
            }

            if ($task->status === 'completed') {
                $task->status = 'pending';
                $task->save();

                UserPointsLedger::query()
                    ->where('user_id', $user->id)
                    ->where('source_type', 'task_completed')
                    ->where('source_id', $task->id)
                    ->delete();
            }

            return $task->fresh(['executions', 'events', 'checkpoints']);
        });
    }

    private function normalizeCheckpointPayload(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->map(function (mixed $checkpoint): ?string {
                if (!is_array($checkpoint)) {
                    return null;
                }

                $title = trim((string) ($checkpoint['title'] ?? ''));

                return $title !== '' ? $title : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function replaceCheckpoints(Task $task, array $checkpointTitles): void
    {
        $existingCheckpointIds = $task->checkpoints()->pluck('id')->all();

        if (count($existingCheckpointIds) > 0) {
            UserPointsLedger::query()
                ->where('user_id', $task->user_id)
                ->where('source_type', 'task_checkpoint_completed')
                ->whereIn('source_id', $existingCheckpointIds)
                ->delete();
        }

        $task->checkpoints()->delete();

        if (count($checkpointTitles) === 0) {
            return;
        }

        $rewards = $this->checkpointRewardDistribution($task->reward_points, count($checkpointTitles));

        foreach ($checkpointTitles as $index => $title) {
            TaskCheckpoint::create([
                'task_id' => $task->id,
                'title' => $title,
                'is_completed' => false,
                'order_index' => $index + 1,
                'reward_points_small' => $rewards[$index] ?? 0,
            ]);
        }
    }

    private function rebalanceCheckpointRewards(Task $task): void
    {
        $checkpoints = $task->checkpoints()->orderBy('order_index')->get();

        if ($checkpoints->isEmpty()) {
            $task->has_checkpoints = false;
            $task->save();

            return;
        }

        $rewards = $this->checkpointRewardDistribution($task->reward_points, $checkpoints->count());

        foreach ($checkpoints as $index => $checkpoint) {
            $checkpoint->reward_points_small = $rewards[$index] ?? 0;
            $checkpoint->save();
        }
    }

    private function checkpointRewardDistribution(int $taskRewardPoints, int $checkpointCount): array
    {
        if ($checkpointCount <= 0) {
            return [];
        }

        $checkpointPool = (int) floor(max(0, $taskRewardPoints) * 0.2);
        $base = intdiv($checkpointPool, $checkpointCount);
        $remainder = $checkpointPool % $checkpointCount;

        $distribution = [];
        for ($i = 0; $i < $checkpointCount; $i++) {
            $distribution[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $distribution;
    }

    private function bossRewardPoints(Task $task): int
    {
        if (!$task->has_checkpoints) {
            return (int) $task->reward_points;
        }

        $checkpointSum = (int) $task->checkpoints()->sum('reward_points_small');

        return max(0, (int) $task->reward_points - $checkpointSum);
    }

    private function assertTaskCanBeCompleted(Task $task): void
    {
        if (!$task->has_checkpoints) {
            return;
        }

        $pendingCheckpoints = $task->checkpoints()->where('is_completed', false)->count();

        if ($pendingCheckpoints > 0) {
            throw new LogicException('Complete all checkpoints before fighting the final boss.');
        }
    }
}
