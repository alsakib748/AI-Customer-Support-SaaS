<?php

namespace App\Http\Requests\Admin\SystemSettings;

class EmailSettingsRequest extends UpdateSystemSettingsRequest
{
    protected function settingsGroup(): string
    {
        return 'email';
    }
}
