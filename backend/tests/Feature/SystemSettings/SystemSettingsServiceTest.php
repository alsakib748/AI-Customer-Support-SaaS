<?php

namespace Tests\Feature\SystemSettings;

use App\Services\SystemSettings\SystemSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemSettingsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('central')->create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('group', 50);
            $table->string('type', 30)->default('string');
            $table->boolean('is_public')->default(false);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index('group');
        });
    }

    protected function service(): SystemSettingsService
    {
        return $this->app->make(SystemSettingsService::class);
    }

    public function test_defaults_are_resolved_before_any_row_is_saved(): void
    {
        $service = $this->service();

        $this->assertSame('Support AI', $service->get('platform.name'));
        $this->assertTrue($service->get('registration.enabled'));
        $this->assertFalse($service->isMaintenanceEnabled());
    }

    public function test_update_group_persists_and_casts_values(): void
    {
        $service = $this->service();

        $payload = $service->updateGroup('security', [
            'security.password_min_length' => '12',
            'registration.enabled'         => false,
        ]);

        $this->assertSame(12, $payload['values']['security.password_min_length']);
        $this->assertFalse($payload['values']['registration.enabled']);

        $row = DB::connection('central')->table('system_settings')
            ->where('key', 'security.password_min_length')->first();

        $this->assertNotNull($row);
        $this->assertSame('security', $row->group);
        $this->assertEquals(12, json_decode($row->value, true));
    }

    public function test_sensitive_values_are_encrypted_and_never_returned(): void
    {
        $service = $this->service();

        $payload = $service->updateGroup('integrations', [
            'integrations.webhook_signing_secret' => 'super-secret',
        ]);

        $this->assertTrue($payload['secrets']['integrations.webhook_signing_secret']);
        $this->assertNull($payload['values']['integrations.webhook_signing_secret']);

        $row = DB::connection('central')->table('system_settings')
            ->where('key', 'integrations.webhook_signing_secret')->first();

        $stored = json_decode($row->value, true);

        $this->assertIsArray($stored);
        $this->assertArrayHasKey('__encrypted', $stored);
        $this->assertStringNotContainsString('super-secret', $row->value);

        // Internally the service can still read the decrypted value.
        $this->assertSame('super-secret', $service->get('integrations.webhook_signing_secret'));
    }

    public function test_cache_is_invalidated_after_update(): void
    {
        $service = $this->service();

        $this->assertSame('Support AI', $service->get('platform.name')); // prime cache

        $service->updateGroup('general', ['platform.name' => 'Acme Support']);

        $this->assertSame('Acme Support', $service->get('platform.name'));
    }

    public function test_keys_outside_the_allow_list_are_ignored(): void
    {
        $service = $this->service();

        $service->updateGroup('general', [
            'platform.name' => 'Kept',
            'evil.key'      => 'ignored',
        ]);

        $this->assertSame(0, DB::connection('central')->table('system_settings')
            ->where('key', 'evil.key')->count());
        $this->assertSame('Kept', $service->get('platform.name'));
    }
}
