<?php

namespace App\Http\Requests\Admin\SystemSettings;

class GeneralSettingsRequest extends UpdateSystemSettingsRequest
{
    protected function settingsGroup(): string
    {
        return 'general';
    }
}
