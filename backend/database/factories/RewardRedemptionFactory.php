<?php

namespace Database\Factories;

use App\Models\Reward;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\RewardRedemption>
 */
class RewardRedemptionFactory extends Factory
{
    public function definition(): array
    {
        $reward = Reward::factory()->create();

        return [
            'user_id' => User::factory(),
            'reward_id' => $reward->id,
            'points_spent' => $reward->cost_points,
            'redeemed_at' => Carbon::now(),
        ];
    }
}
