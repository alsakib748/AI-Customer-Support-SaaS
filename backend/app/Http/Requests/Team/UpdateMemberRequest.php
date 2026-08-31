<?php

namespace App\Http\Requests\Team;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
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
            'department' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'availability_status' => [
                'required',
                Rule::in(['online', 'offline', 'away', 'busy'])
            ],
            'max_concurrent_chats' => [
                'required',
                'integer',
                'min:1',
                'max:50'
            ],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'availability_status.required' => 'Availability status is required.',
            'availability_status.in' => 'Invalid availability status.',
            'max_concurrent_chats.required' => 'Maximum concurrent chats is required.',
            'max_concurrent_chats.min' => 'Maximum concurrent chats must be at least 1.',
            'max_concurrent_chats.max' => 'Maximum concurrent chats cannot exceed 50.',
            'skills.*.max' => 'Skill names cannot exceed 50 characters.',
        ];
    }

    public function attributes(): array
    {
        return [
            'department' => 'department',
            'position' => 'position',
            'availability_status' => 'availability status',
            'max_concurrent_chats' => 'maximum concurrent chats',
            'skills' => 'skills',
        ];
    }

}