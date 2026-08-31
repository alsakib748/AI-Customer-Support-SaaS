<?php
// app/Services/Workspace/WorkspaceService.php

namespace App\Services\Workspace;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

class WorkspaceService
{
    /**
     * Get the current workspace (tenant).
     */
    public function getWorkspace(): ?Tenant
    {
        return app('current_tenant');
    }

    /**
     * Get workspace by ID.
     */
    public function getWorkspaceById(string $id): ?Tenant
    {
        return Tenant::find($id);
    }

    /**
     * Update the current workspace.
     */
    public function updateWorkspace(array $data): Tenant
    {
        $tenant = $this->getWorkspace();

        if (!$tenant) {
            throw new \Exception('No workspace found');
        }

        // Update only the fields that are allowed
        $tenant->update([
            'name' => $data['name'],
            'industry' => $data['industry'] ?? $tenant->industry,
            'support_email' => $data['support_email'] ?? $tenant->support_email,
            'support_phone' => $data['support_phone'] ?? $tenant->support_phone,
            'timezone' => $data['timezone'] ?? $tenant->timezone,
            'default_language' => $data['default_language'] ?? $tenant->default_language,
        ]);

        Log::info('Workspace updated', [
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
        ]);

        return $tenant->fresh();
    }

    /**
     * Update workspace logo.
     */
    public function updateLogo($file): Tenant
    {
        $tenant = $this->getWorkspace();

        if (!$tenant) {
            throw new \Exception('No workspace found');
        }

        // Delete old logo if exists
        if ($tenant->logo) {
            $this->deleteFile($tenant->logo);
        }

        $path = $file->store('workspace-logos', 'public');
        $tenant->update(['logo' => url("/tenant-assets/{$tenant->id}/{$path}")]);

        return $tenant->fresh();
    }

    /**
     * Delete workspace logo.
     */
    public function deleteLogo(): Tenant
    {
        $tenant = $this->getWorkspace();

        if (!$tenant) {
            throw new \Exception('No workspace found');
        }

        if ($tenant->logo) {
            $this->deleteFile($tenant->logo);
        }

        $tenant->update(['logo' => null]);

        return $tenant->fresh();
    }

    /**
     * Update workspace favicon.
     */
    public function updateFavicon($file): Tenant
    {
        $tenant = $this->getWorkspace();

        if (!$tenant) {
            throw new \Exception('No workspace found');
        }

        if ($tenant->favicon) {
            $this->deleteFile($tenant->favicon);
        }

        $path = $file->store('workspace-favicons', 'public');
        $tenant->update(['favicon' => url("/tenant-assets/{$tenant->id}/{$path}")]);

        return $tenant->fresh();
    }

    /**
     * Delete workspace favicon.
     */
    public function deleteFavicon(): Tenant
    {
        $tenant = $this->getWorkspace();

        if (!$tenant) {
            throw new \Exception('No workspace found');
        }

        if ($tenant->favicon) {
            $this->deleteFile($tenant->favicon);
        }

        $tenant->update(['favicon' => null]);

        return $tenant->fresh();
    }

    /**
     * Update business hours.
     */
    public function updateBusinessHours(array $businessHours): Tenant
    {
        $tenant = $this->getWorkspace();

        if (!$tenant) {
            throw new \Exception('No workspace found');
        }

        $tenant->update(['business_hours' => $businessHours]);

        Log::info('Business hours updated', [
            'tenant_id' => $tenant->id,
            'user_id' => auth()->id(),
        ]);

        return $tenant->fresh();
    }

    /**
     * Update workspace settings.
     */
    public function updateSettings(array $settings): Tenant
    {
        $tenant = $this->getWorkspace();

        if (!$tenant) {
            throw new \Exception('No workspace found');
        }

        $currentSettings = $tenant->settings ?? [];
        $mergedSettings = array_merge($currentSettings, $settings);

        $tenant->update(['settings' => $mergedSettings]);

        return $tenant->fresh();
    }

    /**
     * Delete a file from storage.
     */
    protected function deleteFile(string $url): void
    {
        try {
            $path = str_replace('/storage/', '', $url);
            if (\Storage::disk('public')->exists($path)) {
                \Storage::disk('public')->delete($path);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete file:', ['url' => $url, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Check if the current user has workspace access.
     */
    public function hasAccess(string $tenantId, int $userId): bool
    {
        return Tenant::where('id', $tenantId)
            ->whereHas('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->exists();
    }

    /**
     * Get workspace statistics.
     */
    public function getStatistics(Tenant $tenant): array
    {
        return [
            'total_team_members' => $tenant->tenantUsers()->count(),
            'total_customers' => 0, // Will be implemented later
            'total_conversations' => 0, // Will be implemented later
            'total_tickets' => 0, // Will be implemented later
        ];
    }

}