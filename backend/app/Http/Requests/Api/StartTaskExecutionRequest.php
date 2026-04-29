<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StartTaskExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'started_at' => ['sometimes', 'date'],
            'checkpoint_id' => ['nullable', 'integer', 'exists:task_checkpoints,id'],
        ];
    }
}
