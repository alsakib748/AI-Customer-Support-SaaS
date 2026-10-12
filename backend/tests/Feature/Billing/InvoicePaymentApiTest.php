<?php

namespace Tests\Feature\Billing;

use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithBillingDomain;
use Tests\Support\InteractsWithCentralSchema;
use Tests\Support\InteractsWithTenancy;
use Tests\TestCase;

/**
 * HTTP-level coverage of the invoice and payment read endpoints, including
 * cross-tenant isolation guarantees.
 */
class InvoicePaymentApiTest extends TestCase
{
    use InteractsWithBillingDomain;
    use InteractsWithCentralSchema;
    use InteractsWithTenancy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedTenantDatabase();

        config(['database.default' => 'central']);
        DB::purge('central');

        $this->createCentralSchema();
        $this->seedRbac();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantContext();
        parent::tearDown();
    }

    public function test_invoice_index_is_empty_for_a_new_tenant(): void
    {
        $this->bootBillingWorkspace();

        $this->getJson('/api/v1/invoices')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_invoice_index_only_returns_the_current_tenants_invoices(): void
    {
        [$tenant] = $this->bootBillingWorkspace();
        $other = $this->newTenant();

        $this->seedInvoice($tenant, ['invoice_number' => 'INV-MINE']);
        $this->seedInvoice($other, ['invoice_number' => 'INV-OTHER']);

        $this->getJson('/api/v1/invoices')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_number', 'INV-MINE')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_invoice_show_returns_an_invoice_owned_by_the_tenant(): void
    {
        [$tenant] = $this->bootBillingWorkspace();
        $invoice = $this->seedInvoice($tenant, ['invoice_number' => 'INV-100']);

        $this->getJson('/api/v1/invoices/'.$invoice->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice_number', 'INV-100')
            ->assertJsonPath('data.total', 29)
            ->assertJsonPath('data.is_paid', false);
    }

    public function test_invoice_show_hides_another_tenants_invoice(): void
    {
        $this->bootBillingWorkspace();
        $other = $this->newTenant();
        $invoice = $this->seedInvoice($other);

        $this->getJson('/api/v1/invoices/'.$invoice->id)
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_invoice_statistics_summarise_the_tenants_invoices(): void
    {
        [$tenant] = $this->bootBillingWorkspace();

        $this->seedInvoice($tenant, ['status' => 'open', 'total' => 29.00]);
        $this->seedInvoice($tenant, ['status' => 'paid', 'total' => 49.00, 'paid_at' => now()]);
        $this->seedInvoice($this->newTenant());

        $this->getJson('/api/v1/invoices/statistics')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.paid', 1)
            ->assertJsonPath('data.open', 1)
            ->assertJsonStructure(['data' => ['total', 'paid', 'open', 'overdue', 'total_amount', 'outstanding_amount']]);
    }

    public function test_invoice_show_returns_404_for_a_missing_invoice(): void
    {
        $this->bootBillingWorkspace();

        $this->getJson('/api/v1/invoices/999999')
            ->assertStatus(404);
    }

    public function test_invoice_download_rejects_another_tenants_invoice(): void
    {
        $this->bootBillingWorkspace();
        $other = $this->newTenant();
        $invoice = $this->seedInvoice($other);

        $this->getJson('/api/v1/invoices/'.$invoice->id.'/download')
            ->assertStatus(404);
    }

    public function test_invoice_download_redirects_to_the_provider_pdf_when_present(): void
    {
        [$tenant] = $this->bootBillingWorkspace();
        $invoice = $this->seedInvoice($tenant, [
            'invoice_pdf_url' => 'https://pay.example.test/invoices/'.$tenant->id.'.pdf',
        ]);

        $this->get('/api/v1/invoices/'.$invoice->id.'/download')
            ->assertStatus(302)
            ->assertRedirect('https://pay.example.test/invoices/'.$tenant->id.'.pdf');
    }

    public function test_payment_index_is_empty_for_a_new_tenant(): void
    {
        $this->bootBillingWorkspace();

        $this->getJson('/api/v1/payments')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_payment_index_only_returns_the_current_tenants_payments(): void
    {
        [$tenant] = $this->bootBillingWorkspace();
        $other = $this->newTenant();

        $this->seedPayment($tenant, ['payment_id' => 'pay_mine']);
        $this->seedPayment($other, ['payment_id' => 'pay_other']);

        $this->getJson('/api/v1/payments')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payment_id', 'pay_mine')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_payment_index_filters_by_provider_and_status(): void
    {
        [$tenant] = $this->bootBillingWorkspace();

        $this->seedPayment($tenant, ['payment_id' => 'pay_stripe_ok', 'provider' => 'stripe', 'status' => 'completed']);
        $this->seedPayment($tenant, ['payment_id' => 'pay_paypal_ok', 'provider' => 'paypal', 'status' => 'completed']);
        $this->seedPayment($tenant, ['payment_id' => 'pay_stripe_failed', 'provider' => 'stripe', 'status' => 'failed']);

        $this->getJson('/api/v1/payments?provider=paypal')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payment_id', 'pay_paypal_ok');

        $this->getJson('/api/v1/payments?status=failed')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.payment_id', 'pay_stripe_failed');
    }

    public function test_payment_statistics_are_scoped_to_the_tenant(): void
    {
        [$tenant] = $this->bootBillingWorkspace();
        $other = $this->newTenant();

        $this->seedPayment($tenant, ['amount' => 29, 'status' => 'completed']);
        $this->seedPayment($tenant, ['amount' => 10, 'status' => 'failed']);
        $this->seedPayment($other, ['amount' => 500, 'status' => 'completed']);

        $this->getJson('/api/v1/payments/statistics')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.failed', 1)
            ->assertJsonStructure(['data' => ['total', 'completed', 'failed', 'refunded', 'total_revenue', 'total_refunded', 'by_provider']]);
    }
}
