<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Billing\InvoiceResource;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{


    protected InvoiceService $service;

    public function __construct(InvoiceService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of invoices
     */
    public function index(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('billing.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view invoices.',
            //     ], 403);
            // }

            $tenant = app('current_tenant');
            $filters = $request->only(['status', 'date_from', 'date_to', 'per_page']);

            $invoices = $this->service->getInvoices($tenant->id, $filters);

            return response()->json([
                'success' => true,
                'data' => InvoiceResource::collection($invoices->items()),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'last_page' => $invoices->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invoices:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoices.',
            ], 500);
        }
    }

    /**
     * Get a single invoice
     */
    public function show($id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('billing.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view invoices.',
            //     ], 403);
            // }

            $invoice = $this->service->getInvoice($id);

            // Verify invoice belongs to current tenant
            $tenant = app('current_tenant');
            if ($invoice->tenant_id !== $tenant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => new InvoiceResource($invoice),
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invoice.',
            ], 500);
        }
    }

    /**
     * Get invoice statistics
     */
    public function statistics(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('billing.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view statistics.',
            //     ], 403);
            // }

            $tenant = app('current_tenant');
            $statistics = $this->service->getStatistics($tenant->id);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get invoice statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics.',
            ], 500);
        }
    }

    /**
     * Download invoice PDF
     */
    public function download($id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('billing.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to download invoices.',
            //     ], 403);
            // }

            $invoice = $this->service->getInvoice($id);

            // Verify invoice belongs to current tenant
            $tenant = app('current_tenant');
            if ($invoice->tenant_id !== $tenant->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invoice not found.',
                ], 404);
            }

            // If PDF URL exists, redirect
            if ($invoice->invoice_pdf_url) {
                return redirect($invoice->invoice_pdf_url);
            }

            // Generate PDF (implement with a PDF library)
            return response()->json([
                'success' => false,
                'message' => 'PDF not available for this invoice.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to download invoice:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download invoice.',
            ], 500);
        }
    }

}