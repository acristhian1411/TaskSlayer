<?php

namespace Database\Seeders;

use App\Models\Reward;
use Illuminate\Database\Seeder;

class RewardsSeeder extends Seeder
{
    /**
     * Seed the rewards catalog.
     */
    public function run(): void
    {
        $rewards = [
            [
                'name' => '5 min de Social Media',
                'cost_points' => 10,
                'reward_type' => 'time',
                'duration_minutes' => 5,
            ],
            [
                'name' => '15 min de Videojuegos',
                'cost_points' => 30,
                'reward_type' => 'time',
                'duration_minutes' => 15,
            ],
            [
                'name' => '30 min de Videojuegos / Serie',
                'cost_points' => 60,
                'reward_type' => 'time',
                'duration_minutes' => 30,
            ],
            [
                'name' => '60 min de Ocio Libre',
                'cost_points' => 120,
                'reward_type' => 'time',
                'duration_minutes' => 60,
            ],
        ];

        foreach ($rewards as $reward) {
            Reward::updateOrCreate(
                ['name' => $reward['name']],
                [
                    'cost_points' => $reward['cost_points'],
                    'reward_type' => $reward['reward_type'],
                    'duration_minutes' => $reward['duration_minutes'],
                ]
            );
        }
    }
}
