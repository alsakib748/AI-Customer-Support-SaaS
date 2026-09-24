<?php

namespace App\Services\Tenant;

use App\Events\Tenant\TenantProvisioningCompleted;
use App\Events\Tenant\TenantProvisioningFailed;
use App\Events\Tenant\TenantProvisioningStarted;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Billing\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Facades\Tenancy;

class TenantProvisioningService
{
    public function __construct(
        protected SubscriptionService $subscriptions,
        protected AuditLogService $auditLog,
    ) {
    }

    /**
     * Provision a newly created tenant. Idempotent: safe to run for the
     * same tenant more than once (e.g. retry after a failure).
     */
    public function provision(Tenant $tenant, array $data): Tenant
    {
        if ($tenant->provisioned_at && $tenant->ownerMembership()->exists() && $tenant->subscriptions()->exists()) {
            // Already fully provisioned — just make sure the status is sane.
            if (! $tenant->canLogin()) {
                $tenant->update(['status' => Tenant::STATUS_TRIAL]);
            }
            return $tenant->fresh();
        }

        TenantProvisioningStarted::dispatch($tenant);

        $tenant->update([
            'status'            => Tenant::STATUS_PROVISIONING,
            'provisioning_error' => null,
        ]);

        try {
            // 1. Resolve / create the owner user.
            $owner = $this->resolveOwnerUser($data['owner'] ?? []);

            // 2. Attach the owner membership (idempotent).
            $this->attachOwnerMembership($tenant, $owner);

            // 3. Initialize tenant defaults (AI configuration, etc.).
            $this->initializeDefaults($tenant);

            // 4. Create the trial subscription.
            $plan = $this->resolvePlan($data['plan_id'] ?? null);
            $this->createTrialSubscription($tenant, $plan);

            // 5. Mark provisioned.
            $tenant->update([
                'status'         => $plan && $plan->trial_days > 0 ? Tenant::STATUS_TRIAL : Tenant::STATUS_ACTIVE,
                'provisioned_at' => now(),
                'provisioning_error' => null,
            ]);

            $this->auditLog->log(
                'tenant.created',
                'tenant',
                $tenant->id,
                null,
                [
                    'name'  => $tenant->name,
                    'slug'  => $tenant->slug,
                    'plan'  => $plan?->slug,
                    'owner' => $owner->email,
                ]
            );

            TenantProvisioningCompleted::dispatch($tenant);

            return $tenant->fresh();
        } catch (\Throwable $e) {
            Log::error('Tenant provisioning failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            $tenant->update([
                'status'            => Tenant::STATUS_PROVISIONING_FAILED,
                'provisioning_error' => $e->getMessage(),
            ]);

            TenantProvisioningFailed::dispatch($tenant, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Retry provisioning for a failed tenant.
     */
    public function retry(Tenant $tenant): Tenant
    {
        if ($tenant->status !== Tenant::STATUS_PROVISIONING_FAILED) {
            throw new \RuntimeException('Tenant is not in a failed provisioning state.');
        }

        return $this->provision($tenant, []);
    }

    // ============================================
    // INTERNALS
    // ============================================

    protected function resolveOwnerUser(array $ownerData): User
    {
        $email = strtolower(trim($ownerData['email'] ?? ''));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('An owner with a valid email address is required.');
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $name = trim($ownerData['name'] ?? '');
        [$firstName, $lastName] = $this->splitName($name);

        $password = $ownerData['password'] ?? '11111111';

        return User::create([
            'first_name'    => $firstName,
            'last_name'     => $lastName,
            'username'      => Str::slug($firstName) . '-' . Str::lower(Str::random(5)),
            'email'         => $email,
            'password'      => Hash::make($password),
            'uuid'          => (string) Str::uuid(),
            'timezone'      => 'UTC',
            'language'      => 'en',
            'preferences'   => [
                'admin_created'             => true,
                'must_change_password'      => true,
                'temporary_password'        => $password,
            ],
        ]);
    }

    protected function attachOwnerMembership(Tenant $tenant, User $owner): void
    {
        $exists = TenantUser::where('tenant_id', $tenant->id)
            ->where('user_id', $owner->id)
            ->exists();

        if (! $exists) {
            TenantUser::create([
                'tenant_id'   => $tenant->id,
                'user_id'     => $owner->id,
                'role'        => 'owner',
                'accepted_at' => now(),
            ]);
        }

        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'api']);
        if (! $owner->hasRole($ownerRole)) {
            $owner->assignRole($ownerRole);
        }

        // Make the new tenant the owner's current tenant only if they have none.
        if (! $owner->current_tenant_id) {
            $owner->update(['current_tenant_id' => $tenant->id]);
        }
    }

    protected function initializeDefaults(Tenant $tenant): void
    {
        try {
            if (! tenancy()->initialized) {
                Tenancy::initialize($tenant);

                if (! \App\Models\Tenant\AIConfiguration::exists()) {
                    \App\Models\Tenant\AIConfiguration::create([
                        'provider'                 => 'gemini',
                        'model'                    => 'gemini-1.5-flash',
                        'enabled'                  => true,
                        'auto_reply_enabled'       => true,
                        'auto_escalation_enabled'  => true,
                        'streaming_enabled'        => true,
                        'knowledge_base_enabled'   => true,
                        'temperature'              => 0.7,
                        'max_tokens'               => 2000,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Tenant defaults initialization skipped', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);
        } finally {
            if (tenancy()->initialized) {
                Tenancy::end();
            }
        }
    }

    protected function resolvePlan(?int $planId): ?Plan
    {
        if ($planId) {
            $plan = Plan::find($planId);
            if ($plan && $plan->is_active) {
                return $plan;
            }
        }

        return Plan::where('is_default', true)->where('is_active', true)->first()
            ?? Plan::where('is_active', true)->orderBy('price_monthly')->first();
    }

    protected function createTrialSubscription(Tenant $tenant, ?Plan $plan): void
    {
        if ($tenant->subscriptions()->exists()) {
            return; // Idempotency — a subscription already exists.
        }

        if (! $plan) {
            Log::warning('No plan available while provisioning tenant', ['tenant_id' => $tenant->id]);
            return;
        }

        $this->subscriptions->createSubscription($tenant, $plan, 'monthly');
    }

    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2);

        return [
            $parts[0] ?? 'Tenant',
            $parts[1] ?? '',
        ];
    }
}