<?php

namespace Tests\Support;

use App\Models\Plan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the slice of the central schema that the auth/onboarding flow touches:
 * users, tenants (+ Stancl domains), tenant_user, the Spatie permission tables
 * (with the app's tenant-scoped team columns) and the billing + audit slices
 * written during registration.
 */
trait InteractsWithCentralSchema
{
    protected function createCentralSchema(): void
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
            $table->string('current_tenant_id')->nullable(); // string: tenant UUIDs
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->string('id')->primary(); // UUID (Stancl)
            $table->json('data')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('subdomain')->nullable();
            $table->string('domain')->nullable();
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->string('industry')->nullable();
            $table->string('timezone')->nullable()->default('UTC');
            $table->string('default_language')->nullable()->default('en');
            $table->string('support_email')->nullable();
            $table->string('support_phone')->nullable();
            $table->json('business_hours')->nullable();
            $table->json('settings')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('suspension_reason')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->string('provisioning_error')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('member');
            $table->json('permissions')->nullable();
            $table->string('department')->nullable();
            $table->string('position')->nullable();
            $table->string('availability_status')->nullable();
            $table->unsignedInteger('max_concurrent_chats')->nullable();
            $table->json('skills')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->unsignedBigInteger('invited_by')->nullable();
            $table->string('email');
            $table->string('role')->default('agent');
            $table->string('department')->nullable();
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->string('module')->nullable();
            $table->string('label')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('central')->create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->nullable();
            $table->index('tenant_id');
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
            $table->index(['model_id', 'model_type']);
            $table->index('tenant_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::connection('central')->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('tenant_id', 36)->nullable();
            $table->index(['model_id', 'model_type']);
            $table->index('tenant_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::connection('central')->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::connection('central')->create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_yearly', 10, 2)->default(0);
            $table->string('currency')->default('USD');
            $table->unsignedInteger('trial_days')->default(0);
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('feature_key');
            $table->string('feature_type')->default('boolean');
            $table->string('value')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        Schema::connection('central')->create('plan_provider_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('provider', 50);
            $table->string('billing_cycle', 20);
            $table->string('currency', 3)->default('USD');
            $table->string('provider_price_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('tenant_id');
            $table->unsignedBigInteger('plan_id');
            $table->string('provider', 50)->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->string('provider_price_id')->nullable();
            $table->string('status')->default('active');
            $table->string('billing_cycle')->default('monthly');
            $table->timestamp('trial_starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('last_billing_at')->nullable();
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('paypal_subscription_id')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('ai_used')->default(0);
            $table->unsignedBigInteger('ai_limit')->nullable();
            $table->unsignedBigInteger('agents_used')->default(0);
            $table->unsignedBigInteger('agents_limit')->nullable();
            $table->unsignedBigInteger('documents_used')->default(0);
            $table->unsignedBigInteger('documents_limit')->nullable();
            $table->unsignedBigInteger('storage_used')->default(0);
            $table->unsignedBigInteger('storage_limit')->nullable();
            $table->unsignedBigInteger('conversations_used')->default(0);
            $table->unsignedBigInteger('conversations_limit')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('central')->create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->string('type');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('stripe_item_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 30)->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->string('stripe_invoice_id')->nullable();
            $table->string('paypal_invoice_id')->nullable();
            $table->string('invoice_pdf_url')->nullable();
            $table->string('hosted_invoice_url')->nullable();
            $table->json('line_items')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('stripe_invoice_id');
        });

        Schema::connection('central')->create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('tenant_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('payment_id', 255)->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 30)->default('pending');
            $table->string('provider', 50);
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_invoice_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->string('payment_method', 50);
            $table->string('last_four', 4)->nullable();
            $table->string('card_brand')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_charge_id')->nullable();
            $table->string('paypal_transaction_id')->nullable();
            $table->decimal('refunded_amount', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->text('refund_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['provider', 'provider_payment_id']);
            $table->unique(['provider', 'provider_invoice_id']);
        });

        Schema::connection('central')->create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 100)->nullable();
            $table->string('status', 30)->default('received');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload');
            $table->json('normalized_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id']);
        });

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

        Schema::connection('central')->create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    protected function createDefaultPlan(array $attributes = []): Plan
    {
        return Plan::query()->create(array_merge([
            'name'         => 'Starter',
            'slug'         => 'starter'.uniqid(),
            'description'  => 'Starter plan',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency'     => 'USD',
            'trial_days'   => 14,
            'is_active'    => true,
            'is_default'   => true,
            'is_public'    => true,
            'features'     => [],
            'limits'       => [],
        ], $attributes));
    }

    /**
     * @return array<string, string>
     */
    protected function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name'   => 'Jane',
            'last_name'    => 'Doe',
            'username'     => 'jane_doe',
            'email'        => 'jane'.uniqid().'@example.test',
            'password'     => 'SecretPass!123',
            'password_confirmation' => 'SecretPass!123',
            'company_name' => 'Acme Corp',
            'timezone'     => 'UTC',
        ], $overrides);
    }
}