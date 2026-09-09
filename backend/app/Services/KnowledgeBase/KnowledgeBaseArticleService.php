<?php
// app/Services/KnowledgeBase/KnowledgeBaseArticleService.php

namespace App\Services\KnowledgeBase;

use App\Events\KnowledgeBase\ArticleArchived;
use App\Events\KnowledgeBase\ArticleCreated;
use App\Events\KnowledgeBase\ArticlePublished;
use App\Events\KnowledgeBase\ArticleUpdated;
use App\Helpers\SanitizationHelper;
use App\Models\Tenant\KnowledgeBaseArticle;
use App\Models\Tenant\KnowledgeBaseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KnowledgeBaseArticleService
{
    /**
     * Get paginated articles with filters
     */
    public function getArticles(array $filters = []): LengthAwarePaginator
    {
        $query = KnowledgeBaseArticle::query()
            ->with('category');

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Filter by category
        if (!empty($filters['category_id'])) {
            $query->byCategory($filters['category_id']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        // Filter by visibility
        if (!empty($filters['visibility'])) {
            $query->byVisibility($filters['visibility']);
        }

        // Filter by author
        if (!empty($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }

        // Date range
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Sorting
        $sortField = $filters['sort'] ?? 'created_at';
        $sortDirection = $filters['direction'] ?? 'desc';

        $allowedSorts = ['title', 'status', 'visibility', 'created_at', 'updated_at', 'published_at', 'sort_order'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage);
    }

    /**
     * Get articles for AI retrieval
     */
    public function getArticlesForAI(string $query): array
    {
        return KnowledgeBaseArticle::publishedAndEligible()
            ->where(function ($q) use ($query) {
                $q->where('title', 'ILIKE', "%{$query}%")
                    ->orWhere('excerpt', 'ILIKE', "%{$query}%")
                    ->orWhere('content', 'ILIKE', "%{$query}%");
            })
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Get a single article
     */
    public function getArticle(int $id): KnowledgeBaseArticle
    {
        return KnowledgeBaseArticle::with('category')
            ->findOrFail($id);
    }

    /**
     * Get article by slug
     */
    public function getArticleBySlug(string $slug): KnowledgeBaseArticle
    {
        return KnowledgeBaseArticle::with('category')
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Create a new article
     */
    public function createArticle(array $data): KnowledgeBaseArticle
    {
        // Sanitize content
        if (isset($data['content'])) {
            $data['content'] = SanitizationHelper::sanitizeHtml($data['content']);
        }

        $article = DB::transaction(function () use ($data) {
            $article = KnowledgeBaseArticle::create([
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'],
                'slug' => $data['slug'] ?? null,
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $data['content'],
                'status' => 'draft',
                'visibility' => $data['visibility'],
                'author_id' => auth()->id(),
                'sort_order' => $data['sort_order'] ?? 0,
                'metadata' => $data['metadata'] ?? null,
            ]);

            event(new ArticleCreated($article));

            return $article;
        });

        Log::info('Knowledge base article created', [
            'article_id' => $article->id,
            'user_id' => auth()->id(),
        ]);

        return $article;
    }

    /**
     * Update an article
     */
    public function updateArticle(KnowledgeBaseArticle $article, array $data): KnowledgeBaseArticle
    {
        if (isset($data['content'])) {
            $data['content'] = SanitizationHelper::sanitizeHtml($data['content']);
        }

        $article->update($data);

        event(new ArticleUpdated($article));

        Log::info('Knowledge base article updated', [
            'article_id' => $article->id,
            'user_id' => auth()->id(),
        ]);

        return $article->fresh();
    }

    /**
     * Delete an article (soft delete)
     */
    public function deleteArticle(KnowledgeBaseArticle $article): bool
    {
        $article->delete();

        Log::info('Knowledge base article deleted', [
            'article_id' => $article->id,
            'user_id' => auth()->id(),
        ]);

        return true;
    }

    /**
     * Publish an article
     */
    public function publishArticle(KnowledgeBaseArticle $article): KnowledgeBaseArticle
    {
        if (!$article->canTransitionTo('published')) {
            throw ValidationException::withMessages([
                'status' => ['Article cannot be published in its current state.'],
            ]);
        }

        $article->publish();

        event(new ArticlePublished($article));

        Log::info('Knowledge base article published', [
            'article_id' => $article->id,
            'user_id' => auth()->id(),
        ]);

        return $article->fresh();
    }

    /**
     * Unpublish an article
     */
    public function unpublishArticle(KnowledgeBaseArticle $article): KnowledgeBaseArticle
    {
        if (!$article->canTransitionTo('draft')) {
            throw ValidationException::withMessages([
                'status' => ['Article cannot be unpublished in its current state.'],
            ]);
        }

        $article->unpublish();

        Log::info('Knowledge base article unpublished', [
            'article_id' => $article->id,
            'user_id' => auth()->id(),
        ]);

        return $article->fresh();
    }

    /**
     * Archive an article
     */
    public function archiveArticle(KnowledgeBaseArticle $article): KnowledgeBaseArticle
    {
        if (!$article->canTransitionTo('archived')) {
            throw ValidationException::withMessages([
                'status' => ['Article cannot be archived in its current state.'],
            ]);
        }

        $article->archive();

        event(new ArticleArchived($article));

        Log::info('Knowledge base article archived', [
            'article_id' => $article->id,
            'user_id' => auth()->id(),
        ]);

        return $article->fresh();
    }

    /**
     * Get article statistics
     */
    public function getStatistics(): array
    {
        $query = KnowledgeBaseArticle::query();

        return [
            'total' => $query->count(),
            'draft' => (clone $query)->where('status', 'draft')->count(),
            'published' => (clone $query)->where('status', 'published')->count(),
            'archived' => (clone $query)->where('status', 'archived')->count(),
            'by_visibility' => (clone $query)
                ->selectRaw('visibility, count(*) as count')
                ->groupBy('visibility')
                ->pluck('count', 'visibility')
                ->toArray(),
            'categories' => KnowledgeBaseCategory::count(),
            'published_ai_eligible' => (clone $query)
                ->where('status', 'published')
                ->whereIn('visibility', ['ai', 'public'])
                ->count(),
        ];
    }
}
