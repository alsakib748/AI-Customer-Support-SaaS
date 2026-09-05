<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateConversactionRequest extends FormRequest
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
            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],
            'priority' => [
                'sometimes',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }
}
