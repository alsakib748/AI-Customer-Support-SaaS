<?php

namespace App\Http\Requests\Conversation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversactionRequest extends FormRequest
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
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],
            'subject' => [
                'nullable',
                'string',
                'max:255',
            ],
            'channel' => [
                'required',
                'string',
                Rule::in(['web', 'api']),
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

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Selected customer does not exist.',
            'channel.required' => 'Channel is required.',
            'channel.in' => 'Invalid channel selected.',
            'priority.in' => 'Invalid priority selected.',
        ];
    }

}
