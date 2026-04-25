<?php

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\User;
use App\Models\UserPointsLedger;
use Database\Factories\UserPointsLedgerFactory;
use Tests\TestCase;

class RewardTest extends TestCase
{
    // ──────────────────────────── index ────────────────────────────

    public function test_index_returns_all_rewards(): void
    {
        $this->actingAsUser();
        Reward::factory()->count(3)->create();

        $response = $this->getJson('/api/rewards');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    // ──────────────────────────── store ────────────────────────────

    public function test_store_creates_reward(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/rewards', [
            'name' => 'Coffee Break',
            'cost_points' => 50,
            'reward_type' => 'time',
            'duration_minutes' => 15,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Coffee Break')
            ->assertJsonPath('data.cost_points', 50);

        $this->assertDatabaseHas('rewards', ['name' => 'Coffee Break']);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/rewards', [])->assertStatus(422);
    }

    // ──────────────────────────── show ────────────────────────────

    public function test_show_returns_reward(): void
    {
        $this->actingAsUser();
        $reward = Reward::factory()->create();

        $this->getJson("/api/rewards/{$reward->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $reward->id);
    }

    public function test_show_returns_404_for_nonexistent_reward(): void
    {
        $this->actingAsUser();
        $this->getJson('/api/rewards/9999')->assertStatus(404);
    }

    // ──────────────────────────── update ────────────────────────────

    public function test_update_modifies_reward(): void
    {
        $this->actingAsUser();
        $reward = Reward::factory()->create();

        $response = $this->putJson("/api/rewards/{$reward->id}", [
            'name' => 'Updated Reward',
            'cost_points' => 75,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Reward');
    }

    // ──────────────────────────── destroy ────────────────────────────

    public function test_destroy_deletes_reward(): void
    {
        $this->actingAsUser();
        $reward = Reward::factory()->create();

        $this->deleteJson("/api/rewards/{$reward->id}")->assertOk();

        $this->assertDatabaseMissing('rewards', ['id' => $reward->id]);
    }

    // ──────────────────────────── redeem ────────────────────────────

    public function test_redeem_deducts_points_and_creates_ledger_entry(): void
    {
        $user = $this->actingAsUser();
        $reward = Reward::factory()->costing(50)->create();

        // Give the user enough points
        UserPointsLedger::factory()->earned(100)->create(['user_id' => $user->id]);

        $response = $this->postJson("/api/rewards/{$reward->id}/redeem");

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['redemption', 'points_balance']]);

        // A negative ledger entry for the redemption must exist
        $this->assertDatabaseHas('user_points_ledger', [
            'user_id' => $user->id,
            'source_type' => 'reward_redeemed',
            'points' => -50,
        ]);

        // A redemption record must exist
        $this->assertDatabaseHas('reward_redemptions', [
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'points_spent' => 50,
        ]);
    }

    public function test_redeem_fails_with_insufficient_balance(): void
    {
        $user = $this->actingAsUser();
        $reward = Reward::factory()->costing(200)->create();

        // User has only 50 points
        UserPointsLedger::factory()->earned(50)->create(['user_id' => $user->id]);

        $response = $this->postJson("/api/rewards/{$reward->id}/redeem");

        $response->assertStatus(422);

        // No redemption should have been created
        $this->assertDatabaseMissing('reward_redemptions', [
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);
    }

    public function test_redeem_fails_with_zero_balance(): void
    {
        $user = $this->actingAsUser();
        $reward = Reward::factory()->costing(10)->create();

        $response = $this->postJson("/api/rewards/{$reward->id}/redeem");

        $response->assertStatus(422);
    }

    // ──────────────────────────── balance ────────────────────────────

    public function test_points_balance_returns_sum(): void
    {
        $user = $this->actingAsUser();
        UserPointsLedger::factory()->earned(100)->create(['user_id' => $user->id]);
        UserPointsLedger::factory()->earned(50)->create(['user_id' => $user->id]);
        UserPointsLedger::factory()->spent(30)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/points/balance');

        $response->assertOk()
            ->assertJsonPath('data.points_balance', 120);
    }

    public function test_points_balance_is_zero_for_new_user(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/points/balance');

        $response->assertOk()
            ->assertJsonPath('data.points_balance', 0);
    }

    // ──────────────────────────── redemptions ────────────────────────────

    public function test_my_redemptions_returns_own_redemptions(): void
    {
        $user = $this->actingAsUser();
        $reward = Reward::factory()->costing(10)->create();

        // Give points and redeem
        UserPointsLedger::factory()->earned(100)->create(['user_id' => $user->id]);
        $this->postJson("/api/rewards/{$reward->id}/redeem");

        $response = $this->getJson('/api/reward-redemptions/me');

        $response->assertOk();
        collect($response->json('data'))->each(
            fn($redemption) => $this->assertEquals($user->id, $redemption['user_id'])
        );
    }
}
