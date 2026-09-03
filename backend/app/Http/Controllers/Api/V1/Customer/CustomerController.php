<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\Customer\CustomerCollection;
use App\Http\Resources\Customer\CustomerResource;
use App\Services\Customer\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{

    protected CustomerService $service;

    public function __construct(CustomerService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of customers
     */
    public function index(Request $request)
    {
        // dd('working');

        try {
            // Check permission (using Spatie)
            // if (!auth()->user()->hasPermissionTo('customers.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view customers.',
            //     ], 403);
            // }

            $filters = $request->only([
                'search',
                'status',
                'tag',
                'date_from',
                'date_to',
                'sort',
                'direction',
                'per_page',
            ]);

            $customers = $this->service->getCustomers($filters);

            return new CustomerCollection($customers);

        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to get customers:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customers.',
            ], 500);
        }
    }

    /**
     * Get a single customer
     */
    public function show($id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view customers.',
            //     ], 403);
            // }

            $customer = $this->service->getCustomer($id);

            return new CustomerResource($customer);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get customer:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer.',
            ], 500);
        }
    }

    /**
     * Create a new customer
     */
    public function store(StoreCustomerRequest $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.create')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to create customers.',
            //     ], 403);
            // }

            $customer = $this->service->createCustomer($request->validated());

            return (new CustomerResource($customer))
                ->additional([
                    'message' => 'Customer created successfully.',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to create customer:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer.',
            ], 500);
        }
    }

    /**
     * Update a customer
     */
    public function update(UpdateCustomerRequest $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to update customers.',
            //     ], 403);
            // }

            $customer = $this->service->updateCustomer($id, $request->validated());

            return (new CustomerResource($customer))
                ->additional([
                    'message' => 'Customer updated successfully 🎉',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to update customer:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer.',
            ], 500);
        }
    }

    /**
     * Delete a customer (soft delete)
     */
    public function destroy($id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to delete customers.',
            //     ], 403);
            // }

            $this->service->deleteCustomer($id);

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to delete customer:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer.',
            ], 500);
        }
    }

    /**
     * Restore a deleted customer
     */
    public function restore($id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to restore customers.',
            //     ], 403);
            // }

            $customer = $this->service->restoreCustomer($id);

            return (new CustomerResource($customer))
                ->additional([
                    'message' => 'Customer restored successfully.',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to restore customer:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore customer.',
            ], 500);
        }
    }

    /**
     * Block a customer
     */
    public function block(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to block customers.',
            //     ], 403);
            // }

            $request->validate([
                'reason' => 'nullable|string|max:500',
            ]);

            $customer = $this->service->blockCustomer($id, $request->reason);

            return (new CustomerResource($customer))
                ->additional([
                    'message' => 'Customer blocked successfully.',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to block customer:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to block customer.',
            ], 500);
        }
    }

    /**
     * Unblock a customer
     */
    public function unblock($id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to unblock customers.',
            //     ], 403);
            // }

            $customer = $this->service->unblockCustomer($id);

            return (new CustomerResource($customer))
                ->additional([
                    'message' => 'Customer unblocked successfully.',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to unblock customer:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unblock customer.',
            ], 500);
        }
    }

    /**
     * Add a tag to a customer
     */
    public function addTag(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to manage tags.',
            //     ], 403);
            // }

            $request->validate([
                'tag' => 'required|string|max:50',
            ]);

            $customer = $this->service->addTag($id, $request->tag);

            return (new CustomerResource($customer))
                ->additional([
                    'message' => 'Tag added successfully.',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to add tag:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add tag.',
            ], 500);
        }
    }

    /**
     * Remove a tag from a customer
     */
    public function removeTag(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to manage tags.',
            //     ], 403);
            // }

            $request->validate([
                'tag' => 'required|string|max:50',
            ]);

            $customer = $this->service->removeTag($id, $request->tag);

            return (new CustomerResource($customer))
                ->additional([
                    'message' => 'Tag removed successfully.',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to remove tag:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove tag.',
            ], 500);
        }
    }

    /**
     * Bulk delete customers
     */
    public function bulkDelete(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to delete customers.',
            //     ], 403);
            // }

            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'integer|exists:customers,id',
            ]);

            $count = $this->service->bulkDelete($request->ids);

            return response()->json([
                'success' => true,
                'message' => "{$count} customers deleted successfully.",
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to bulk delete customers:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customers.',
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function statistics(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view statistics.',
            //     ], 403);
            // }

            $statistics = $this->service->getStatistics();

            // dd($statistics);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get customer statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer statistics.',
            ], 500);
        }
    }

    /**
     * Get all tags
     */
    public function tags(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('customers.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view tags.',
            //     ], 403);
            // }

            // dd('working');

            $tags = $this->service->getTags();

            return response()->json([
                'success' => true,
                'data' => $tags,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tags:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tags.',
            ], 500);
        }
    }

}