<?php

namespace App\Http\Controllers\Api\V1\Admin\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreCouponRequest;
use App\Http\Resources\Billing\CouponCollection;
use App\Http\Resources\Billing\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\Request;

class AdminCouponController extends Controller
{
    public function index(Request $request)
    {
        $query = Coupon::query()
            ->when($request->boolean('active_only'), fn($q) => $q->where('is_active', true))
            ->latest();

        return new CouponCollection($query->paginate(50));
    }

    public function store(StoreCouponRequest $request)
    {
        $coupon = Coupon::create($request->validated());

        return (new CouponResource($coupon))
            ->additional(['message' => 'Coupon created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Coupon $coupon)
    {
        return new CouponResource($coupon);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'expires_at' => ['nullable', 'date'],
        ]));

        return (new CouponResource($coupon->fresh()))
            ->additional(['message' => 'Coupon updated successfully.']);
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();

        return response()->json([
            'success' => true,
            'message' => 'Coupon deleted successfully.',
        ]);
    }
}
