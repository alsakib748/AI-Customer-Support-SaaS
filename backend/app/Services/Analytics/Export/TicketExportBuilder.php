<?php
// app/Services/Analytics/Export/TicketExportBuilder.php

namespace App\Services\Analytics\Export;

use App\Support\Analytics\AnalyticsPeriod;

class TicketExportBuilder
{
    public function build(array $filters): array
    {
        $period = AnalyticsPeriod::fromRequest($filters);

        $rows = \App\Models\Tenant\Ticket::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw("
                COALESCE(ticket_number, '') as ticket_number,
                COALESCE(subject, '') as subject,
                COALESCE(status, '') as status,
                COALESCE(priority, '') as priority,
                COALESCE(type, '') as type,
                COALESCE(assigned_user_id::text, '') as assigned_user_id,
                TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI:SS') as created_at,
                COALESCE(TO_CHAR(resolved_at, 'YYYY-MM-DD HH24:MI:SS'), '') as resolved_at
            ")
            ->orderBy('created_at')
            ->cursor();

        $header = [
            'Ticket Number',
            'Subject',
            'Status',
            'Priority',
            'Type',
            'Assigned User',
            'Created At',
            'Resolved At',
        ];

        return [$header, $rows];
    }
}
