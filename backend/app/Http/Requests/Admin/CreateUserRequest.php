<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name'   => ['required', 'string', 'max:100'],
            'last_name'    => ['required', 'string', 'max:100'],
            'username'     => ['nullable', 'string', 'max:100', 'unique:users,username'],
            'email'        => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'timezone'     => ['nullable', 'string', 'max:100'],
            'language'     => ['nullable', 'string', 'max:10'],
        ];
    }
}