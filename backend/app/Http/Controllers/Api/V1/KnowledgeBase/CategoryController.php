<?php

namespace App\Http\Controllers\Api\V1\KnowledgeBase;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBase\StoreCategoryRequest;
use App\Http\Requests\KnowledgeBase\UpdateCategoryRequest;
use App\Http\Resources\KnowledgeBase\CategoryCollection;
use App\Http\Resources\KnowledgeBase\CategoryResource;
use App\Services\KnowledgeBase\KnowledgeBaseCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    protected KnowledgeBaseCategoryService $service;

    public function __construct(KnowledgeBaseCategoryService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of categories
     */
    public function index(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Super Admin: No tenant context required.',
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                        'last_page' => 1,
                    ],
                ]);
            }

            $filters = $request->only(['search', 'status', 'sort', 'direction', 'per_page']);
            $categories = $this->service->getCategories($filters);

            return new CategoryCollection($categories);

        } catch (\Exception $e) {
            Log::error('Failed to get categories:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories.',
            ], 500);
        }
    }

    /**
     * Get all categories (for dropdown)
     */
    public function all(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $categories = $this->service->getAllCategories();

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get all categories:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve categories.',
            ], 500);
        }
    }

    /**
     * Get a single category
     */
    public function show(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have category context.',
                ], 404);
            }

            $category = $this->service->getCategory($id);

            return new CategoryResource($category);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get category:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve category.',
            ], 500);
        }
    }

    /**
     * Create a new category
     */
    public function store(StoreCategoryRequest $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.create')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to create categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot create categories without tenant context.',
                ], 400);
            }

            $category = $this->service->createCategory($request->validated());

            return (new CategoryResource($category))
                ->additional([
                    'message' => 'Category created successfully 🎉',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to create category:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create category.',
            ], 500);
        }
    }

    /**
     * Update a category
     */
    public function update(UpdateCategoryRequest $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to update categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot update categories without tenant context.',
                ], 400);
            }

            $category = $this->service->getCategory($id);
            $category = $this->service->updateCategory($category, $request->validated());

            return (new CategoryResource($category))
                ->additional([
                    'message' => 'Category updated successfully 🎉',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to update category:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update category.',
            ], 500);
        }
    }

    /**
     * Delete a category
     */
    public function destroy(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to delete categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot delete categories without tenant context.',
                ], 400);
            }

            $category = $this->service->getCategory($id);
            $this->service->deleteCategory($category);

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to delete category:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category.',
            ], 500);
        }
    }

    /**
     * Activate a category
     */
    public function activate(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to activate categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot activate categories without tenant context.',
                ], 400);
            }

            $category = $this->service->getCategory($id);
            $category = $this->service->activateCategory($category);

            return (new CategoryResource($category))
                ->additional([
                    'message' => 'Category activated successfully ✅',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to activate category:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to activate category.',
            ], 500);
        }
    }

    /**
     * Deactivate a category
     */
    public function deactivate(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to deactivate categories.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot deactivate categories without tenant context.',
                ], 400);
            }

            $category = $this->service->getCategory($id);
            $category = $this->service->deactivateCategory($category);

            return (new CategoryResource($category))
                ->additional([
                    'message' => 'Category deactivated successfully 🔒',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to deactivate category:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate category.',
            ], 500);
        }
    }
}