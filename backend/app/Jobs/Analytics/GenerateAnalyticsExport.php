<?php

namespace App\Jobs\Analytics;

use App\Models\Tenant\AnalyticsExport;
use App\Services\Analytics\AnalyticsExportService;
use App\Services\Analytics\Export\AgentExportBuilder;
use App\Services\Analytics\Export\AIUsageExportBuilder;
use App\Services\Analytics\Export\ConversationExportBuilder;
use App\Services\Analytics\Export\CustomerExportBuilder;
use App\Services\Analytics\Export\TicketExportBuilder;
use App\Services\Analytics\Export\WidgetExportBuilder;
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
                //  Convert row to a flat array of scalars
                $flat = $this->flattenRow($row);

                // Skip completely empty rows
                if (empty(array_filter($flat, fn($v) => $v !== null && $v !== ''))) {
                    continue;
                }

                fputcsv($temp, $flat);
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

            // Notify user
            try {
                $user = \App\Models\User::find($export->user_id);
                if ($user) {
                    $user->notify(new \App\Notifications\AnalyticsExportCompleted($export));
                }
            } catch (\Throwable $e) {
                Log::warning('Export notification failed', [
                    'export_id' => $export->id,
                    'error' => $e->getMessage(),
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('Analytics export failed', [
                'export_id' => $export->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $service->markFailed($export, $e->getMessage());
            throw $e;
        }
    }

    /**
     * ✅ Convert any row (Eloquent model, array, collection) to a flat array.
     */
    protected function flattenRow($row): array
    {
        // Eloquent model → get attributes only
        if ($row instanceof \Illuminate\Database\Eloquent\Model) {
            $data = $row->attributesToArray();
        } elseif (is_object($row)) {
            $data = (array) $row;
        } else {
            $data = (array) $row;
        }

        return array_map(function ($value) {
            // Null → empty string
            if ($value === null)
                return '';

            // Bool → 1/0
            if (is_bool($value))
                return $value ? '1' : '0';

            // Carbon/DateTime → ISO
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            // Nested array/object → JSON encode (shouldn't happen but safe)
            if (is_array($value) || is_object($value)) {
                return json_encode($value);
            }

            return (string) $value;
        }, $data);
    }

    protected function resolveBuilder(string $type)
    {
        return match ($type) {
            ExportType::CONVERSATIONS->value => new ConversationExportBuilder(),
            ExportType::CUSTOMERS->value => new CustomerExportBuilder(),
            ExportType::TICKETS->value => new TicketExportBuilder(),
            ExportType::AGENTS->value => new AgentExportBuilder(),
            ExportType::AI->value => new AIUsageExportBuilder(),
            ExportType::WIDGET->value => new WidgetExportBuilder(),
            default => throw new \RuntimeException('Unsupported export type: ' . $type),
        };
    }
}
