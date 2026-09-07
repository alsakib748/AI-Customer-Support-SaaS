<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

class WidgetRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, $limit = 20, $time = 60): Response
    {
        $key = $this->resolveRequestSignature($request);

        if (RateLimiterFacade::tooManyAttempts($key, $limit)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => RateLimiterFacade::availableIn($key),
            ], 429);
        }

        RateLimiterFacade::hit($key, $time);

        return $next($request);
    }

    protected function resolveRequestSignature($request)
    {
        return sha1(
            $request->method() . '|' . $request->path() . '|' .
            ($request->ip() ?? '0.0.0.0') . '|' .
            ($request->input('session_token') ?? '')
        );
    }
}