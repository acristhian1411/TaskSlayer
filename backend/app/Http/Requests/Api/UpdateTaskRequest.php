<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_original' => ['sometimes', 'string', 'max:255'],
            'title_rpg' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'difficulty_level' => ['sometimes', 'integer', 'min:1', 'max:4'],
            'reward_points' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'archived'])],
        ];
    }
}
