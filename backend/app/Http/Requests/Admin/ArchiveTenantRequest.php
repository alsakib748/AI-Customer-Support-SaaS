<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArchiveTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', Rule::in(['ARCHIVE'])],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.required' => 'Type "ARCHIVE" to confirm tenant archiving.',
            'confirmation.in'       => 'The confirmation must be exactly "ARCHIVE".',
        ];
    }
}