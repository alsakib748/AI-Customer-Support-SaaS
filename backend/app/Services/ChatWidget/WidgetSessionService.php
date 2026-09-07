<?php
// app/Services/ChatWidget/WidgetSessionService.php

namespace App\Services\ChatWidget;

use App\Models\Tenant\WidgetSession;
use App\Models\Tenant\ChatWidget;
use Illuminate\Support\Facades\Log;

class WidgetSessionService
{
    /**
     * Get session by token
     */
    public function getSessionByToken(string $token): ?WidgetSession
    {
        return WidgetSession::bySessionToken($token)->first();
    }

    /**
     * Get active sessions for widget
     */
    public function getActiveSessions(ChatWidget $widget, int $limit = 100)
    {
        return $widget->sessions()
            ->where('last_seen_at', '>=', now()->subMinutes(30))
            ->orderBy('last_seen_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get session count for widget
     */
    public function getSessionStats(ChatWidget $widget): array
    {
        $total = $widget->sessions()->count();
        $active = $widget->sessions()
            ->where('last_seen_at', '>=', now()->subMinutes(30))
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'with_customer' => $widget->sessions()
                ->whereNotNull('customer_id')
                ->count(),
            'with_conversation' => $widget->sessions()
                ->whereNotNull('current_conversation_id')
                ->count(),
        ];
    }

    /**
     * Cleanup expired sessions with intelligent rules
     */
    public function cleanupExpiredSessions(): array
    {
        $results = [
            'deleted' => 0,
            'kept' => 0,
            'details' => [],
        ];

        DB::transaction(function () use (&$results) {
            // 1. Delete sessions without any activity for 30 days
            $deleted = WidgetSession::where(function ($query) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', now()->subDays(30));
            })->delete();

            $results['deleted'] += $deleted;
            $results['details']['no_activity_30_days'] = $deleted;

            // 2. Delete sessions without conversation for 7 days
            $deleted = WidgetSession::whereNull('current_conversation_id')
                ->where('created_at', '<', now()->subDays(7))
                ->delete();

            $results['deleted'] += $deleted;
            $results['details']['no_conversation_7_days'] = $deleted;

            // 3. Delete sessions with resolved conversations (older than 14 days)
            $deleted = WidgetSession::whereNotNull('current_conversation_id')
                ->whereHas('currentConversation', function ($query) {
                    $query->where('status', 'resolved');
                })
                ->where('created_at', '<', now()->subDays(14))
                ->delete();

            $results['deleted'] += $deleted;
            $results['details']['resolved_old'] = $deleted;

            // 4. Delete sessions with closed conversations (older than 7 days)
            $deleted = WidgetSession::whereNotNull('current_conversation_id')
                ->whereHas('currentConversation', function ($query) {
                    $query->where('status', 'closed');
                })
                ->where('created_at', '<', now()->subDays(7))
                ->delete();

            $results['deleted'] += $deleted;
            $results['details']['closed_old'] = $deleted;
        });

        Log::info('Widget sessions cleaned up', $results);

        return $results;
    }

    /**
     * Soft cleanup - mark sessions as expired instead of deleting
     */
    public function softCleanupExpiredSessions(): array
    {
        $results = [
            'updated' => 0,
            'details' => [],
        ];

        // Instead of deleting, we could add an 'expired' status
        // But since we don't have a status column, we can update metadata
        $updated = WidgetSession::where('last_seen_at', '<', now()->subDays(30))
            ->update([
                'metadata' => DB::raw('jsonb_set(metadata, \'{"expired"}\', \'true\')'),
            ]);

        $results['updated'] = $updated;
        $results['details']['marked_expired'] = $updated;

        Log::info('Widget sessions marked as expired', $results);

        return $results;
    }

    /**
     * Get cleanup statistics
     */
    public function getCleanupStats(): array
    {
        return [
            'total_sessions' => WidgetSession::count(),
            'active' => WidgetSession::where('last_seen_at', '>=', now()->subMinutes(30))->count(),
            'inactive_1_day' => WidgetSession::where('last_seen_at', '<', now()->subDay())->count(),
            'inactive_7_days' => WidgetSession::where('last_seen_at', '<', now()->subDays(7))->count(),
            'inactive_30_days' => WidgetSession::where('last_seen_at', '<', now()->subDays(30))->count(),
            'no_customer' => WidgetSession::whereNull('customer_id')->count(),
            'no_conversation' => WidgetSession::whereNull('current_conversation_id')->count(),
        ];
    }

    /**
     * Archive old sessions (move to archive table)
     */
    public function archiveOldSessions(): array
    {
        // This would move sessions to a separate archive table
        // Implement if you need to keep data for compliance
        return [];
    }

}
