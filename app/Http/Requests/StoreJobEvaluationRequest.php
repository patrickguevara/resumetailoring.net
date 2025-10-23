<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resume_id' => ['required', 'integer', 'exists:resumes,id'],
            'model' => ['required', 'in:gpt-5-nano,gpt-5-mini'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'job_url_override' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
