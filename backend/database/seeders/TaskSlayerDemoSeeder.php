<?php

namespace Database\Seeders;

use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskExecution;
use App\Models\User;
use App\Models\UserPointsLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class TaskSlayerDemoSeeder extends Seeder
{
    /**
     * Seed demo gameplay data for local development.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'demo@taskslayer.local'],
            [
                'name' => 'Demo Slayer',
                'password' => Hash::make('password'),
            ]
        );

        // Keep the demo dataset stable across repeated seeding runs.
        UserPointsLedger::where('user_id', $user->id)->delete();
        RewardRedemption::where('user_id', $user->id)->delete();
        Task::where('user_id', $user->id)->delete();

        $now = Carbon::now();

        $tasksData = [
            [
                'title_original' => 'Configurar Docker y levantar el entorno',
                'title_rpg' => 'Forja del Entorno Primordial',
                'description' => 'Levantar app, nginx y postgres con Docker Compose.',
                'difficulty_level' => 2,
                'reward_points' => 30,
                'status' => 'completed',
                'started_offset_minutes' => 220,
                'duration_seconds' => 3600,
                'was_completed' => true,
            ],
            [
                'title_original' => 'Diseñar el esquema de tareas y ejecuciones',
                'title_rpg' => 'Cartografia del Reino Productivo',
                'description' => 'Definir tablas para tareas, ejecuciones y eventos.',
                'difficulty_level' => 3,
                'reward_points' => 60,
                'status' => 'completed',
                'started_offset_minutes' => 140,
                'duration_seconds' => 5400,
                'was_completed' => true,
            ],
            [
                'title_original' => 'Crear endpoint para transformar tareas con IA',
                'title_rpg' => 'Invocacion del Oraculo Local',
                'description' => 'Integrar LM Studio y clasificar dificultad de tarea.',
                'difficulty_level' => 4,
                'reward_points' => 120,
                'status' => 'pending',
                'started_offset_minutes' => 45,
                'duration_seconds' => 900,
                'was_completed' => false,
            ],
        ];

        foreach ($tasksData as $taskData) {
            $startedAt = $now->copy()->subMinutes($taskData['started_offset_minutes']);
            $endedAt = $taskData['was_completed']
                ? $startedAt->copy()->addSeconds($taskData['duration_seconds'])
                : null;

            $task = Task::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'title_original' => $taskData['title_original'],
                ],
                [
                    'title_rpg' => $taskData['title_rpg'],
                    'description' => $taskData['description'],
                    'difficulty_level' => $taskData['difficulty_level'],
                    'reward_points' => $taskData['reward_points'],
                    'status' => $taskData['status'],
                ]
            );

            $execution = TaskExecution::updateOrCreate(
                [
                    'task_id' => $task->id,
                    'started_at' => $startedAt,
                ],
                [
                    'user_id' => $user->id,
                    'ended_at' => $endedAt,
                    'duration_seconds' => $taskData['duration_seconds'],
                    'was_completed' => $taskData['was_completed'],
                    'created_at' => $startedAt,
                ]
            );

            TaskEvent::updateOrCreate(
                [
                    'task_id' => $task->id,
                    'execution_id' => $execution->id,
                    'type' => 'start',
                    'timestamp' => $startedAt,
                ],
                [
                    'metadata' => ['source' => 'seeder'],
                ]
            );

            if ($taskData['was_completed'] && $endedAt !== null) {
                TaskEvent::updateOrCreate(
                    [
                        'task_id' => $task->id,
                        'execution_id' => $execution->id,
                        'type' => 'complete',
                        'timestamp' => $endedAt,
                    ],
                    [
                        'metadata' => ['source' => 'seeder'],
                    ]
                );

                UserPointsLedger::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'source_type' => 'task_completed',
                        'source_id' => $task->id,
                    ],
                    [
                        'points' => $task->reward_points,
                        'created_at' => $endedAt,
                    ]
                );
            }
        }

        $reward = Reward::where('duration_minutes', 15)->first();

        if ($reward !== null) {
            $redeemedAt = $now->copy()->subMinutes(20);

            $redemption = RewardRedemption::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'reward_id' => $reward->id,
                    'redeemed_at' => $redeemedAt,
                ],
                [
                    'points_spent' => $reward->cost_points,
                ]
            );

            UserPointsLedger::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'source_type' => 'reward_redeemed',
                    'source_id' => $redemption->id,
                ],
                [
                    'points' => -$reward->cost_points,
                    'created_at' => $redeemedAt,
                ]
            );
        }
    }
}
