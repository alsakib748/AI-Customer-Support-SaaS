<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Jobs\Billing\ProcessPaymentWebhook;
use App\Services\Billing\StripeService;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    protected StripeService $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Handle Stripe webhook
     */
    public function stripe(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        if (!$this->stripeService->verifyWebhook($payload, $signature)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature.',
            ], 400);
        }

        $event = json_decode($payload, true);

        // Process async
        ProcessPaymentWebhook::dispatch('stripe', $event);

        return response()->json(['received' => true]);
    }

    /**
     * Handle PayPal webhook
     */
    public function paypal(Request $request)
    {
        $payload = $request->all();

        // Process async
        ProcessPaymentWebhook::dispatch('paypal', $payload);

        return response()->json(['received' => true]);
    }
}