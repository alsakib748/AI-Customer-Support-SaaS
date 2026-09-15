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

    /**
     * Delete an export file from disk.
     */
    public function deleteFile(AnalyticsExport $export): void
    {
        if ($export->file_path && Storage::disk(self::DISK)->exists($export->file_path)) {
            Storage::disk(self::DISK)->delete($export->file_path);
        }
    }

    /**
     * Stream a small CSV export immediately (no queue).
     */
    public function streamNow(array $filters, int $userId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        [$header, $rows] = match ($filters['type']) {
            'conversations' => (new \App\Services\Analytics\Export\ConversationExportBuilder())->build($filters),
            'customers' => (new \App\Services\Analytics\Export\CustomerExportBuilder())->build($filters),
            'tickets' => (new \App\Services\Analytics\Export\TicketExportBuilder())->build($filters),
            'agents' => (new \App\Services\Analytics\Export\AgentExportBuilder())->build($filters),
            'ai' => (new \App\Services\Analytics\Export\AIUsageExportBuilder())->build($filters),
            'widget' => (new \App\Services\Analytics\Export\WidgetExportBuilder())->build($filters),
            default => throw new \RuntimeException('Unsupported export type.'),
        };

        $fileName = 'analytics-' . $filters['type'] . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);

            foreach ($rows as $row) {
                if ($row instanceof \Illuminate\Database\Eloquent\Model) {
                    $row = $row->attributesToArray();
                } else {
                    $row = (array) $row;
                }

                $flat = array_map(function ($v) {
                    if ($v === null)
                        return '';
                    if (is_bool($v))
                        return $v ? '1' : '0';
                    if ($v instanceof \DateTimeInterface)
                        return $v->format('Y-m-d H:i:s');
                    if (is_array($v) || is_object($v))
                        return json_encode($v);
                    return (string) $v;
                }, $row);

                fputcsv($out, $flat);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
    }
}