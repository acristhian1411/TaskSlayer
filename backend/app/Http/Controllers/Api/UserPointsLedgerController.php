<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\PointsLedgerIndexRequest;
use App\Services\UserPointsLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UserPointsLedgerController extends ApiController
{
    public function __construct(private readonly UserPointsLedgerService $userPointsLedgerService)
    {
    }

    public function index(PointsLedgerIndexRequest $request): JsonResponse
    {
        try {
            $entries = $this->userPointsLedgerService->listForUser($request->user(), $request->validated());

            return $this->showAll($entries);
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function show(Request $request, int $entry): JsonResponse
    {
        try {
            return $this->showOne($this->userPointsLedgerService->findForUser($request->user(), $entry));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function summary(PointsLedgerIndexRequest $request): JsonResponse
    {
        try {
            return $this->showOne($this->userPointsLedgerService->summaryForUser($request->user(), $request->validated()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }
}
