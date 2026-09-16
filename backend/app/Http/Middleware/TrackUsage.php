<?php

namespace App\Http\Middleware;

use App\Services\Billing\UsageTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUsage
{

    protected UsageTracker $tracker;

    public function __construct(UsageTracker $tracker)
    {
        $this->tracker = $tracker;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type = 'api_calls'): Response
    {
        $tenant = app('current_tenant');

        if (!$tenant) {
            return $next($request);
        }

        // Check limit BEFORE processing
        if ($this->tracker->hasReachedLimit($tenant->id, $type)) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached your ' . str_replace('_', ' ', $type) . ' limit. Please upgrade your plan.',
                'code' => 'usage_limit_reached',
                'type' => $type,
            ], 429);
        }

        // Process request
        $response = $next($request);

        // Track usage only on successful responses
        if ($response->getStatusCode() < 400) {
            $this->tracker->track($tenant->id, $type);
        }

        return $response;
    }
}