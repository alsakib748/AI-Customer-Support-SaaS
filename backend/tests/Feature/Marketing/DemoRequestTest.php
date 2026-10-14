<?php

namespace Tests\Feature\Marketing;

use App\Http\Middleware\JWTAuthMiddleware;
use App\Http\Middleware\TenantAware;
use App\Mail\DemoRequestNotification;
use App\Mail\DemoRequestReceived;
use App\Models\DemoRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\InteractsWithAuth;
use Tests\TestCase;

/**
 * Covers the public "Book a demo" capture flow plus the Super Admin review
 * endpoints that back the marketing site.
 */
class DemoRequestTest extends TestCase
{
    use InteractsWithAuth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootAuthEnvironment();

        Schema::connection('central')->create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('company')->nullable();
            $table->string('team_size')->nullable();
            $table->text('interests')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending')->index();
            $table->dateTime('scheduled_at')->nullable();
            $table->string('meeting_link')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('handled_by')->nullable();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function makeSuperAdmin(): User
    {
        setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = User::query()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'super'.uniqid().'@example.test',
            'password' => 'SecretPass!123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $admin->assignRole(Role::findByName('super_admin', 'api'));

        return $admin->fresh();
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Maria Johnson',
            'email' => 'maria@acme.test',
            'company' => 'Acme Corp',
            'team_size' => '11-50',
            'interests' => 'AI agents, knowledge base',
            'message' => 'We would like a walkthrough next week.',
        ], $overrides);
    }

    public function test_demo_requests_are_pinned_to_the_central_connection(): void
    {
        // Regression: the admin route runs behind TenantAware, which switches the
        // default connection to the tenant DB. demo_requests lives in the central
        // database, so the model must never follow the tenant connection.
        $this->assertSame('central', (new DemoRequest)->getConnectionName());
    }

    public function test_a_visitor_can_submit_a_demo_request(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/demo-requests', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('data.email', 'maria@acme.test')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('message', 'Demo request submitted successfully. We will contact you soon.');

        $this->assertDatabaseHas('demo_requests', [
            'email' => 'maria@acme.test',
            'company' => 'Acme Corp',
            'status' => 'pending',
        ], 'central');

        Mail::assertSent(DemoRequestReceived::class, function (DemoRequestReceived $mail) {
            return $mail->demoRequest->email === 'maria@acme.test';
        });

        Mail::assertSent(DemoRequestNotification::class, function (DemoRequestNotification $mail) {
            return $mail->demoRequest->email === 'maria@acme.test';
        });
    }

    public function test_a_demo_request_requires_a_name_and_a_valid_email(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/demo-requests', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['full_name', 'email']]);

        $this->assertSame(0, DemoRequest::query()->count());
        Mail::assertNothingSent();
    }

    public function test_optional_fields_may_be_omitted(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/demo-requests', [
            'full_name' => 'Solo Founder',
            'email' => 'solo@startup.test',
        ])->assertStatus(201);

        $this->assertDatabaseHas('demo_requests', [
            'email' => 'solo@startup.test',
            'company' => null,
            'message' => null,
        ], 'central');
    }

    public function test_a_demo_request_survives_a_mail_transport_failure(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('smtp down'));

        $this->postJson('/api/v1/demo-requests', $this->payload(['email' => 'resilient@acme.test']))
            ->assertStatus(201);

        $this->assertDatabaseHas('demo_requests', ['email' => 'resilient@acme.test'], 'central');
    }

    public function test_super_admin_can_list_demo_requests(): void
    {
        DemoRequest::query()->create([
            'full_name' => 'First Lead',
            'email' => 'first@acme.test',
            'status' => 'pending',
        ]);

        $admin = $this->makeSuperAdmin();

        $this->withoutMiddleware([JWTAuthMiddleware::class, TenantAware::class]);
        $this->actingAs($admin, 'api');

        $this->getJson('/api/v1/admin/demo-requests')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'first@acme.test');
    }

    public function test_super_admin_can_update_a_demo_request(): void
    {
        $demoRequest = DemoRequest::query()->create([
            'full_name' => 'Second Lead',
            'email' => 'second@acme.test',
            'status' => 'pending',
        ]);

        $admin = $this->makeSuperAdmin();

        $this->withoutMiddleware([JWTAuthMiddleware::class, TenantAware::class]);
        $this->actingAs($admin, 'api');

        $this->putJson("/api/v1/admin/demo-requests/{$demoRequest->id}", [
            'status' => 'scheduled',
            'scheduled_at' => '2026-11-01 10:00:00',
            'meeting_link' => 'https://meet.example.test/abc',
            'notes' => 'Confirmed with sales.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.handled_by', $admin->id);

        $fresh = $demoRequest->fresh();
        $this->assertSame('scheduled', $fresh->status);
        $this->assertSame($admin->id, $fresh->handled_by);
        $this->assertNotNull($fresh->handled_at);
    }

    public function test_updating_with_an_unknown_status_is_rejected(): void
    {
        $demoRequest = DemoRequest::query()->create([
            'full_name' => 'Third Lead',
            'email' => 'third@acme.test',
            'status' => 'pending',
        ]);

        $admin = $this->makeSuperAdmin();

        $this->withoutMiddleware([JWTAuthMiddleware::class, TenantAware::class]);
        $this->actingAs($admin, 'api');

        $this->putJson("/api/v1/admin/demo-requests/{$demoRequest->id}", [
            'status' => 'banana',
        ])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->assertSame('pending', $demoRequest->fresh()->status);
    }

    public function test_non_super_admin_cannot_reach_admin_demo_requests(): void
    {
        DemoRequest::query()->create([
            'full_name' => 'Hidden Lead',
            'email' => 'hidden@acme.test',
            'status' => 'pending',
        ]);

        [$actor] = $this->makeTenantOwner();

        $this->withoutMiddleware([JWTAuthMiddleware::class, TenantAware::class]);
        $this->actingAs($actor, 'api');

        $this->getJson('/api/v1/admin/demo-requests')->assertStatus(403);
    }
}
