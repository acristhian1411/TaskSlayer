<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\UserPointsLedger;
use Carbon\Carbon;
use Tests\TestCase;

class PointsLedgerTest extends TestCase
{
    // ──────────────────────────── index ────────────────────────────

    public function test_index_returns_own_ledger_entries(): void
    {
        $user = $this->actingAsUser();
        UserPointsLedger::factory()->count(4)->create(['user_id' => $user->id]);
        UserPointsLedger::factory()->count(3)->create(); // other users

        $response = $this->getJson('/api/points/ledger');

        $response->assertOk();
        collect($response->json('data'))->each(
            fn($entry) => $this->assertEquals($user->id, $entry['user_id'])
        );
    }

    public function test_index_filters_by_source_type(): void
    {
        $user = $this->actingAsUser();
        UserPointsLedger::factory()->earned(50)->create(['user_id' => $user->id]);
        UserPointsLedger::factory()->earned(30)->create(['user_id' => $user->id]);
        UserPointsLedger::factory()->spent(20)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/points/ledger?source_type=task_completed');

        $response->assertOk();
        collect($response->json('data'))->each(
            fn($entry) => $this->assertEquals('task_completed', $entry['source_type'])
        );
    }

    public function test_index_filters_by_date_from(): void
    {
        $user = $this->actingAsUser();

        // Entry created "last month" — should be excluded
        UserPointsLedger::factory()->create([
            'user_id' => $user->id,
            'points' => 10,
            'created_at' => Carbon::now()->subMonth(),
        ]);

        // Entry created today — should be included
        UserPointsLedger::factory()->create([
            'user_id' => $user->id,
            'points' => 20,
            'created_at' => Carbon::now(),
        ]);

        $from = Carbon::now()->startOfDay()->toDateString();
        $response = $this->getJson("/api/points/ledger?from={$from}");

        $response->assertOk();
        foreach ($response->json('data') as $entry) {
            $this->assertGreaterThanOrEqual(
                Carbon::now()->startOfDay()->timestamp,
                Carbon::parse($entry['created_at'])->timestamp
            );
        }
    }

    // ──────────────────────────── show ────────────────────────────

    public function test_show_returns_own_entry(): void
    {
        $user = $this->actingAsUser();
        $entry = UserPointsLedger::factory()->create(['user_id' => $user->id]);

        $this->getJson("/api/points/ledger/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id);
    }

    public function test_show_returns_404_for_another_users_entry(): void
    {
        $this->actingAsUser();
        $entry = UserPointsLedger::factory()->create(); // other user

        $this->getJson("/api/points/ledger/{$entry->id}")->assertStatus(404);
    }

    // ──────────────────────────── summary ────────────────────────────

    public function test_summary_returns_earned_spent_and_balance(): void
    {
        $user = $this->actingAsUser();
        UserPointsLedger::factory()->earned(100)->create(['user_id' => $user->id]);
        UserPointsLedger::factory()->earned(50)->create(['user_id' => $user->id]);
        UserPointsLedger::factory()->spent(40)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/points/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['earned_points', 'spent_points', 'balance', 'entries_count'],
            ]);

        $data = $response->json('data');
        $this->assertEquals(150, $data['earned_points']);
        $this->assertEquals(40, $data['spent_points']);
        $this->assertEquals(110, $data['balance']);
        $this->assertEquals(3, $data['entries_count']);
    }

    public function test_summary_is_isolated_per_user(): void
    {
        $user = $this->actingAsUser();
        UserPointsLedger::factory()->earned(200)->create(['user_id' => $user->id]);

        // Another user's points should not pollute this user's summary
        UserPointsLedger::factory()->earned(999)->create();

        $response = $this->getJson('/api/points/summary');

        $this->assertEquals(200, $response->json('data.earned_points'));
    }

    public function test_summary_returns_zeros_for_new_user(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/points/summary');

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.balance'));
        $this->assertEquals(0, $response->json('data.earned_points'));
        $this->assertEquals(0, $response->json('data.spent_points'));
    }
}
