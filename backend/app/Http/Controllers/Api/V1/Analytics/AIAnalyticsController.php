<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Services\Analytics\AIAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AIAnalyticsController extends Controller
{
    public function __construct(
        protected AIAnalyticsService $service
    ) {
    }

    public function index(AnalyticsFilterRequest $request): JsonResponse
    {
        try {
            // $this->authorizeAnalytics('analytics.ai');

            $service = $this->service->withFilters($request->validated());

            return response()->json([
                'success' => true,
                'data' => $service->report(),
                'meta' => $service->getMeta(),
            ]);
        } catch (\Exception $e) {
            Log::error('AI analytics failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load AI analytics.',
            ], 500);
        }
    }

    protected function authorizeAnalytics(string $permission): void
    {
        if (!auth()->user()->hasPermissionTo($permission)) {
            abort(403, 'You do not have permission to view this analytics.');
        }

        if (auth()->user()->hasRole('super_admin')) {
            abort(403, 'Super Admin must use platform analytics.');
        }
    }
}