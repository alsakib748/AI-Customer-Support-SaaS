<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Services\Analytics\WidgetAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class WidgetAnalyticsController extends Controller
{
    public function __construct(
        protected WidgetAnalyticsService $service
    ) {
    }

    public function index(AnalyticsFilterRequest $request): JsonResponse
    {
        try {
            // $this->authorizeAnalytics('analytics.widget');

            $service = $this->service->withFilters($request->validated());

            return response()->json([
                'success' => true,
                'data' => $service->report(),
                'meta' => $service->getMeta(),
            ]);
        } catch (\Exception $e) {
            Log::error('Widget analytics failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load widget analytics.',
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