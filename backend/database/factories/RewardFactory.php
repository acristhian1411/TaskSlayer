<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Reward>
 */
class RewardFactory extends Factory
{
    public function definition(): array
    {
        $minutesMap = [5 => 10, 15 => 30, 30 => 60, 60 => 120];
        $minutes = fake()->randomElement(array_keys($minutesMap));

        return [
            'name' => fake()->words(3, true),
            'cost_points' => $minutesMap[$minutes],
            'reward_type' => 'time',
            'duration_minutes' => $minutes,
        ];
    }

    public function costing(int $points): static
    {
        return $this->state(['cost_points' => $points]);
    }
}
