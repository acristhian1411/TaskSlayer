<?php

namespace App\Services;

use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Models\UserPointsLedger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardService
{
    public function listAll(): Collection
    {
        return Reward::query()
            ->orderBy('cost_points')
            ->get();
    }

    public function create(array $data): Reward
    {
        return Reward::create([
            'name' => $data['name'],
            'cost_points' => $data['cost_points'],
            'reward_type' => $data['reward_type'] ?? 'time',
            'duration_minutes' => $data['duration_minutes'] ?? null,
        ]);
    }

    public function find(int $rewardId): Reward
    {
        return Reward::query()->findOrFail($rewardId);
    }

    public function update(int $rewardId, array $data): Reward
    {
        $reward = $this->find($rewardId);

        $reward->fill($data);
        $reward->save();

        return $reward->fresh();
    }

    public function delete(int $rewardId): void
    {
        $reward = $this->find($rewardId);
        $reward->delete();
    }

    public function listRedemptionsForUser(User $user): Collection
    {
        return RewardRedemption::query()
            ->where('user_id', $user->id)
            ->with('reward')
            ->orderByDesc('redeemed_at')
            ->get();
    }

    public function findRedemptionForUser(User $user, int $redemptionId): RewardRedemption
    {
        return RewardRedemption::query()
            ->where('user_id', $user->id)
            ->with('reward')
            ->findOrFail($redemptionId);
    }

    public function pointsBalanceForUser(User $user): int
    {
        return (int) UserPointsLedger::query()
            ->where('user_id', $user->id)
            ->sum('points');
    }

    public function redeemForUser(User $user, int $rewardId, ?string $redeemedAt = null): array
    {
        return DB::transaction(function () use ($user, $rewardId, $redeemedAt): array {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reward = $this->find($rewardId);
            $balance = $this->pointsBalanceForUser($lockedUser);

            if ($balance < $reward->cost_points) {
                throw ValidationException::withMessages([
                    'balance' => 'Puntos insuficientes para canjear esta recompensa.',
                ]);
            }

            $effectiveRedeemedAt = $redeemedAt ? Carbon::parse($redeemedAt) : Carbon::now();

            $redemption = RewardRedemption::create([
                'user_id' => $lockedUser->id,
                'reward_id' => $reward->id,
                'points_spent' => $reward->cost_points,
                'redeemed_at' => $effectiveRedeemedAt,
            ]);

            UserPointsLedger::create([
                'user_id' => $lockedUser->id,
                'points' => -$reward->cost_points,
                'source_type' => 'reward_redeemed',
                'source_id' => $redemption->id,
                'created_at' => $effectiveRedeemedAt,
            ]);

            return [
                'redemption' => $redemption->load('reward'),
                'points_balance' => $this->pointsBalanceForUser($lockedUser),
            ];
        });
    }
}
