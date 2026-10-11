<?php

namespace App\Http\Requests\Admin\Notification;

use App\Notifications\Enums\NotificationCategory;
use App\Notifications\Enums\NotificationSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'category' => ['sometimes', 'nullable', 'string', Rule::in(NotificationCategory::values())],
            'severity' => ['sometimes', 'nullable', 'string', Rule::in(NotificationSeverity::values())],
        ];
    }
}
