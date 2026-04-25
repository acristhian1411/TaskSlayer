<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'cost_points' => ['required', 'integer', 'min:1'],
            'reward_type' => ['sometimes', 'string', Rule::in(['time', 'custom'])],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
