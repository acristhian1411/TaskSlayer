<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StopTaskExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ended_at' => ['sometimes', 'date'],
            'was_completed' => ['sometimes', 'boolean'],
        ];
    }
}
