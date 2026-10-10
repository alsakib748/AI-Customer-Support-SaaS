<?php

namespace App\Http\Requests\Admin\SystemSettings;

class SecuritySettingsRequest extends UpdateSystemSettingsRequest
{
    protected function settingsGroup(): string
    {
        return 'security';
    }
}
