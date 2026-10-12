<?php

namespace Tests\Feature\Customer;

use App\Http\Controllers\Api\V1\Customer\CustomerController;
use App\Models\Tenant\Customer;
use App\Services\Billing\BillingLimitService;
use App\Services\Customer\CustomerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

/**
 * Verifies that every customer route resolves to a real, permitted controller
 * action, that search is portable, and that force-delete and CSV export work.
 */
class CustomerManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('tenant');

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('company_name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('status', 20)->default('active');
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            $table->string('default_language', 10)->default('en');
            $table->string('timezone')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->unsignedInteger('total_conversations')->default(0);
            $table->unsignedInteger('total_tickets')->default(0);
            $table->decimal('satisfaction_score', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function bindTenant(): void
    {
        $tenant = new \stdClass;
        $tenant->id = 'tenant-1';
        $this->app->instance('current_tenant', $tenant);
    }

    protected function makeService(): CustomerService
    {
        $this->bindTenant();

        return new CustomerService(
            Request::create('/', 'GET'),
            Mockery::mock(BillingLimitService::class),
        );
    }

    protected function makeController(): CustomerController
    {
        return new CustomerController($this->makeService());
    }

    public function test_customer_routes_resolve_to_existing_controller_methods(): void
    {
        $routes = [
            ['GET', 'api/v1/customers', 'index'],
            ['POST', 'api/v1/customers', 'store'],
            ['GET', 'api/v1/customers/statistics', 'statistics'],
            ['GET', 'api/v1/customers/tags', 'tags'],
            ['GET', 'api/v1/customers/export', 'export'],
            ['GET', 'api/v1/customers/{id}', 'show'],
            ['PUT', 'api/v1/customers/{id}', 'update'],
            ['DELETE', 'api/v1/customers/{id}', 'destroy'],
            ['POST', 'api/v1/customers/{id}/restore', 'restore'],
            ['DELETE', 'api/v1/customers/{id}/force', 'forceDelete'],
            ['POST', 'api/v1/customers/{id}/block', 'block'],
            ['POST', 'api/v1/customers/{id}/unblock', 'unblock'],
            ['POST', 'api/v1/customers/{id}/tags', 'addTag'],
            ['DELETE', 'api/v1/customers/{id}/tags/{tag}', 'removeTag'],
            ['POST', 'api/v1/customers/bulk-delete', 'bulkDelete'],
        ];

        $registered = collect(Route::getRoutes()->getRoutes());

        foreach ($routes as [$method, $uri, $action]) {
            $route = $registered->first(
                fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
            );

            $this->assertNotNull($route, "Expected route {$method} {$uri} to be registered.");
            $this->assertSame(
                CustomerController::class.'@'.$action,
                $route->getActionName(),
                "Route {$method} {$uri} must resolve to CustomerController@{$action}."
            );
            $this->assertTrue(
                method_exists(CustomerController::class, $action),
                "CustomerController@{$action} must exist."
            );
        }
    }

    public function test_customer_routes_are_permission_protected(): void
    {
        $expected = [
            ['GET', 'api/v1/customers', 'customers.view'],
            ['POST', 'api/v1/customers', 'customers.create'],
            ['GET', 'api/v1/customers/statistics', 'customers.view'],
            ['GET', 'api/v1/customers/tags', 'customers.view'],
            ['GET', 'api/v1/customers/export', 'customers.view'],
            ['GET', 'api/v1/customers/{id}', 'customers.view'],
            ['PUT', 'api/v1/customers/{id}', 'customers.update'],
            ['DELETE', 'api/v1/customers/{id}', 'customers.delete'],
            ['POST', 'api/v1/customers/{id}/restore', 'customers.delete'],
            ['DELETE', 'api/v1/customers/{id}/force', 'customers.delete'],
            ['POST', 'api/v1/customers/{id}/block', 'customers.update'],
            ['POST', 'api/v1/customers/{id}/unblock', 'customers.update'],
            ['POST', 'api/v1/customers/{id}/tags', 'customers.update'],
            ['DELETE', 'api/v1/customers/{id}/tags/{tag}', 'customers.update'],
            ['POST', 'api/v1/customers/bulk-delete', 'customers.delete'],
        ];

        $registered = collect(Route::getRoutes()->getRoutes());

        foreach ($expected as [$method, $uri, $permission]) {
            $route = $registered->first(
                fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
            );

            $this->assertNotNull($route, "Expected route {$method} {$uri} to be registered.");
            $this->assertContains(
                "permission:{$permission}",
                $route->middleware(),
                "Route {$method} {$uri} must be guarded by permission:{$permission}."
            );
        }
    }

    public function test_customer_search_is_case_insensitive(): void
    {
        Customer::create(['first_name' => 'Alice', 'last_name' => 'Nguyen']);
        Customer::create(['first_name' => 'Bob', 'last_name' => 'Stone']);

        $this->assertSame(1, Customer::search('ali')->count());
        $this->assertSame(1, Customer::search('ALICE')->count());
        $this->assertSame(0, Customer::search('zzz')->count());
    }

    public function test_force_delete_permanently_removes_a_soft_deleted_customer(): void
    {
        $customer = Customer::create(['first_name' => 'Trash']);
        $customer->delete();

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $response = $this->makeController()->forceDelete($customer->id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame(0, Customer::withTrashed()->where('id', $customer->id)->count());
    }

    public function test_export_streams_customers_as_csv(): void
    {
        Customer::create(['first_name' => 'Alice', 'last_name' => 'Nguyen', 'email' => 'alice@example.test']);

        $response = $this->makeController()->export(Request::create('/api/v1/customers/export', 'GET'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('first_name', $csv);
        $this->assertStringContainsString('Alice', $csv);
        $this->assertStringContainsString('alice@example.test', $csv);
    }

    public function test_index_does_not_leak_internal_exception_message(): void
    {
        $service = Mockery::mock(CustomerService::class);
        $service->shouldReceive('getCustomers')->andThrow(new \RuntimeException('SECRET DB DSN'));

        $response = (new CustomerController($service))->index(Request::create('/api/v1/customers', 'GET'));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringNotContainsString('SECRET', $response->getData(true)['message']);
    }
}
