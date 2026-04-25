<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use App\Models\UserPointsLedger;
use Tests\TestCase;

class TaskTest extends TestCase
{
    // ──────────────────────────── index ────────────────────────────

    public function test_index_returns_only_authenticated_user_tasks(): void
    {
        $user = $this->actingAsUser();
        Task::factory()->count(3)->create(['user_id' => $user->id]);
        Task::factory()->count(2)->create(); // belongs to other users

        $response = $this->getJson('/api/tasks');

        $response->assertOk();
        // All returned tasks must belong to the current user
        collect($response->json('data'))->each(
            fn($task) => $this->assertEquals($user->id, $task['user_id'])
        );
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
    }

    // ──────────────────────────── store ────────────────────────────

    public function test_store_creates_task_for_authenticated_user(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/tasks', [
            'title_original' => 'Write unit tests',
            'title_rpg' => 'Forge the TestScrolls',
            'difficulty_level' => 2,
            'reward_points' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.title_original', 'Write unit tests')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tasks', [
            'user_id' => $user->id,
            'title_original' => 'Write unit tests',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/tasks', [])->assertStatus(422);
    }

    // ──────────────────────────── show ────────────────────────────

    public function test_show_returns_own_task(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
    }

    public function test_show_returns_404_for_another_users_task(): void
    {
        $this->actingAsUser(); // authenticated as different user
        $task = Task::factory()->create(); // belongs to another user

        $this->getJson("/api/tasks/{$task->id}")->assertStatus(404);
    }

    // ──────────────────────────── update ────────────────────────────

    public function test_update_modifies_task(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $response = $this->putJson("/api/tasks/{$task->id}", [
            'title_original' => 'Updated title',
            'title_rpg' => 'Updated RPG',
            'difficulty_level' => 3,
            'reward_points' => 60,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title_original', 'Updated title');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title_original' => 'Updated title',
        ]);
    }

    public function test_update_returns_404_for_another_users_task(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create();

        $this->putJson("/api/tasks/{$task->id}", [
            'title_original' => 'Hack',
            'title_rpg' => 'Hack RPG',
            'difficulty_level' => 1,
            'reward_points' => 10,
        ])->assertStatus(404);
    }

    // ──────────────────────────── destroy ────────────────────────────

    public function test_destroy_deletes_own_task(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create(['user_id' => $user->id]);

        $this->deleteJson("/api/tasks/{$task->id}")->assertOk();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_destroy_returns_404_for_another_users_task(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create();

        $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(404);
    }

    // ──────────────────────────── complete ────────────────────────────

    public function test_complete_marks_task_as_completed_and_awards_points(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'reward_points' => 30,
        ]);

        $response = $this->postJson("/api/tasks/{$task->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('user_points_ledger', [
            'user_id' => $user->id,
            'source_type' => 'task_completed',
            'source_id' => $task->id,
            'points' => 30,
        ]);
    }

    public function test_complete_is_idempotent_for_points(): void
    {
        $user = $this->actingAsUser();
        $task = Task::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'reward_points' => 30,
        ]);

        // Complete twice
        $this->postJson("/api/tasks/{$task->id}/complete")->assertOk();
        $this->postJson("/api/tasks/{$task->id}/complete")->assertOk();

        // Points must be awarded only once
        $this->assertEquals(1, UserPointsLedger::query()
            ->where('user_id', $user->id)
            ->where('source_type', 'task_completed')
            ->where('source_id', $task->id)
            ->count());
    }
}
