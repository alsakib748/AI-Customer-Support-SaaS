<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Get all audit logs for the current tenant
     */
    public function index(Request $request)
    {
        try {
            $tenant = app('current_tenant');

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tenant found',
                ], 404);
            }

            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            $logs = $this->auditLogService->getTenantLogs($tenant->id, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $logs->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch audit logs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific user
     */
    public function getUserLogs(Request $request, $userId)
    {
        try {
            $limit = $request->input('limit', 100);
            $offset = $request->input('offset', 0);

            $logs = $this->auditLogService->getUserLogs($userId, $limit, $offset);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user audit logs',
            ], 500);
        }
    }

    /**
     * Get audit log statistics
     */
    public function statistics(Request $request)
    {
        try {
            $tenant = app('current_tenant');

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tenant found',
                ], 404);
            }

            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $statistics = $this->auditLogService->getStatistics($tenant->id, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch audit statistics',
            ], 500);
        }
    }
}
