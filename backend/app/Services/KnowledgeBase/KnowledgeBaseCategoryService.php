<?php
// app/Services/KnowledgeBase/KnowledgeBaseCategoryService.php

namespace App\Services\KnowledgeBase;

use App\Models\Tenant\KnowledgeBaseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KnowledgeBaseCategoryService
{
    /**
     * Get paginated categories with filters
     */
    public function getCategories(array $filters = []): LengthAwarePaginator
    {
        $query = KnowledgeBaseCategory::query();

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'sort_order';
        $sortDirection = $filters['direction'] ?? 'asc';

        $allowedSorts = ['name', 'created_at', 'sort_order', 'status'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('sort_order', 'asc');
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    /**
     * Get all categories (for dropdown)
     */
    public function getAllCategories(): array
    {
        return KnowledgeBaseCategory::active()
            ->ordered()
            ->get()
            ->toArray();
    }

    /**
     * Get a single category
     */
    public function getCategory(int $id): KnowledgeBaseCategory
    {
        return KnowledgeBaseCategory::findOrFail($id);
    }

    /**
     * Create a new category
     */
    public function createCategory(array $data): KnowledgeBaseCategory
    {
        $category = KnowledgeBaseCategory::create($data);

        Log::info('Knowledge base category created', [
            'category_id' => $category->id,
            'user_id' => auth()->id(),
        ]);

        return $category;
    }

    /**
     * Update a category
     */
    public function updateCategory(KnowledgeBaseCategory $category, array $data): KnowledgeBaseCategory
    {
        $category->update($data);

        Log::info('Knowledge base category updated', [
            'category_id' => $category->id,
            'user_id' => auth()->id(),
        ]);

        return $category->fresh();
    }

    /**
     * Delete a category
     */
    public function deleteCategory(KnowledgeBaseCategory $category): bool
    {
        // Check if category has articles
        if ($category->articles()->count() > 0) {
            throw ValidationException::withMessages([
                'category' => ['Cannot delete category with articles. Move or delete the articles first.'],
            ]);
        }

        $category->delete();

        Log::info('Knowledge base category deleted', [
            'category_id' => $category->id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Activate a category
     */
    public function activateCategory(KnowledgeBaseCategory $category): KnowledgeBaseCategory
    {
        $category->activate();

        Log::info('Knowledge base category activated', [
            'category_id' => $category->id,
            'user_id' => auth()->id(),
        ]);

        return $category->fresh();
    }

    /**
     * Deactivate a category
     */
    public function deactivateCategory(KnowledgeBaseCategory $category): KnowledgeBaseCategory
    {
        $category->deactivate();

        Log::info('Knowledge base category deactivated', [
            'category_id' => $category->id,
            'user_id' => auth()->id(),
        ]);

        return $category->fresh();
    }
}