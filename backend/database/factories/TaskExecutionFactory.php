<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TaskExecution>
 */
class TaskExecutionFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = Carbon::now()->subMinutes(fake()->numberBetween(10, 120));

        return [
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => null,
            'duration_seconds' => 0,
            'was_completed' => false,
        ];
    }

    public function completed(int $durationSeconds = 1800): static
    {
        return $this->state(function (array $attributes) use ($durationSeconds) {
            $startedAt = Carbon::parse($attributes['started_at']);

            return [
                'ended_at' => $startedAt->copy()->addSeconds($durationSeconds),
                'duration_seconds' => $durationSeconds,
                'was_completed' => true,
            ];
        });
    }

    public function stopped(int $durationSeconds = 600): static
    {
        return $this->state(function (array $attributes) use ($durationSeconds) {
            $startedAt = Carbon::parse($attributes['started_at']);

            return [
                'ended_at' => $startedAt->copy()->addSeconds($durationSeconds),
                'duration_seconds' => $durationSeconds,
                'was_completed' => false,
            ];
        });
    }
}
