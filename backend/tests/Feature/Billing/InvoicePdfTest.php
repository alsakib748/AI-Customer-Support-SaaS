<?php

namespace Tests\Feature\Billing;

use App\Http\Controllers\Api\V1\Billing\InvoiceController;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\InvoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteractsWithCentralSchema;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use InteractsWithCentralSchema;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'central']);
        config(['tenancy.database.central_connection' => 'central']);
        config(['cache.default' => 'array']);
        DB::purge('central');

        $this->createCentralSchema();
    }

    protected function tenant(string $slug): Tenant
    {
        return Tenant::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'Acme '.$slug,
            'slug' => $slug,
            'status' => 'active',
        ]);
    }

    protected function invoiceFor(Tenant $tenant): Invoice
    {
        $plan = $this->createDefaultPlan(['name' => 'Pro', 'price_monthly' => 29]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
        ]);

        return Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => 'INV-'.date('Ym').'-000001',
            'subtotal' => 29.00,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 29.00,
            'currency' => 'USD',
            'status' => 'open',
            'due_at' => now()->addDays(7),
            'line_items' => [
                ['description' => 'Pro — Monthly', 'quantity' => 1, 'unit_price' => 29.00, 'total' => 29.00],
            ],
        ]);
    }

    public function test_render_pdf_returns_a_pdf_document(): void
    {
        $tenant  = $this->tenant('acme');
        $invoice = $this->invoiceFor($tenant);

        $pdf = app(InvoiceService::class)->renderPdf($invoice);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_download_streams_pdf_for_current_tenant(): void
    {
        $tenant  = $this->tenant('acme');
        $invoice = $this->invoiceFor($tenant);

        app()->instance('current_tenant', $tenant);

        $response = app(InvoiceController::class)->download($invoice->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_download_rejects_invoice_of_other_tenant(): void
    {
        $owner   = $this->tenant('owner');
        $other   = $this->tenant('other');
        $invoice = $this->invoiceFor($owner);

        app()->instance('current_tenant', $other);

        $response = app(InvoiceController::class)->download($invoice->id);

        $this->assertSame(404, $response->getStatusCode());
    }
}
