<?php
// app/Services/TenantService.php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Stancl\Tenancy\Facades\Tenancy;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class TenantService
{
    /**
     * Create default tenant for new user
     */
    public function createDefaultTenant(User $user, array $data): Tenant
    {
        $tenantName = $data['company_name'] ?? $user->full_name . "'s Workspace";
        $slug = Str::slug($tenantName);
        $subdomain = $data['subdomain'] ?? Str::slug($tenantName) . '-' . Str::random(4);

        // Create tenant - Stancl will auto-generate the ID
        $tenant = Tenant::create([
            'name' => $tenantName,
            'slug' => $slug,
            'subdomain' => $subdomain,
            'domain' => $data['domain'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'default_language' => 'en',
            'status' => 'active',
            'metadata' => [
                'registered_from' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
            'data' => [
                'name' => $tenantName,
                'slug' => $slug,
                'subdomain' => $subdomain,
                'domain' => $data['domain'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'default_language' => 'en',
                'status' => 'active',
            ],
        ]);

        // Create domain for subdomain
        $centralDomain = config('tenancy.central_domains')[0] ?? 'localhost';
        Domain::create([
            'domain' => $subdomain . '.' . $centralDomain,
            'tenant_id' => $tenant->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Assign user as owner
        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        // Assign owner role
        $ownerRole = Role::firstOrCreate([
            'name' => 'owner',
            'guard_name' => 'api',
        ]);
        $user->assignRole($ownerRole);

        // Set current tenant
        $user->update(['current_tenant_id' => $tenant->id]);

        return $tenant;
    }

    /**
     * Create a new tenant
     */
    public function create(array $data, int $userId): Tenant
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:tenants',
            'subdomain' => 'nullable|string|max:255|unique:tenants',
            'domain' => 'nullable|string|max:255|unique:tenants',
            'industry' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|timezone',
            'support_email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $slug = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(4);
        $subdomain = $data['subdomain'] ?? $slug;

        // Create tenant
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => $data['name'],
            'slug' => $slug,
            'subdomain' => $subdomain,
            'domain' => $data['domain'] ?? null,
            'industry' => $data['industry'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'support_email' => $data['support_email'] ?? null,
            'default_language' => 'en',
            'status' => 'active',
        ]);

        // Create subdomain domain
        Domain::create([
            'domain' => $subdomain . '.' . config('tenancy.central_domains')[0],
            'tenant_id' => $tenant->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        // Create custom domain if provided
        if ($data['domain'] ?? false) {
            Domain::create([
                'domain' => $data['domain'],
                'tenant_id' => $tenant->id,
                'is_primary' => true,
                'status' => 'active',
                'verified_at' => now(), // In production, verify first
            ]);
        }

        // Assign user as owner
        TenantUser::create([
            'tenant_id' => $tenant->id,
            'user_id' => $userId,
            'role' => 'owner',
            'accepted_at' => now(),
        ]);

        $user = User::find($userId);
        $user->assignRole('owner');
        $user->update(['current_tenant_id' => $tenant->id]);

        return $tenant;
    }

    /**
     * Update tenant details
     */
    public function update(string $tenantId, array $data): Tenant
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }

        $validator = Validator::make($data, [
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:tenants,slug,' . $tenantId . ',id',
            'subdomain' => 'sometimes|string|max:255|unique:tenants,subdomain,' . $tenantId . ',id',
            'domain' => 'sometimes|string|max:255|unique:tenants,domain,' . $tenantId . ',id',
            'logo_url' => 'nullable|url',
            'favicon_url' => 'nullable|url',
            'industry' => 'nullable|string|max:100',
            'timezone' => 'nullable|string|timezone',
            'support_email' => 'nullable|email',
            'support_phone' => 'nullable|string|max:20',
            'business_hours' => 'nullable|array',
            'settings' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Handle JSON fields
        if (isset($validated['business_hours'])) {
            $validated['business_hours'] = json_encode($validated['business_hours']);
        }
        if (isset($validated['settings'])) {
            $validated['settings'] = json_encode($validated['settings']);
        }
        if (isset($validated['metadata'])) {
            $validated['metadata'] = json_encode($validated['metadata']);
        }

        $tenant->update($validated);

        // Update domains if domain changed
        if (isset($data['domain']) && $data['domain'] !== $tenant->domain) {
            // Create new domain
            Domain::create([
                'domain' => $data['domain'],
                'tenant_id' => $tenant->id,
                'is_primary' => true,
                'status' => 'active',
            ]);
        }

        return $tenant->fresh();
    }

    /**
     * Switch current tenant
     */
    public function switchTenant(int $userId, string $tenantId): Tenant
    {
        $tenantUser = TenantUser::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$tenantUser) {
            throw new \Exception('User does not have access to this tenant');
        }

        $user = User::find($userId);
        $user->update(['current_tenant_id' => $tenantId]);

        // Initialize tenant context
        $tenant = Tenant::find($tenantId);
        Tenancy::initialize($tenant);

        return $tenant;
    }

    /**
     * Get tenant users with roles
     */
    public function getTenantUsers(string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }

        return $tenant->tenantUsers()
            ->with('user')
            ->get()
            ->map(function ($tenantUser) {
                return [
                    'id' => $tenantUser->id,
                    'user_id' => $tenantUser->user_id,
                    'name' => $tenantUser->user->full_name,
                    'email' => $tenantUser->user->email,
                    'avatar_url' => $tenantUser->user->avatar_url,
                    'role' => $tenantUser->role,
                    'department' => $tenantUser->department,
                    'position' => $tenantUser->position,
                    'availability_status' => $tenantUser->availability_status,
                    'max_concurrent_chats' => $tenantUser->max_concurrent_chats,
                    'skills' => $tenantUser->skills,
                    'accepted_at' => $tenantUser->accepted_at,
                    'invited_at' => $tenantUser->invited_at,
                    'joined_at' => $tenantUser->created_at,
                ];
            })
            ->toArray();
    }

    /**
     * Invite user to tenant
     */
    public function inviteUser(string $tenantId, array $data): array
    {
        $validator = Validator::make($data, [
            'email' => 'required|email|exists:users',
            'role' => 'required|string|in:admin,manager,agent,viewer',
            'department' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }

        $user = User::where('email', $data['email'])->first();

        // Check if user already belongs to tenant
        $existing = TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            throw new \Exception('User already belongs to this tenant');
        }

        // Create tenant user record
        $tenantUser = TenantUser::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'role' => $data['role'],
            'department' => $data['department'] ?? null,
            'invited_at' => now(),
        ]);

        // Assign role in Spatie
        $role = Role::firstOrCreate([
            'name' => $data['role'],
            'guard_name' => 'api',
        ]);
        $user->assignRole($role);

        // TODO: Send invitation email

        return [
            'user' => $user,
            'role' => $data['role'],
            'tenant' => $tenant,
        ];
    }

    /**
     * Remove user from tenant
     */
    public function removeUser(string $tenantId, int $userId): void
    {
        $tenantUser = TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$tenantUser) {
            throw new \Exception('User not found in this tenant');
        }

        // Don't allow removing the owner if it's the last owner
        if ($tenantUser->role === 'owner') {
            $ownerCount = TenantUser::where('tenant_id', $tenantId)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                throw new \Exception('Cannot remove the last owner of the tenant');
            }
        }

        $tenantUser->delete();

        // Remove Spatie role if user has no other tenants
        $user = User::find($userId);
        if ($user && $user->tenants()->count() === 0) {
            $user->syncRoles([]);
        }
    }

    /**
     * Update user role in tenant
     */
    public function updateUserRole(string $tenantId, int $userId, string $newRole): array
    {
        $tenantUser = TenantUser::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if (!$tenantUser) {
            throw new \Exception('User not found in this tenant');
        }

        // Don't change owner role
        if ($tenantUser->role === 'owner' && $newRole !== 'owner') {
            $ownerCount = TenantUser::where('tenant_id', $tenantId)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                throw new \Exception('Cannot change the role of the last owner');
            }
        }

        $tenantUser->update(['role' => $newRole]);

        // Update Spatie role
        $user = User::find($userId);
        $role = Role::firstOrCreate([
            'name' => $newRole,
            'guard_name' => 'api',
        ]);
        $user->syncRoles([$role]);

        return [
            'user_id' => $userId,
            'old_role' => $tenantUser->getOriginal('role'),
            'new_role' => $newRole,
        ];
    }

    /**
     * Get tenant by ID or slug
     */
    public function getTenant(string $identifier): ?Tenant
    {
        return Tenant::where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }

    /**
     * Get tenant settings
     */
    public function getTenantSettings(string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }

        return $tenant->settings ?? [];
    }

    /**
     * Update tenant settings
     */
    public function updateTenantSettings(string $tenantId, array $settings): array
    {
        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new \Exception('Tenant not found');
        }

        $currentSettings = $tenant->settings ?? [];
        $mergedSettings = array_merge($currentSettings, $settings);

        $tenant->update(['settings' => $mergedSettings]);

        return $mergedSettings;
    }
}