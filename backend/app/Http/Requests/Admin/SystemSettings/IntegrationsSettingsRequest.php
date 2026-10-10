<?php

namespace App\Http\Requests\Admin\SystemSettings;

class IntegrationsSettingsRequest extends UpdateSystemSettingsRequest
{
    protected function settingsGroup(): string
    {
        return 'integrations';
    }
}
