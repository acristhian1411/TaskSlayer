<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\StoreTaskRequest;
use App\Http\Requests\Api\UpdateTaskRequest;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TaskController extends ApiController
{
    public function __construct(private readonly TaskService $taskService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return $this->showAll($this->taskService->listForUser($request->user()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $task = $this->taskService->createForUser($request->user(), $request->validated());

            return $this->showAfterAction($task, 'create', 201);
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function show(Request $request, int $task): JsonResponse
    {
        try {
            return $this->showOne($this->taskService->findForUser($request->user(), $task));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function update(UpdateTaskRequest $request, int $task): JsonResponse
    {
        try {
            $updatedTask = $this->taskService->updateForUser($request->user(), $task, $request->validated());

            return $this->showAfterAction($updatedTask, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function destroy(Request $request, int $task): JsonResponse
    {
        try {
            $this->taskService->deleteForUser($request->user(), $task);

            return $this->showMessage('Registro eliminado con exito.');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function complete(Request $request, int $task): JsonResponse
    {
        try {
            $completedTask = $this->taskService->completeForUser($request->user(), $task);

            return $this->showAfterAction($completedTask, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function uncomplete(Request $request, int $task): JsonResponse
    {
        try {
            $task = $this->taskService->uncompleteForUser($request->user(), $task);

            return $this->showAfterAction($task, 'update');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }
}
