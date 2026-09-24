<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('tenant')?->id ?? $this->tenant;

        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'slug'           => ['sometimes', 'string', 'max:255', 'regex:/^[a-z0-9-_]+$/', Rule::unique('tenants', 'slug')->ignore($tenantId)],
            'subdomain'      => ['sometimes', 'string', 'max:63', 'regex:/^[a-z0-9-]+$/', Rule::unique('tenants', 'subdomain')->ignore($tenantId)],
            'domain'         => ['sometimes', 'string', 'max:255', Rule::unique('tenants', 'domain')->ignore($tenantId)],
            'industry'       => ['nullable', 'string', 'max:100'],
            'timezone'       => ['nullable', 'string', 'max:100'],
            'default_language' => ['nullable', 'string', 'max:10'],
            'support_email'  => ['nullable', 'email', 'max:255'],
            'support_phone'  => ['nullable', 'string', 'max:40'],
            'business_hours' => ['nullable', 'array'],
            'settings'       => ['nullable', 'array'],
        ];
    }
}