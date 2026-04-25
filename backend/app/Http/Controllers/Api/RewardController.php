<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\RedeemRewardRequest;
use App\Http\Requests\Api\StoreRewardRequest;
use App\Http\Requests\Api\UpdateRewardRequest;
use App\Services\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RewardController extends ApiController
{
    public function __construct(private readonly RewardService $rewardService)
    {
    }

    public function index(): JsonResponse
    {
        try {
            return $this->showAll($this->rewardService->listAll());
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function store(StoreRewardRequest $request): JsonResponse
    {
        try {
            $reward = $this->rewardService->create($request->validated());

            return $this->showAfterAction($reward, 'create', 201);
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function show(int $reward): JsonResponse
    {
        try {
            return $this->showOne($this->rewardService->find($reward));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function update(UpdateRewardRequest $request, int $reward): JsonResponse
    {
        try {
            $updatedReward = $this->rewardService->update($reward, $request->validated());

            return $this->showAfterAction($updatedReward, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function destroy(int $reward): JsonResponse
    {
        try {
            $this->rewardService->delete($reward);

            return $this->showMessage('Registro eliminado con exito.');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function redeem(RedeemRewardRequest $request, int $reward): JsonResponse
    {
        try {
            $result = $this->rewardService->redeemForUser(
                $request->user(),
                $reward,
                $request->validated()['redeemed_at'] ?? null
            );

            return $this->showAfterAction($result, 'create', 201);
        } catch (Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function pointsBalance(Request $request): JsonResponse
    {
        try {
            return $this->showOne([
                'points_balance' => $this->rewardService->pointsBalanceForUser($request->user()),
            ]);
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function myRedemptions(Request $request): JsonResponse
    {
        try {
            return $this->showAll($this->rewardService->listRedemptionsForUser($request->user()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function showMyRedemption(Request $request, int $rewardRedemption): JsonResponse
    {
        try {
            return $this->showOne($this->rewardService->findRedemptionForUser($request->user(), $rewardRedemption));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }
}
