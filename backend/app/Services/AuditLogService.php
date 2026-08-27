<?php
// app/Services/AuditLogService.php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    /**
     * Log an audit event
     */
    public function log(
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): AuditLog {
        $user = auth()->user();
        $tenant = app('current_tenant');

        return AuditLog::create([
            'tenant_id' => $tenant?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'metadata' => array_merge([
                'url' => Request::fullUrl(),
                'method' => Request::method(),
            ], $metadata ?? []),
        ]);
    }

    /**
     * Get audit logs for a tenant
     */
    public function getTenantLogs(string $tenantId, int $limit = 100): array
    {
        return AuditLog::where('tenant_id', $tenantId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get audit logs for a user
     */
    public function getUserLogs(int $userId, int $limit = 100): array
    {
        return AuditLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get audit logs for a resource
     */
    public function getResourceLogs(string $resourceType, int $resourceId, int $limit = 100): array
    {
        return AuditLog::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
