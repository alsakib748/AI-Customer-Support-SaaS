<?php

namespace Tests\Support\Notifications;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the slice of the central schema the platform notification module
 * touches: users, the Spatie permission tables (with the app's tenant-scoped
 * team columns) and Laravel's notifications table.
 */
trait InteractsWithPlatformNotifications
{
    protected function createCentralNotificationSchema(): void
    {
        Schema::connection('central')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('avatar')->nullable();
            $table->json('preferences')->nullable();
            $table->string('timezone')->nullable();
            $table->string('language')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->unsignedBigInteger('current_tenant_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('group_name')->nullable();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('central')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable();
            $table->index('tenant_id', 'roles_team_foreign_key_index');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        Schema::connection('central')->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('tenant_id', 36)->nullable();
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->index('tenant_id', 'model_has_roles_team_foreign_key_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::connection('central')->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('tenant_id', 36)->nullable();
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->index('tenant_id', 'model_has_permissions_team_foreign_key_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::connection('central')->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        Schema::connection('central')->create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    protected function createPlatformIncidentSchema(): void
    {
        Schema::connection('central')->create('platform_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 120)->index();
            $table->string('title', 160);
            $table->text('summary')->nullable();
            $table->string('severity', 20);
            $table->string('status', 20);
            $table->string('source', 60);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function createAuditLogSchema(): void
    {
        Schema::connection('central')->create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function ensureSuperAdminRole(): Role
    {
        return Role::query()->firstOrCreate([
            'name'       => 'super_admin',
            'guard_name' => 'api',
            'tenant_id'  => null,
        ]);
    }

    protected function makeUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'first_name' => 'Test',
            'last_name'  => 'User',
            'email'      => 'user'.uniqid().'@example.test',
            'password'   => 'password',
            'is_active'  => true,
        ], $attributes));
    }

    protected function makeSuperAdmin(array $attributes = []): User
    {
        $user = $this->makeUser($attributes);
        $user->assignRole($this->ensureSuperAdminRole());

        return $user->fresh();
    }
}
