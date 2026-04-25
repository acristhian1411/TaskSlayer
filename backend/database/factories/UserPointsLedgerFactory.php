<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\UserPointsLedger>
 */
class UserPointsLedgerFactory extends Factory
{
    protected $model = \App\Models\UserPointsLedger::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'points' => fake()->numberBetween(10, 120),
            'source_type' => 'task_completed',
            'source_id' => fake()->numberBetween(1, 100),
        ];
    }

    public function earned(int $points): static
    {
        return $this->state(['points' => $points, 'source_type' => 'task_completed']);
    }

    public function spent(int $points): static
    {
        return $this->state(['points' => -$points, 'source_type' => 'reward_redeemed']);
    }
}
