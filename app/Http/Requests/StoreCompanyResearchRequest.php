<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyResearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company' => ['nullable', 'string', 'max:255'],
            'model' => ['required', 'in:gpt-5-nano,gpt-5-mini'],
            'focus' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
