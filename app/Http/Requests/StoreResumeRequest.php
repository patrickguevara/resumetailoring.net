<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreResumeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input_type' => ['nullable', 'string', Rule::in(['markdown', 'pdf'])],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'content_markdown' => [
                Rule::requiredIf(fn () => $this->inputType() === 'markdown'),
                'nullable',
                'string',
            ],
            'resume_file' => [
                Rule::requiredIf(fn () => $this->inputType() === 'pdf'),
                'nullable',
                'file',
                'mimes:pdf',
                'max:5120',
            ],
        ];
    }

    public function inputType(): string
    {
        $value = strtolower((string) $this->input('input_type', 'markdown'));

        return in_array($value, ['markdown', 'pdf'], true) ? $value : 'markdown';
    }
}
