<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\PlanResource;
use App\Services\Billing\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    protected PlanService $service;

    public function __construct(PlanService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of plans
     */
    public function index(Request $request)
    {
        try {
            if ($request->boolean('public')) {
                $plans = $this->service->getPublicPlans();
            } else {
                $plans = $this->service->getActivePlans();
            }

            return response()->json([
                'success' => true,
                'data' => PlanResource::collection($plans),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get plans:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve plans.',
            ], 500);
        }
    }

    /**
     * Get a single plan
     */
    public function show($id)
    {
        try {
            $plan = $this->service->getPlan($id);

            return response()->json([
                'success' => true,
                'data' => new PlanResource($plan),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get plan:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve plan.',
            ], 500);
        }
    }

    /**
     * Compare plans
     */
    public function compare(Request $request)
    {
        try {
            $request->validate([
                'plan_ids' => 'required|array|min:2',
                'plan_ids.*' => 'integer|exists:plans,id',
            ]);

            $comparison = $this->service->comparePlans($request->plan_ids);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to compare plans:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to compare plans.',
            ], 500);
        }
    }
}