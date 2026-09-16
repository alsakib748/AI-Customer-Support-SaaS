<?php
// app/Services/Billing/PlanService.php

namespace App\Services\Billing;

use App\Models\Plan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PlanService
{
    /**
     * Get all active plans
     */
    public function getActivePlans(): Collection
    {
        return Plan::active()->ordered()->get();
    }

    /**
     * Get public plans (for pricing page)
     */
    public function getPublicPlans(): Collection
    {
        return Plan::active()->public()->ordered()->get();
    }

    /**
     * Get plan by ID
     */
    public function getPlan(int $id): Plan
    {
        return Plan::findOrFail($id);
    }

    /**
     * Get plan by slug
     */
    public function getPlanBySlug(string $slug): Plan
    {
        return Plan::where('slug', $slug)->firstOrFail();
    }

    /**
     * Get default plan
     */
    public function getDefaultPlan(): ?Plan
    {
        return Plan::getDefaultPlan();
    }

    /**
     * Get free plan
     */
    public function getFreePlan(): ?Plan
    {
        return Plan::getFreePlan();
    }

    /**
     * Create a new plan
     */
    public function createPlan(array $data): Plan
    {
        $plan = Plan::create($data);

        Log::info('Plan created', [
            'plan_id' => $plan->id,
            'name' => $plan->name,
            'user_id' => auth()->id(),
        ]);

        return $plan;
    }

    /**
     * Update a plan
     */
    public function updatePlan(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        Log::info('Plan updated', [
            'plan_id' => $plan->id,
            'user_id' => auth()->id(),
        ]);

        return $plan->fresh();
    }

    /**
     * Delete a plan
     */
    public function deletePlan(Plan $plan): bool
    {
        // Check if plan has active subscriptions
        $activeSubscriptions = $plan->subscriptions()->active()->count();

        if ($activeSubscriptions > 0) {
            throw new \Exception('Cannot delete plan with active subscriptions.');
        }

        $plan->delete();

        Log::info('Plan deleted', [
            'plan_id' => $plan->id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Compare plans
     */
    public function comparePlans(array $planIds): array
    {
        $plans = Plan::whereIn('id', $planIds)->get();

        return [
            'plans' => $plans,
            'features' => $this->getAllFeatures($plans),
            'limits' => $this->getAllLimits($plans),
        ];
    }

    /**
     * Get all unique features across plans
     */
    protected function getAllFeatures(Collection $plans): array
    {
        $features = [];

        foreach ($plans as $plan) {
            foreach ($plan->features ?? [] as $key => $value) {
                if (!isset($features[$key])) {
                    $features[$key] = [
                        'name' => $key,
                        'plans' => [],
                    ];
                }
                $features[$key]['plans'][$plan->id] = $value;
            }
        }

        return $features;
    }

    /**
     * Get all unique limits across plans
     */
    protected function getAllLimits(Collection $plans): array
    {
        $limits = [];

        foreach ($plans as $plan) {
            foreach ($plan->limits ?? [] as $key => $value) {
                if (!isset($limits[$key])) {
                    $limits[$key] = [
                        'name' => $key,
                        'plans' => [],
                    ];
                }
                $limits[$key]['plans'][$plan->id] = $value;
            }
        }

        return $limits;
    }
}