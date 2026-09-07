<?php

namespace App\Http\Controllers\Api\V1\ChatWidget;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ChatWidget;
use App\Services\ChatWidget\WidgetSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WidgetStatisticsController extends Controller
{
    protected WidgetSessionService $sessionService;

    public function __construct(WidgetSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Get statistics for a widget
     */
    public function show(ChatWidget $chatWidget)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('widgets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view widget statistics.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have widget context.',
                ], 404);
            }

            $stats = $this->sessionService->getSessionStats($chatWidget);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get widget statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve widget statistics.',
            ], 500);
        }
    }

    /**
     * Get widget analytics
     */
    public function analytics(ChatWidget $chatWidget, Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('widgets.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view analytics.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have widget context.',
                ], 404);
            }

            $dateRange = $request->input('range', '7d');
            $startDate = now()->subDays((int) filter_var($dateRange, FILTER_SANITIZE_NUMBER_INT));

            $analytics = [
                'total_sessions' => $chatWidget->sessions()
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'converted_sessions' => $chatWidget->sessions()
                    ->whereNotNull('customer_id')
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'messages_sent' => \App\Models\Tenant\Message::whereHas('conversation', function ($query) use ($chatWidget) {
                    $query->whereHas('customer', function ($q) use ($chatWidget) {
                        // Messages from widget conversations
                    });
                })->count(),
                'conversation_started' => $chatWidget->sessions()
                    ->whereNotNull('current_conversation_id')
                    ->where('created_at', '>=', $startDate)
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get widget analytics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve widget analytics.',
            ], 500);
        }
    }
}
