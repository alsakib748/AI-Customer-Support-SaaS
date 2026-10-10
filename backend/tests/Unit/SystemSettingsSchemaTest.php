<?php

namespace Tests\Unit;

use App\Services\SystemSettings\SystemSettingsSchema;
use Tests\TestCase;

class SystemSettingsSchemaTest extends TestCase
{
    protected function schema(): SystemSettingsSchema
    {
        return $this->app->make(SystemSettingsSchema::class);
    }

    public function test_every_group_rule_is_guarded_with_sometimes(): void
    {
        $rules = $this->schema()->rulesForGroup('security');

        $this->assertNotEmpty($rules);

        foreach ($rules as $key => $fieldRules) {
            $this->assertSame('sometimes', $fieldRules[0], "Field {$key} must start with 'sometimes'.");
        }
    }

    public function test_casts_values_to_declared_types(): void
    {
        $schema = $this->schema();

        $this->assertTrue($schema->cast('registration.enabled', 'true'));
        $this->assertSame(12, $schema->cast('security.password_min_length', '12'));
        $this->assertSame(0.5, $schema->cast('ai.default_temperature', '0.5'));
        $this->assertSame(['openai', 'anthropic'], $schema->cast('ai.allowed_providers', '["openai","anthropic"]'));
    }

    public function test_sensitive_and_public_flags_are_exposed_in_field_meta(): void
    {
        $schema = $this->schema();

        $secret = $schema->fieldMeta('integrations.webhook_signing_secret');
        $this->assertTrue($secret['sensitive']);
        $this->assertArrayNotHasKey('value', $secret);

        $name = $schema->fieldMeta('platform.name');
        $this->assertTrue($name['public']);
        $this->assertFalse($name['sensitive']);
    }

    public function test_group_meta_lists_all_fields(): void
    {
        $meta = $this->schema()->groupMeta('general');

        $this->assertSame('general', $meta['key']);
        $this->assertNotEmpty($meta['fields']);
        $this->assertSame('platform.name', $meta['fields'][0]['key']);
    }
}
