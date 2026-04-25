<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_original' => ['required', 'string', 'max:255'],
            'title_rpg' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'difficulty_level' => ['required', 'integer', 'min:1', 'max:4'],
            'reward_points' => ['required', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'archived'])],
        ];
    }
}
