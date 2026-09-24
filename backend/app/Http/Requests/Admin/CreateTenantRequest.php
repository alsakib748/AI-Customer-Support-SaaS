<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-_]+$/', 'unique:tenants,slug'],
            'subdomain'   => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,subdomain'],
            'domain'      => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'industry'    => ['nullable', 'string', 'max:100'],
            'timezone'    => ['nullable', 'string', 'max:100'],
            'default_language' => ['nullable', 'string', 'max:10'],
            'support_email'    => ['nullable', 'email', 'max:255'],
            'support_phone'    => ['nullable', 'string', 'max:40'],
            'business_hours'   => ['nullable', 'array'],
            'settings'         => ['nullable', 'array'],
            'plan_id'          => ['nullable', 'integer', 'exists:plans,id'],
            'owner'            => ['required', 'array'],
            'owner.email'      => ['required', 'email', 'max:255'],
            'owner.name'       => ['nullable', 'string', 'max:255'],
            'owner.password'   => ['nullable', 'string', 'min:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner.email.required' => 'The tenant owner email is required.',
            'owner.email.email'    => 'The tenant owner email must be a valid email address.',
            'confirmation.in'      => 'The confirmation must be "ARCHIVE".',
        ];
    }
}