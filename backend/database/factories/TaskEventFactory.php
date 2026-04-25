<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskExecution;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\TaskEvent>
 */
class TaskEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'execution_id' => null,
            'type' => fake()->randomElement(['start', 'pause', 'resume', 'stop', 'complete']),
            'timestamp' => Carbon::now(),
            'metadata' => null,
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function forExecution(TaskExecution $execution): static
    {
        return $this->state([
            'task_id' => $execution->task_id,
            'execution_id' => $execution->id,
        ]);
    }
}
