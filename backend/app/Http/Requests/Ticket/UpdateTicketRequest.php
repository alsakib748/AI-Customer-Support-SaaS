<?php

namespace App\Http\Requests\Ticket;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
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
                'sometimes',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'priority' => [
                'sometimes',
                Rule::in(['low', 'normal', 'high', 'urgent']),
            ],
            'type' => [
                'sometimes',
                Rule::in(['general', 'technical', 'billing', 'account', 'bug', 'feature_request', 'other']),
            ],
            'due_at' => [
                'nullable',
                'date',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'priority.in' => 'Invalid priority value.',
            'type.in' => 'Invalid type value.',
        ];
    }

}
