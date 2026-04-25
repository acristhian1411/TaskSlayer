<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'cost_points' => ['sometimes', 'integer', 'min:1'],
            'reward_type' => ['sometimes', 'string', Rule::in(['time', 'custom'])],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
