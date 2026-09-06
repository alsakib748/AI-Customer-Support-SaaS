<?php

namespace App\Http\Requests\Message;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
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
            'content' => [
                'required',
                'string',
                'max:10000',
            ],
            'message_type' => [
                'sometimes',
                Rule::in(['text', 'internal_note']),
            ],
            'is_internal' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Message content is required.',
            'content.max' => 'Message cannot exceed 10,000 characters.',
            'message_type.in' => 'Invalid message type.',
        ];
    }
}
