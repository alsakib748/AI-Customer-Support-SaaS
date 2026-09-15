<?php
// app/Services/Analytics/Export/CustomerExportBuilder.php

namespace App\Services\Analytics\Export;

use App\Models\Tenant\Customer;
use App\Support\Analytics\AnalyticsPeriod;

class CustomerExportBuilder
{
    public function build(array $filters): array
    {
        $period = AnalyticsPeriod::fromRequest($filters);

        $rows = Customer::query()
            ->whereBetween('created_at', [$period->from, $period->to])
            ->selectRaw("
                id,
                COALESCE(first_name, '') as first_name,
                COALESCE(last_name, '') as last_name,
                COALESCE(email, '') as email,
                COALESCE(phone, '') as phone,
                COALESCE(company_name, '') as company_name,
                COALESCE(status, '') as status,
                COALESCE(total_conversations, 0) as total_conversations,
                COALESCE(total_tickets, 0) as total_tickets,
                COALESCE(satisfaction_score::text, '') as satisfaction_score,
                TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI:SS') as created_at
            ")
            ->orderBy('created_at')
            ->cursor();

        $header = [
            'ID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'Company',
            'Status',
            'Total Conversations',
            'Total Tickets',
            'Satisfaction',
            'Created At',
        ];

        return [$header, $rows];
    }
}