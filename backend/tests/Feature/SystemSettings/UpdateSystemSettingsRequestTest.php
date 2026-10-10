<?php

namespace Tests\Feature\SystemSettings;

use App\Http\Requests\Admin\SystemSettings\GeneralSettingsRequest;
use Tests\TestCase;

class UpdateSystemSettingsRequestTest extends TestCase
{
    public function test_dotted_keys_are_returned_flat_by_validated(): void
    {
        $request = GeneralSettingsRequest::create('/api/v1/admin/settings/general', 'PUT', [
            'platform.name'           => 'Acme Support',
            'platform.default_locale' => 'en',
        ]);

        $this->assertSame([
            'platform.name'           => 'Acme Support',
            'platform.default_locale' => 'en',
        ], $request->validated());
    }

    public function test_absent_keys_are_omitted(): void
    {
        $request = GeneralSettingsRequest::create('/api/v1/admin/settings/general', 'PUT', [
            'platform.name' => 'Only Name',
        ]);

        $this->assertSame(['platform.name' => 'Only Name'], $request->validated());
    }

    public function test_array_values_are_preserved(): void
    {
        $request = \App\Http\Requests\Admin\SystemSettings\AiSettingsRequest::create('/api/v1/admin/settings/ai', 'PUT', [
            'ai.allowed_providers' => ['openai', 'anthropic'],
        ]);

        $this->assertSame([
            'ai.allowed_providers' => ['openai', 'anthropic'],
        ], $request->validated());
    }
}
