<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ChatWidget\WidgetSessionService;
use Illuminate\Http\Request;

class WidgetCleanupController extends Controller
{
    protected WidgetSessionService $sessionService;

    public function __construct(WidgetSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Get cleanup statistics
     */
    public function stats(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $stats = $this->sessionService->getCleanupStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Trigger cleanup manually
     */
    public function cleanup(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'soft' => ['nullable', 'boolean'],
        ]);

        $days = $request->input('days', 30);
        $soft = $request->input('soft', false);

        if ($soft) {
            $results = $this->sessionService->softCleanupExpiredSessions();
        } else {
            $results = $this->sessionService->cleanupExpiredSessions();
        }

        return response()->json([
            'success' => true,
            'message' => 'Cleanup completed successfully',
            'data' => $results,
        ]);
    }
}