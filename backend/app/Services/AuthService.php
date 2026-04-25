<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        return $this->buildAuthPayload($user, 'auth_token');
    }

    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas no son validas.'],
            ]);
        }

        $user->tokens()->delete();

        return $this->buildAuthPayload($user, 'auth_token');
    }

    public function me(User $user): User
    {
        return $user->loadMissing('tasks');
    }

    public function logout(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
            return;
        }

        $user->tokens()->delete();
    }

    private function buildAuthPayload(User $user, string $tokenName): array
    {
        $token = $user->createToken($tokenName)->plainTextToken;

        return [
            'user' => Arr::only($user->fresh()->toArray(), ['id', 'name', 'email', 'created_at', 'updated_at']),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
