<?php
// app/Services/AuditLogService.php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    /**
     * Log an audit event.
     */
    public function log(
        string $action,
        ?string $resourceType = null,
        ?int $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): ?AuditLog {
        try {
            $user = Auth::user();

            // current_tenant is only bound by the TenantAware middleware,
            // which is NOT applied to public routes (register, login, forgot-password, etc).
            // Resolve it safely so we don't throw on those routes.
            $tenant = null;
            try {
                $tenant = app('current_tenant');
            } catch (\Throwable $e) {
                $tenant = null;
            }

            // Get IP and User Agent safely
            $ip = null;
            $userAgent = null;

            try {
                $ip = Request::ip();
                $userAgent = Request::userAgent();
            } catch (\Exception $e) {
                // Ignore request errors
            }

            $auditLogData = [
                'tenant_id' => $tenant?->id,
                'user_id' => $user?->id,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'metadata' => array_merge(
                    [
                        'url' => Request::fullUrl(),
                        'method' => Request::method(),
                    ],
                    $metadata ?? []
                ),
            ];

            // Create audit log
            $auditLog = AuditLog::create($auditLogData);

            return $auditLog;

        } catch (\Exception $e) {
            // Log the error but don't break the application
            Log::error('Failed to create audit log:', [
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Log a user action.
     */
    public function logUserAction(User $user, string $action, ?array $metadata = null): ?AuditLog
    {
        return $this->log(
            $action,
            'user',
            $user->id,
            null,
            ['email' => $user->email, 'name' => $user->full_name],
            $metadata
        );
    }

    /**
     * Log a resource creation.
     */
    public function logCreation(string $resourceType, $resource, array $data = []): ?AuditLog
    {
        return $this->log(
            $resourceType . '_created',
            $resourceType,
            $resource->id,
            null,
            $data,
            ['event' => 'created']
        );
    }

    /**
     * Log a resource update.
     */
    public function logUpdate(string $resourceType, $resource, array $oldValues, array $newValues): ?AuditLog
    {
        return $this->log(
            $resourceType . '_updated',
            $resourceType,
            $resource->id,
            $oldValues,
            $newValues,
            ['event' => 'updated']
        );
    }

    /**
     * Log a resource deletion.
     */
    public function logDeletion(string $resourceType, $resource, array $data = []): ?AuditLog
    {
        return $this->log(
            $resourceType . '_deleted',
            $resourceType,
            $resource->id,
            $data,
            null,
            ['event' => 'deleted']
        );
    }

    /**
     * Log an AI action.
     */
    public function logAIAction(string $action, array $data = []): ?AuditLog
    {
        return $this->log(
            $action,
            'ai',
            null,
            null,
            $data,
            ['event' => 'ai_action']
        );
    }

    /**
     * Get audit logs for a tenant.
     */
    public function getTenantLogs(string $tenantId, int $limit = 100, int $offset = 0): array
    {
        return AuditLog::forTenant($tenantId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get audit logs for a user.
     */
    public function getUserLogs(int $userId, int $limit = 100, int $offset = 0): array
    {
        return AuditLog::forUser($userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get audit logs for a resource.
     */
    public function getResourceLogs(string $resourceType, int $resourceId, int $limit = 100): array
    {
        return AuditLog::resource($resourceType, $resourceId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get audit log statistics for a tenant (or globally if no tenant id given).
     *
     * @return array{ total: int, by_action: array<string,int>, by_resource_type: array<string,int>, recent: array }
     */
    public function getStatistics(?string $tenantId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = AuditLog::query();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $total = (clone $query)->count();

        $byAction = (clone $query)
            ->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')
            ->pluck('count', 'action')
            ->toArray();

        $byResourceType = (clone $query)
            ->selectRaw('resource_type, COUNT(*) as count')
            ->whereNotNull('resource_type')
            ->groupBy('resource_type')
            ->pluck('count', 'resource_type')
            ->toArray();

        $recent = (clone $query)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'total' => $total,
            'by_action' => $byAction,
            'by_resource_type' => $byResourceType,
            'recent' => $recent,
        ];
    }
}
