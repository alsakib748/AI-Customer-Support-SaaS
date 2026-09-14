<?php

namespace App\Jobs\Analytics;

use App\Models\Tenant\AnalyticsExport;
use App\Services\Analytics\AnalyticsExportService;
use App\Services\Analytics\Export\AgentExportBuilder;
use App\Services\Analytics\Export\TicketExportBuilder;
use App\Support\Analytics\ExportType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateAnalyticsExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $exportId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsExportService $service): void
    {
        $export = AnalyticsExport::find($this->exportId);
        if (!$export) {
            return;
        }

        try {
            $export->update(['status' => 'processing']);

            [$header, $rows] = $this->resolveBuilder($export->type)
                ->build($export->filters ?? []);

            $temp = fopen('php://temp', 'r+');
            fputcsv($temp, $header);
            $rowCount = 0;
            foreach ($rows as $row) {
                fputcsv($temp, is_array($row) ? $row : (array) $row);
                $rowCount++;
            }
            rewind($temp);
            $content = stream_get_contents($temp);
            fclose($temp);

            $service->storeCsv($export, $content, $rowCount);

            Log::info('Analytics export completed', [
                'export_id' => $export->id,
                'row_count' => $rowCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('Analytics export failed', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
            ]);
            $service->markFailed($export, $e->getMessage());
            throw $e;
        }
    }

    protected function resolveBuilder(string $type)
    {
        return match ($type) {
            ExportType::CONVERSATIONS->value => new ConversationExportBuilder(),
            ExportType::TICKETS->value => new TicketExportBuilder(),
            ExportType::AGENTS->value => new AgentExportBuilder(),
            default => throw new \RuntimeException('Unsupported export type: ' . $type),
        };
    }
}