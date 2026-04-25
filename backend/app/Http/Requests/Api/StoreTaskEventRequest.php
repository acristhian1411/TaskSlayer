<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
            'execution_id' => ['nullable', 'integer', 'exists:task_executions,id'],
            'type' => ['required', 'string', Rule::in(['start', 'pause', 'resume', 'stop', 'complete'])],
            'timestamp' => ['required', 'date'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
