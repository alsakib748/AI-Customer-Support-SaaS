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
            ->select([
                'ticket_number',
                'subject',
                'status',
                'priority',
                'type',
                'assigned_user_id',
                'created_at',
                'resolved_at',
            ])
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