<?php

namespace App\Http\Requests\Admin\SystemSettings;

class OperationsSettingsRequest extends UpdateSystemSettingsRequest
{
    protected function settingsGroup(): string
    {
        return 'operations';
    }
}
