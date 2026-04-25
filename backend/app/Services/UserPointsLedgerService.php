<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPointsLedger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class UserPointsLedgerService
{
    public function listForUser(User $user, array $filters = []): Collection
    {
        $query = UserPointsLedger::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        return $query->get();
    }

    public function findForUser(User $user, int $entryId): UserPointsLedger
    {
        return UserPointsLedger::query()
            ->where('user_id', $user->id)
            ->findOrFail($entryId);
    }

    public function summaryForUser(User $user, array $filters = []): array
    {
        $query = UserPointsLedger::query()
            ->where('user_id', $user->id);

        if (!empty($filters['source_type'])) {
            $query->where('source_type', $filters['source_type']);
        }

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay());
        }

        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay());
        }

        $earnedPoints = (clone $query)
            ->where('points', '>', 0)
            ->sum('points');

        $spentPoints = (clone $query)
            ->where('points', '<', 0)
            ->sum('points');

        return [
            'earned_points' => (int) $earnedPoints,
            'spent_points' => (int) abs((int) $spentPoints),
            'balance' => (int) ($earnedPoints + $spentPoints),
            'entries_count' => (int) (clone $query)->count(),
        ];
    }
}
