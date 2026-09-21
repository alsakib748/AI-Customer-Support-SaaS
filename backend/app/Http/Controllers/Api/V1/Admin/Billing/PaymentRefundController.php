<?php
namespace App\Http\Controllers\Api\V1\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\RefundPaymentRequest;
use App\Models\Payment;
use App\Services\Billing\PaymentService;

class PaymentRefundController extends Controller
{
    public function __construct(protected PaymentService $payments)
    {}

    public function refund(RefundPaymentRequest $request, Payment $payment)
    {
        try {
            $refunded = $this->payments->refund(
                $payment,
                $request->input('amount'),
                $request->input('reason'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Refund processed successfully.',
                'data'    => new \App\Http\Resources\Billing\PaymentResource($refunded),
            ]);
        } catch (\Throwable $e) {
            \Log::error('Refund failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
