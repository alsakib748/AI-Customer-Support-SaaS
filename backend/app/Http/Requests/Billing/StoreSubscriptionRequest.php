<?php

namespace App\Http\Requests\Billing;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return auth()->check() && auth()->user()->hasPermissionTo('billing.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:central.plans,id'],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'gateway' => ['nullable', Rule::in(['stripe', 'paypal'])],
        ];
    }

    public function messages(): array
    {
        return [
            'plan_id.required' => 'Please select a plan.',
            'plan_id.exists' => 'Selected plan does not exist.',
            'billing_cycle.in' => 'Billing cycle must be monthly or yearly.',
            'gateway.in' => 'Payment gateway must be stripe or paypal.',
        ];
    }

    public function attributes(): array
    {
        return [
            'plan_id' => 'plan',
            'billing_cycle' => 'billing cycle',
            'coupon_code' => 'coupon code',
        ];
    }
}
