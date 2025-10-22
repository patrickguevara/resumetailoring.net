<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResumeEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'job_input_type' => ['required', 'in:url,text'],
            'job_url' => ['nullable', 'required_if:job_input_type,url', 'url', 'max:2048'],
            'job_text' => ['nullable', 'required_if:job_input_type,text', 'string', 'min:10', 'max:20000'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
