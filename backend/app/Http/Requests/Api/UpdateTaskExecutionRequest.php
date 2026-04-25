<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => ['sometimes', 'date'],
            'ended_at' => ['nullable', 'date'],
            'duration_seconds' => ['sometimes', 'integer', 'min:0'],
            'was_completed' => ['sometimes', 'boolean'],
        ];
    }
}
