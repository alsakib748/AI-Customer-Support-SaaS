<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Services\Analytics\AnalyticsOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OverviewController extends Controller
{
    public function __construct(
        protected AnalyticsOverviewService $service
    ) {
    }

    public function index(AnalyticsFilterRequest $request): JsonResponse
    {
        try {
            // if (!auth()->user()->hasPermissionTo('analytics.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view analytics.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin must use platform analytics endpoints.',
                ], 403);
            }

            $data = $this->service
                ->withFilters($request->validated())
                ->overview();

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => array_merge(
                    $this->service->meta ?? [],
                    []
                ),
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics overview failed:', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load analytics overview.',
            ], 500);
        }
    }
}
