<?php

namespace App\Http\Requests\Admin\SystemSettings;

class AiSettingsRequest extends UpdateSystemSettingsRequest
{
    protected function settingsGroup(): string
    {
        return 'ai';
    }
}
