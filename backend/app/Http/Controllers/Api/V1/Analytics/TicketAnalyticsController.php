<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\AnalyticsFilterRequest;
use App\Services\Analytics\TicketAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TicketAnalyticsController extends Controller
{
    public function __construct(
        protected TicketAnalyticsService $service
    ) {
    }

    public function index(AnalyticsFilterRequest $request): JsonResponse
    {
        try {
            // $this->authorizeAnalytics('analytics.tickets');

            $service = $this->service->withFilters($request->validated());

            return response()->json([
                'success' => true,
                'data' => $service->report(),
                'meta' => $service->getMeta(),
            ]);
        } catch (\Exception $e) {
            Log::error('Ticket analytics failed:', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load ticket analytics.',
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