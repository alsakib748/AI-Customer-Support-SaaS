<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\PaymentResource;
use App\Services\Billing\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected PaymentService $service;

    public function __construct(PaymentService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of payments
     */
    public function index(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('billing.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view payments.',
            //     ], 403);
            // }

            $tenant = app('current_tenant');
            $filters = $request->only(['status', 'provider', 'per_page']);

            $payments = $this->service->getPayments($tenant->id, $filters);

            return response()->json([
                'success' => true,
                'data' => PaymentResource::collection($payments->items()),
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payments:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payments.',
            ], 500);
        }
    }

    /**
     * Get payment statistics
     */
    public function statistics(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('billing.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view statistics.',
            //     ], 403);
            // }

            $tenant = app('current_tenant');
            $statistics = $this->service->getStatistics($tenant->id);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get payment statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
            ], 500);
        }
    }
}