<?php
// app/Services/Analytics/Export/AIUsageExportBuilder.php

namespace App\Services\Analytics\Export;

use App\Models\Tenant\AIUsage;
use App\Support\Analytics\AnalyticsPeriod;

class AIUsageExportBuilder
{
    public function build(array $filters): array
    {
        $period = AnalyticsPeriod::fromRequest($filters);

        $query = AIUsage::query()
            ->whereBetween('created_at', [$period->from, $period->to]);

        if (!empty($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (!empty($filters['model'])) {
            $query->where('model', $filters['model']);
        }

        $rows = $query
            ->selectRaw("
                id,
                COALESCE(provider, '') as provider,
                COALESCE(model, '') as model,
                COALESCE(operation, '') as operation,
                COALESCE(input_tokens, 0) as input_tokens,
                COALESCE(output_tokens, 0) as output_tokens,
                COALESCE(total_tokens, 0) as total_tokens,
                COALESCE(estimated_cost, 0)::text as estimated_cost,
                TO_CHAR(created_at, 'YYYY-MM-DD HH24:MI:SS') as created_at
            ")
            ->orderBy('created_at')
            ->cursor();

        $header = [
            'ID',
            'Provider',
            'Model',
            'Operation',
            'Input Tokens',
            'Output Tokens',
            'Total Tokens',
            'Estimated Cost',
            'Created At',
        ];

        return [$header, $rows];
    }
}
