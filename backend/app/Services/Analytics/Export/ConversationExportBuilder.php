<?php
// app/Services/Analytics/Export/ConversationExportBuilder.php

namespace App\Services\Analytics\Export;

use App\Support\Analytics\AnalyticsPeriod;

class ConversationExportBuilder
{
    public function build(array $filters): array
    {
        $period = AnalyticsPeriod::fromRequest($filters);

        $rows = \App\Models\Tenant\Conversation::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw("
                id,
                COALESCE(subject, '') as subject,
                COALESCE(channel, '') as channel,
                COALESCE(status, '') as status,
                COALESCE(priority, '') as priority,
                COALESCE(assigned_user_id::text, '') as assigned_user_id,
                TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI:SS') as created_at,
                COALESCE(TO_CHAR(resolved_at, 'YYYY-MM-DD HH24:MI:SS'), '') as resolved_at,
                COALESCE(TO_CHAR(closed_at, 'YYYY-MM-DD HH24:MI:SS'), '') as closed_at
            ")
            ->orderBy('created_at')
            ->cursor();

        $header = [
            'ID',
            'Subject',
            'Channel',
            'Status',
            'Priority',
            'Assigned User',
            'Created At',
            'Resolved At',
            'Closed At',
        ];

        return [$header, $rows];
    }
}