<?php
namespace App\Http\Controllers\Api\V1\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanProviderPrice;
use Illuminate\Http\Request;

class PlanProviderPriceController extends Controller
{
    public function index(Plan $plan)
    {
        return response()->json([
            'success' => true,
            'data'    => $plan->providerPrices()->get(),
        ]);
    }

    public function store(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'provider'          => ['required', 'in:stripe,paypal'],
            'billing_cycle'     => ['required', 'in:monthly,yearly'],
            'currency'          => ['nullable', 'string', 'size:3'],
            'provider_price_id' => ['required', 'string', 'max:255'],
            'amount'            => ['required', 'numeric', 'min:0'],
            'is_active'         => ['nullable', 'boolean'],
        ]);

        $data['currency'] = $data['currency'] ?? 'USD';

        $price = $plan->providerPrices()->updateOrCreate(
            [
                'provider'      => $data['provider'],
                'billing_cycle' => $data['billing_cycle'],
                'currency'      => $data['currency'],
            ],
            $data
        );

        return response()->json(['success' => true, 'data' => $price], 201);
    }

    public function destroy(PlanProviderPrice $price)
    {
        $price->delete();
        return response()->json(['success' => true]);
    }
}
