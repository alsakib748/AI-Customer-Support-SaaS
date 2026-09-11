<?php

namespace App\Http\Controllers\Api\V1\AI;

use App\Ai\Services\AIUsageService;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AILog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AIUsageController extends Controller
{
    protected AIUsageService $usageService;

    public function __construct(AIUsageService $usageService)
    {
        $this->usageService = $usageService;
    }

    /**
     * Get AI usage statistics
     */
    public function index(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('ai.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view AI usage.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'data' => $this->getEmptyUsage(),
                ]);
            }

            $period = $request->input('period', 'month');
            $usage = $this->usageService->getStatistics(tenant()->id, $period);

            return response()->json([
                'success' => true,
                'data' => $usage,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get AI usage:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI usage.',
            ], 500);
        }
    }

    /**
     * Get AI health metrics
     */
    public function health(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('ai.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view AI health.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_requests' => 0,
                        'success_rate' => 0,
                        'failure_rate' => 0,
                        'avg_response_time' => 0,
                        'status' => 'healthy',
                    ],
                ]);
            }

            $health = $this->usageService->getHealthMetrics(tenant()->id);

            return response()->json([
                'success' => true,
                'data' => $health,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get AI health:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI health.',
            ], 500);
        }
    }

    /**
     * Get AI analytics
     */
    public function analytics(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('ai.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view AI analytics.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'total_requests' => 0,
                        'total_cost' => 0,
                        'avg_cost_per_request' => 0,
                        'trend' => [],
                    ],
                ]);
            }

            $analytics = $this->usageService->getAnalytics(tenant()->id);

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get AI analytics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI analytics.',
            ], 500);
        }
    }

    /**
     * Get AI logs
     */
    public function logs(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('ai.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view AI logs.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $filters = $request->only(['agent', 'provider', 'status', 'date_from', 'date_to', 'per_page']);

            $query = AILog::query();

            if (!empty($filters['agent'])) {
                $query->where('agent', $filters['agent']);
            }

            if (!empty($filters['provider'])) {
                $query->where('provider', $filters['provider']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            $logs = $query->orderBy('created_at', 'desc')
                ->paginate($filters['per_page'] ?? 20);

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

        } catch (\Exception $e) {
            Log::error('Failed to get AI logs:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve AI logs.',
            ], 500);
        }
    }

    protected function getEmptyUsage(): array
    {
        return [
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => 0,
            'by_provider' => [],
            'by_model' => [],
            'daily_usage' => [],
        ];
    }
}