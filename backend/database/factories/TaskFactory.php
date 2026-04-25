<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        $level = fake()->numberBetween(1, 4);
        $pointsMap = [1 => 10, 2 => 30, 3 => 60, 4 => 120];

        return [
            'user_id' => User::factory(),
            'title_original' => fake()->sentence(4),
            'title_rpg' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'difficulty_level' => $level,
            'reward_points' => $pointsMap[$level],
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function archived(): static
    {
        return $this->state(['status' => 'archived']);
    }
}
