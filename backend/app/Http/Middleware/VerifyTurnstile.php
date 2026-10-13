<?php

namespace App\Http\Middleware;

use App\Services\Captcha\TurnstileVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests to protected (unauthenticated) endpoints that do not carry a
 * valid Cloudflare Turnstile token. The check is skipped entirely when bot
 * protection is disabled, so the middleware is safe to leave attached to routes
 * in every environment.
 */
class VerifyTurnstile
{
    public function __construct(private TurnstileVerifier $verifier) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->verifier->enabled()) {
            return $next($request);
        }

        $token = $request->input('cf-turnstile-response')
            ?? $request->input('turnstile_token')
            ?? $request->header('X-Turnstile-Token');

        if (! $this->verifier->verify(is_string($token) ? $token : null, $request->ip())) {
            return response()->json([
                'success' => false,
                'message' => 'Captcha verification failed. Please try again.',
                'errors' => [
                    'captcha' => ['Captcha verification failed. Please try again.'],
                ],
            ], 422);
        }

        return $next($request);
    }
}
