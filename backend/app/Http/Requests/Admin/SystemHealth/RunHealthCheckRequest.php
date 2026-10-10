<?php

namespace App\Http\Requests\Admin\SystemHealth;

use App\Health\Services\HealthCheckRegistry;
use Illuminate\Foundation\Http\FormRequest;

class RunHealthCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        $key = (string) $this->route('check');

        return app(HealthCheckRegistry::class)->has($key);
    }

    public function rules(): array
    {
        return [
            'check' => ['sometimes', 'string', 'max:80'],
        ];
    }
}