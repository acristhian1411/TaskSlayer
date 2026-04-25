<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends ApiController
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $payload = $this->authService->register($request->validated());

            return $this->showAfterAction($payload, 'create', 201);
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $payload = $this->authService->login($request->validated());

            return $this->showAfterAction($payload, 'show');
        } catch (Throwable $e) {
            return $this->respondException($e, 422);
        }
    }

    public function me(Request $request): JsonResponse
    {
        try {
            return $this->showOne($this->authService->me($request->user()));
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $this->authService->logout($request->user());

            return $this->showMessage('Sesion cerrada con exito.');
        } catch (Throwable $e) {
            return $this->respondException($e);
        }
    }
}
