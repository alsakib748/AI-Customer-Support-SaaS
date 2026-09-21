<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function __construct(protected PaymentGatewayManager $gateways) {}

    public function index()
    {
        return response()->json([
            'success' => true,
            'data'    => $this->gateways->availableProviders(),
        ]);
    }
}
