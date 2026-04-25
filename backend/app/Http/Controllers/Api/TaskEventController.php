<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\StoreTaskEventRequest;
use App\Http\Requests\Api\TaskEventIndexRequest;
use App\Services\TaskEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TaskEventController extends ApiController
{
    public function __construct(private readonly TaskEventService $taskEventService)
    {
    }

    public function index(TaskEventIndexRequest $request): JsonResponse
    {
        try {
            return $this->showAll($this->taskEventService->listForUser($request->user(), $request->validated()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function store(StoreTaskEventRequest $request): JsonResponse
    {
        try {
            $event = $this->taskEventService->createForUser($request->user(), $request->validated());

            return $this->showAfterAction($event, 'create', 201);
        } catch (Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function show(Request $request, int $taskEvent): JsonResponse
    {
        try {
            return $this->showOne($this->taskEventService->findForUser($request->user(), $taskEvent));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function destroy(Request $request, int $taskEvent): JsonResponse
    {
        try {
            $this->taskEventService->deleteForUser($request->user(), $taskEvent);

            return $this->showMessage('Registro eliminado con exito.');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function executionTimeline(Request $request, int $taskExecution): JsonResponse
    {
        try {
            return $this->showAll($this->taskEventService->timelineForExecution($request->user(), $taskExecution));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function executionStats(Request $request, int $taskExecution): JsonResponse
    {
        try {
            return $this->showOne($this->taskEventService->statsForExecution($request->user(), $taskExecution));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }
}
