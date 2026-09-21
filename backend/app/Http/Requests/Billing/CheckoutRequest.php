<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id'       => ['required', 'integer', 'exists:central.plans,id'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'provider'      => ['nullable', Rule::in(['stripe', 'paypal'])],
            'coupon_code'   => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a plan.',
            'plan_id.exists'   => 'Selected plan does not exist.',
            'provider.in'      => 'Invalid payment provider.',
        ];
    }
}
