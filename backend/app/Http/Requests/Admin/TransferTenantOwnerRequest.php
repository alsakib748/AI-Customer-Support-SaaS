<?php

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferTenantOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        $currentUserId = $tenant?->tenantUsers()
            ->where('role', 'owner')
            ->value('user_id');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::in(
                    $tenant?->members()->pluck('user_id')->all() ?? []
                ),
                Rule::notIn($currentUserId ? [$currentUserId] : []),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists' => 'The given member does not exist.',
            'user_id.in'     => 'The user must already be a member of this tenant.',
            'user_id.not_in' => 'This user is already the owner.',
        ];
    }
}