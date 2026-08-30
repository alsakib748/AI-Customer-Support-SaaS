<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['required', 'string', 'timezone'],
            'default_language' => ['required', 'string', 'max:10'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Workspace name is required.',
            'name.max' => 'Workspace name cannot exceed 255 characters.',
            'support_email.email' => 'Please enter a valid email address.',
            'timezone.required' => 'Timezone is required.',
            'timezone.timezone' => 'Please select a valid timezone.',
            'default_language.required' => 'Default language is required.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'workspace name',
            'industry' => 'industry',
            'support_email' => 'support email',
            'support_phone' => 'support phone',
            'timezone' => 'timezone',
            'default_language' => 'default language',
        ];
    }

}