<?php

namespace App\Http\Requests\ChatWidget;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChatWidgetRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'disabled'])],
            'position' => ['sometimes', Rule::in(['bottom-right', 'bottom-left', 'top-right', 'top-left'])],
            'header_title' => ['nullable', 'string', 'max:100'],
            'welcome_message' => ['nullable', 'string', 'max:500'],
            'offline_message' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'show_branding' => ['nullable', 'boolean'],
            'require_name' => ['nullable', 'boolean'],
            'require_email' => ['nullable', 'boolean'],
            'require_phone' => ['nullable', 'boolean'],
            'allowed_origins' => ['nullable', 'array'],
            'allowed_origins.*' => ['url'],
            'settings' => ['nullable', 'array'],
        ];
    }
}
