<?php

namespace App\Http\Requests\Admin\SystemSettings;

use Illuminate\Foundation\Http\FormRequest;

class TestEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient' => ['nullable', 'email', 'max:255'],
        ];
    }
}
