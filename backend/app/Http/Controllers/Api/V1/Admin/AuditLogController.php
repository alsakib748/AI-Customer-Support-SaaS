<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    protected AuditLogService $auditLogService;

    public function __construct(AuditLogService $auditLogService)
    {
        $this->auditLogService = $auditLogService;
    }

    /**
     * Platform-wide audit log feed (Super Admin).
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 25);
            $perPage = max(1, min($perPage, 100));

            $logs = $this->auditLogService->paginate(
                $request->only([
                    'tenant_id', 'user_id', 'action', 'resource_type',
                    'search', 'date_from', 'date_to',
                ]),
                $perPage
            );

            return response()->json([
                'success' => true,
                'data' => $logs->items(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch platform audit logs', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch audit logs.',
            ], 500);
        }
    }

    /**
     * Platform-wide audit log statistics (Super Admin).
     */
    public function statistics(Request $request)
    {
        try {
            $statistics = $this->auditLogService->getStatistics(
                $request->input('tenant_id'),
                $request->input('start_date'),
                $request->input('end_date')
            );

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to fetch platform audit statistics', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch audit statistics.',
            ], 500);
        }
    }
}
