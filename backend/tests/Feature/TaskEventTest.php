<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskEvent;
use App\Models\TaskExecution;
use Carbon\Carbon;
use Tests\TestCase;

class TaskEventTest extends TestCase
{
    // ──────────────────────────── index ────────────────────────────

    public function test_index_returns_only_own_events(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        TaskEvent::factory()->count(3)->create(['task_id' => $task->id]);

        // Events belonging to other user's tasks
        TaskEvent::factory()->count(5)->create();

        $response = $this->getJson('/api/task-events');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('task_id')->unique()->all();
        foreach ($ids as $taskId) {
            $this->assertEquals($user->id, Task::find($taskId)->user_id);
        }
    }

    public function test_index_filters_by_execution_id(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $execution = TaskExecution::factory()->create(['user_id' => $user->id, 'task_id' => $task->id]);

        TaskEvent::factory()->count(2)->create([
            'task_id' => $task->id,
            'execution_id' => $execution->id,
        ]);
        TaskEvent::factory()->count(2)->create([
            'task_id' => $task->id,
            'execution_id' => null,
        ]);

        $response = $this->getJson("/api/task-events?execution_id={$execution->id}");

        $response->assertOk();
        collect($response->json('data'))->each(
            fn($event) => $this->assertEquals($execution->id, $event['execution_id'])
        );
    }

    // ──────────────────────────── store ────────────────────────────

    public function test_store_creates_event_for_own_task(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->postJson('/api/task-events', [
            'task_id' => $task->id,
            'type' => 'start',
            'timestamp' => Carbon::now()->toISOString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.task_id', $task->id)
            ->assertJsonPath('data.type', 'start');

        $this->assertDatabaseHas('task_events', [
            'task_id' => $task->id,
            'type' => 'start',
        ]);
    }

    public function test_store_fails_for_another_users_task(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create(); // other user's task

        $this->postJson('/api/task-events', [
            'task_id' => $task->id,
            'type' => 'start',
            'timestamp' => Carbon::now()->toISOString(),
        ])->assertStatus(404);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/task-events', [])->assertStatus(422);
    }

    // ──────────────────────────── show ────────────────────────────

    public function test_show_returns_own_event(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $event = TaskEvent::factory()->create(['task_id' => $task->id]);

        $this->getJson("/api/task-events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id);
    }

    public function test_show_returns_404_for_another_users_event(): void
    {
        $this->actingAsUser();
        $event = TaskEvent::factory()->create(); // other user's task

        $this->getJson("/api/task-events/{$event->id}")->assertStatus(404);
    }

    // ──────────────────────────── destroy ────────────────────────────

    public function test_destroy_deletes_own_event(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $event = TaskEvent::factory()->create(['task_id' => $task->id]);

        $this->deleteJson("/api/task-events/{$event->id}")->assertOk();

        $this->assertDatabaseMissing('task_events', ['id' => $event->id]);
    }

    public function test_destroy_returns_404_for_another_users_event(): void
    {
        $this->actingAsUser();
        $event = TaskEvent::factory()->create();

        $this->deleteJson("/api/task-events/{$event->id}")->assertStatus(404);
    }

    // ──────────────────────────── execution timeline ────────────────────────────

    public function test_execution_timeline_returns_events_for_execution(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $execution = TaskExecution::factory()->create(['user_id' => $user->id, 'task_id' => $task->id]);

        TaskEvent::factory()->count(3)->create([
            'task_id' => $task->id,
            'execution_id' => $execution->id,
        ]);

        $response = $this->getJson("/api/task-executions/{$execution->id}/events");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
        collect($response->json('data'))->each(
            fn($event) => $this->assertEquals($execution->id, $event['execution_id'])
        );
    }

    public function test_execution_timeline_returns_404_for_another_users_execution(): void
    {
        $this->actingAsUser();
        $execution = TaskExecution::factory()->create(); // other user

        $this->getJson("/api/task-executions/{$execution->id}/events")->assertStatus(404);
    }

    // ──────────────────────────── execution stats ────────────────────────────

    public function test_execution_stats_returns_event_counts(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);
        $execution = TaskExecution::factory()->create(['user_id' => $user->id, 'task_id' => $task->id]);

        TaskEvent::factory()->ofType('start')->create(['task_id' => $task->id, 'execution_id' => $execution->id]);
        TaskEvent::factory()->ofType('pause')->create(['task_id' => $task->id, 'execution_id' => $execution->id]);
        TaskEvent::factory()->ofType('pause')->create(['task_id' => $task->id, 'execution_id' => $execution->id]);
        TaskEvent::factory()->ofType('complete')->create(['task_id' => $task->id, 'execution_id' => $execution->id]);

        $response = $this->getJson("/api/task-executions/{$execution->id}/events/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'execution_id',
                    'total_events',
                    'pause_count',
                    'resume_count',
                    'start_count',
                    'stop_count',
                    'complete_count',
                ],
            ]);

        $this->assertEquals(4, $response->json('data.total_events'));
        $this->assertEquals(2, $response->json('data.pause_count'));
        $this->assertEquals(1, $response->json('data.complete_count'));
    }
}
