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
            ->select([
                'id',
                'subject',
                'channel',
                'status',
                'priority',
                'assigned_user_id',
                'created_at',
                'resolved_at',
                'closed_at',
            ])
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