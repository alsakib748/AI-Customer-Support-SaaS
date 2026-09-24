<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user');
        $userId = $userId instanceof \App\Models\User ? $userId->id : $userId;

        return [
            'first_name'   => ['sometimes', 'string', 'max:100'],
            'last_name'    => ['sometimes', 'string', 'max:100'],
            'username'     => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId)],
            'email'        => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone'        => ['sometimes', 'nullable', 'string', 'max:20'],
            'timezone'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'language'     => ['sometimes', 'nullable', 'string', 'max:10'],
        ];
    }
}