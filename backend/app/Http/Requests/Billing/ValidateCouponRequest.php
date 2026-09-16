<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidateCouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Please enter a coupon code.',
            'code.max' => 'Coupon code cannot exceed 50 characters.',
        ];
    }
}