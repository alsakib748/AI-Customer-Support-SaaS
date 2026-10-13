<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
// use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Symfony\Component\HttpFoundation\Response;

class WidgetRateLimit
{
    protected RateLimiter $limiter;

    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, $limit = 60, $decay = 60): Response
    {

        // Cast to integers
        $limit = (int) $limit;
        $decay = (int) $decay;

        $key = $this->resolveRequestSignature($request);

        // $key = 'ai:' . ($request->user()?->id ?? $request->ip());

        if ($this->limiter->tooManyAttempts($key, $limit)) {
            $seconds = $this->limiter->availableIn($key);

            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again in '.$seconds.' seconds.',
                'retry_after' => $seconds,
            ], 429);
        }

        // RateLimiterFacade::hit($key, $decay);

        // Record the attempt
        $this->limiter->hit($key, $decay);

        return $next($request);
    }

    protected function resolveRequestSignature(Request $request): string
    {
        // Key on the authenticated user id or the client IP — never on a
        // caller-supplied session token, which an attacker can rotate to obtain
        // an unlimited number of fresh buckets.
        $identifier = auth()->id() ?? $request->ip();

        return 'widget.rate.limit:'.sha1($identifier.'|'.$request->path());
    }
}
