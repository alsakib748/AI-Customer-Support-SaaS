<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\ExportRequest;
use App\Models\Tenant\AnalyticsExport;
use App\Services\Analytics\AnalyticsExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnalyticsExportController extends Controller
{
    public function __construct(
        protected AnalyticsExportService $service
    ) {
    }

    /**
     * Queue a new export.
     */
    public function store(ExportRequest $request)
    {
        if (!auth()->user()->hasPermissionTo('analytics.export')) {
            abort(403, 'You do not have permission to export analytics.');
        }

        $export = $this->service->queue($request->validated(), auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Export queued. You will be notified when ready.',
            'data' => $export,
        ], 202);
    }

    /**
     * List user exports.
     */
    public function index()
    {
        $exports = AnalyticsExport::where('user_id', auth()->id())
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $exports,
        ]);
    }

    /**
     * Download an export.
     */
    public function download(AnalyticsExport $export)
    {
        if (
            $export->user_id !== auth()->id()
            && !auth()->user()->hasPermissionTo('analytics.export')
        ) {
            abort(403);
        }

        if (!$export->is_ready) {
            abort(404, 'Export is not ready yet.');
        }

        return Storage::disk(AnalyticsExportService::DISK)
            ->download($export->file_path, $export->file_name);
    }
}