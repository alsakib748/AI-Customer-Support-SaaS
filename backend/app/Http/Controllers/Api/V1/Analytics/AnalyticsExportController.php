<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\ExportRequest;
use App\Models\Tenant\AnalyticsExport;
use App\Services\Analytics\AnalyticsExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        // if (!auth()->user()->hasPermissionTo('analytics.export')) {
        //     abort(403, 'You do not have permission to export analytics.');
        // }

        if (!tenant()) {
            return response()->json([
                'success' => false,
                'message' => 'Workspace context is required to create exports.',
            ], 400);
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
        // if (!auth()->user()->hasPermissionTo('analytics.export')) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'You do not have permission to view exports.',
        //     ], 403);
        // }

        // Guard against missing tenant context (Super Admin etc.)
        if (!tenant()) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

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
    // public function download(AnalyticsExport $export)
    // {
    //     // if (
    //     //     $export->user_id !== auth()->id()
    //     //     && !auth()->user()->hasPermissionTo('analytics.export')
    //     // ) {
    //     //     abort(403);
    //     // }

    //     if (!$export->is_ready) {
    //         abort(404, 'Export is not ready yet.');
    //     }

    //     return Storage::disk(AnalyticsExportService::DISK)
    //         ->download($export->file_path, $export->file_name);
    // }

    /**
     *  Download an export — no implicit route model binding.
     */
    public function download(int $exportId)
    {
        // Guard: tenant must be initialized
        if (!tenant()) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant context is required.',
            ], 400);
        }

        // Fetch inside the controller — tenancy is already booted here
        $export = AnalyticsExport::find($exportId);

        if (!$export) {
            return response()->json([
                'success' => false,
                'message' => 'Export not found.',
            ], 404);
        }

        if (
            $export->user_id !== auth()->id()
            && !auth()->user()->hasPermissionTo('analytics.export')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to download this export.',
            ], 403);
        }

        if (!$export->is_ready) {
            return response()->json([
                'success' => false,
                'message' => 'Export is not ready yet.',
            ], 404);
        }

        $disk = Storage::disk(AnalyticsExportService::DISK);

        if (!$disk->exists($export->file_path)) {
            Log::warning('Export file missing on disk', [
                'export_id' => $export->id,
                'file_path' => $export->file_path,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Export file is missing. Please re-run the export.',
            ], 404);
        }

        return $disk->download($export->file_path, $export->file_name);
    }

    public function downloadNow(ExportRequest $request)
    {
        if (!auth()->user()->hasPermissionTo('analytics.export')) {
            return response()->json(['success' => false, 'message' => 'Permission denied.'], 403);
        }

        if (!tenant()) {
            return response()->json(['success' => false, 'message' => 'Tenant context is required.'], 400);
        }

        return $this->service->streamNow($request->validated(), auth()->id());
    }
}