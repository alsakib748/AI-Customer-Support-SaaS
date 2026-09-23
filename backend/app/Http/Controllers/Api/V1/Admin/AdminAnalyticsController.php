<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\PlatformAnalyticsService;
use Illuminate\Http\Request;

class AdminAnalyticsController extends Controller
{
    public function __construct(
        protected PlatformAnalyticsService $service
    ) {
    }

    public function overview()
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Super Admin only.');
        }
        return response()->json(['success' => true, 'data' => $this->service->overview()]);
    }

    public function tenantUsage()
    {
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Super Admin only.');
        }
        return response()->json(['success' => true, 'data' => $this->service->tenantUsage()]);
    }
}