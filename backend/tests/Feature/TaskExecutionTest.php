<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskExecution;
use App\Models\UserPointsLedger;
use Tests\TestCase;

class TaskExecutionTest extends TestCase
{
    // ──────────────────────────── index ────────────────────────────

    public function test_index_returns_only_own_executions(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskExecution::factory()->count(2)->create(['user_id' => $user->id, 'task_id' => $task->id]);
        TaskExecution::factory()->count(3)->create(); // other users

        $response = $this->getJson('/api/task-executions');

        $response->assertOk();
        collect($response->json('data'))->each(
            fn($ex) => $this->assertEquals($user->id, $ex['user_id'])
        );
    }

    // ──────────────────────────── start ────────────────────────────

    public function test_start_creates_new_execution(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson("/api/tasks/{$task->id}/executions/start");

        $response->assertStatus(201)
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.was_completed', false);

        $this->assertDatabaseHas('task_executions', [
            'task_id' => $task->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_start_returns_existing_active_execution(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        // First start
        $first = $this->postJson("/api/tasks/{$task->id}/executions/start")->assertStatus(201)->json('data');

        // Second start should return the same execution
        $second = $this->postJson("/api/tasks/{$task->id}/executions/start")->assertStatus(201)->json('data');

        $this->assertEquals($first['id'], $second['id']);
        $this->assertEquals(1, TaskExecution::query()
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->count());
    }

    public function test_start_returns_404_for_another_users_task(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create(); // other user's task

        $this->postJson("/api/tasks/{$task->id}/executions/start")->assertStatus(404);
    }

    // ──────────────────────────── pause ────────────────────────────

    public function test_pause_accumulates_duration(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $execution = $this->postJson("/api/tasks/{$task->id}/executions/start")->json('data');
        $executionId = $execution['id'];

        $response = $this->postJson("/api/task-executions/{$executionId}/pause");

        $response->assertOk()
            ->assertJsonPath('data.id', $executionId);

        // Duration should be >= 0 after pause
        $this->assertGreaterThanOrEqual(0, $response->json('data.duration_seconds'));
    }

    // ──────────────────────────── resume ────────────────────────────

    public function test_resume_resumes_paused_execution(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $executionId = $this->postJson("/api/tasks/{$task->id}/executions/start")->json('data.id');
        $this->postJson("/api/task-executions/{$executionId}/pause");

        $response = $this->postJson("/api/task-executions/{$executionId}/resume");

        $response->assertOk()->assertJsonPath('data.id', $executionId);

        // Execution must still be open (no ended_at)
        $this->assertNull($response->json('data.ended_at'));
    }

    // ──────────────────────────── stop ────────────────────────────

    public function test_stop_closes_execution(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $executionId = $this->postJson("/api/tasks/{$task->id}/executions/start")->json('data.id');

        $response = $this->postJson("/api/task-executions/{$executionId}/stop");

        $response->assertOk()
            ->assertJsonPath('data.was_completed', false);

        $this->assertNotNull($response->json('data.ended_at'));
    }

    // ──────────────────────────── complete ────────────────────────────

    public function test_complete_closes_execution_and_awards_points(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id, 'reward_points' => 60]);

        $executionId = $this->postJson("/api/tasks/{$task->id}/executions/start")->json('data.id');

        $response = $this->postJson("/api/task-executions/{$executionId}/complete");

        $response->assertOk()
            ->assertJsonPath('data.was_completed', true);

        $this->assertNotNull($response->json('data.ended_at'));

        $this->assertDatabaseHas('user_points_ledger', [
            'user_id' => $user->id,
            'source_type' => 'task_completed',
            'source_id' => $task->id,
        ]);
    }

    // ──────────────────────────── show ────────────────────────────

    public function test_show_returns_own_execution(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $execution = TaskExecution::factory()->create(['user_id' => $user->id, 'task_id' => $task->id]);

        $this->getJson("/api/task-executions/{$execution->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $execution->id);
    }

    public function test_show_returns_404_for_another_users_execution(): void
    {
        $this->actingAsUser();
        $execution = TaskExecution::factory()->create();

        $this->getJson("/api/task-executions/{$execution->id}")->assertStatus(404);
    }

    // ──────────────────────────── destroy ────────────────────────────

    public function test_destroy_deletes_own_execution(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $execution = TaskExecution::factory()->create(['user_id' => $user->id, 'task_id' => $task->id]);

        $this->deleteJson("/api/task-executions/{$execution->id}")->assertOk();

        $this->assertDatabaseMissing('task_executions', ['id' => $execution->id]);
    }

    // ──────────────────────────── metrics ────────────────────────────

    public function test_summary_returns_user_metrics(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskExecution::factory()->count(2)->create(['user_id' => $user->id, 'task_id' => $task->id]);

        $response = $this->getJson('/api/task-executions/metrics/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total_sessions', 'completed_sessions', 'completion_rate', 'total_duration_seconds'],
            ]);

        $this->assertEquals(2, $response->json('data.total_sessions'));
    }

    public function test_summary_only_counts_own_executions(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskExecution::factory()->count(2)->create(['user_id' => $user->id, 'task_id' => $task->id]);
        TaskExecution::factory()->count(5)->create(); // other users

        $response = $this->getJson('/api/task-executions/metrics/summary');

        $this->assertEquals(2, $response->json('data.total_sessions'));
    }

    public function test_time_by_task_returns_aggregated_data(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskExecution::factory()->count(3)->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'duration_seconds' => 600,
        ]);

        $response = $this->getJson('/api/task-executions/metrics/time-by-task');

        $response->assertOk()->assertJsonStructure(['data']);
        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertEquals(1800, $items[0]['total_duration_seconds']);
        $this->assertEquals(3, $items[0]['sessions']);
    }

    public function test_daily_productivity_returns_per_day_data(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskExecution::factory()->count(2)->create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'started_at' => now(),
            'duration_seconds' => 300,
        ]);

        $response = $this->getJson('/api/task-executions/metrics/daily-productivity');

        $response->assertOk()->assertJsonStructure(['data']);
        $items = $response->json('data');
        $this->assertNotEmpty($items);
        $this->assertArrayHasKey('day', $items[0]);
        $this->assertArrayHasKey('total_duration_seconds', $items[0]);
    }
}
