<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\StartTaskExecutionRequest;
use App\Http\Requests\Api\StopTaskExecutionRequest;
use App\Http\Requests\Api\StoreTaskExecutionRequest;
use App\Http\Requests\Api\TaskExecutionMetricsRequest;
use App\Http\Requests\Api\UpdateTaskExecutionRequest;
use App\Services\TaskExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TaskExecutionController extends ApiController
{
    public function __construct(private readonly TaskExecutionService $taskExecutionService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return $this->showAll($this->taskExecutionService->listForUser($request->user()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function summary(TaskExecutionMetricsRequest $request): JsonResponse
    {
        try {
            return $this->showOne($this->taskExecutionService->summaryForUser($request->user(), $request->validated()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function timeByTask(TaskExecutionMetricsRequest $request): JsonResponse
    {
        try {
            return $this->showOne($this->taskExecutionService->timeByTaskForUser($request->user(), $request->validated()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function dailyProductivity(TaskExecutionMetricsRequest $request): JsonResponse
    {
        try {
            return $this->showOne($this->taskExecutionService->dailyProductivityForUser($request->user(), $request->validated()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function store(StoreTaskExecutionRequest $request): JsonResponse
    {
        try {
            $execution = $this->taskExecutionService->createForUser($request->user(), $request->validated());

            return $this->showAfterAction($execution, 'create', 201);
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function show(Request $request, int $taskExecution): JsonResponse
    {
        try {
            return $this->showOne($this->taskExecutionService->findForUser($request->user(), $taskExecution));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function update(UpdateTaskExecutionRequest $request, int $taskExecution): JsonResponse
    {
        try {
            $execution = $this->taskExecutionService->updateForUser($request->user(), $taskExecution, $request->validated());

            return $this->showAfterAction($execution, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function destroy(Request $request, int $taskExecution): JsonResponse
    {
        try {
            $this->taskExecutionService->deleteForUser($request->user(), $taskExecution);

            return $this->showMessage('Registro eliminado con exito.');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function start(StartTaskExecutionRequest $request, int $task): JsonResponse
    {
        try {
            $execution = $this->taskExecutionService->startForUser(
                $request->user(),
                $task,
                $request->validated()['started_at'] ?? null
            );

            return $this->showAfterAction($execution, 'create', 201);
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function pause(Request $request, int $taskExecution): JsonResponse
    {
        try {
            $execution = $this->taskExecutionService->pauseForUser($request->user(), $taskExecution);

            return $this->showAfterAction($execution, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function resume(Request $request, int $taskExecution): JsonResponse
    {
        try {
            $execution = $this->taskExecutionService->resumeForUser($request->user(), $taskExecution);

            return $this->showAfterAction($execution, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function stop(StopTaskExecutionRequest $request, int $taskExecution): JsonResponse
    {
        try {
            $execution = $this->taskExecutionService->stopForUser(
                $request->user(),
                $taskExecution,
                $request->validated()['ended_at'] ?? null,
                (bool) ($request->validated()['was_completed'] ?? false)
            );

            return $this->showAfterAction($execution, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function complete(StopTaskExecutionRequest $request, int $taskExecution): JsonResponse
    {
        try {
            $execution = $this->taskExecutionService->completeForUser(
                $request->user(),
                $taskExecution,
                $request->validated()['ended_at'] ?? null
            );

            return $this->showAfterAction($execution, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }
}
