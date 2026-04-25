<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaskEventIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task_id' => ['sometimes', 'integer', 'exists:tasks,id'],
            'execution_id' => ['sometimes', 'integer', 'exists:task_executions,id'],
            'type' => ['sometimes', 'string', Rule::in(['start', 'pause', 'resume', 'stop', 'complete'])],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
