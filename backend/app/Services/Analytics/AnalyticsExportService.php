<?php
// app/Services/Analytics/AnalyticsExportService.php

namespace App\Services\Analytics;

use App\Models\Tenant\AnalyticsExport;
use App\Support\Analytics\ExportType;
use Illuminate\Support\Facades\Storage;

class AnalyticsExportService
{
    public const DISK = 'local';

    public const DIRECTORY = 'analytics-exports';

    /**
     * Create an export record and dispatch job.
     */
    public function queue(array $filters, int $userId): AnalyticsExport
    {
        $export = AnalyticsExport::create([
            'user_id' => $userId,
            'type' => $filters['type'],
            'format' => 'csv',
            'status' => 'pending',
            'filters' => $filters,
        ]);

        \App\Jobs\Analytics\GenerateAnalyticsExport::dispatch($export->id);

        return $export;
    }

    /**
     * Persist CSV content as file.
     */
    public function storeCsv(AnalyticsExport $export, string $content, int $rowCount): void
    {
        $fileName = sprintf(
            'analytics-%s-%s.csv',
            $export->type,
            now()->format('Ymd_His')
        );
        $path = self::DIRECTORY . '/' . $export->id . '/' . $fileName;

        Storage::disk(self::DISK)->put($path, $content);

        $export->update([
            'status' => 'completed',
            'file_path' => $path,
            'file_name' => $fileName,
            'row_count' => $rowCount,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(AnalyticsExport $export, string $error): void
    {
        $export->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}