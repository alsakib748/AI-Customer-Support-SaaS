<?php

namespace App\Http\Controllers\Api\V1\KnowledgeBase;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBase\StoreArticleRequest;
use App\Http\Requests\KnowledgeBase\UpdateArticleRequest;
use App\Http\Resources\KnowledgeBase\ArticleCollection;
use App\Http\Resources\KnowledgeBase\ArticleResource;
use App\Services\KnowledgeBase\KnowledgeBaseArticleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArticleController extends Controller
{
    protected KnowledgeBaseArticleService $service;

    public function __construct(KnowledgeBaseArticleService $service)
    {
        $this->service = $service;
    }

    /**
     * Get list of articles
     */
    public function index(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view articles.',
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

            $filters = $request->only([
                'search',
                'category_id',
                'status',
                'visibility',
                'author_id',
                'date_from',
                'date_to',
                'sort',
                'direction',
                'per_page',
            ]);

            $articles = $this->service->getArticles($filters);

            return new ArticleCollection($articles);

        } catch (\Exception $e) {
            Log::error('Failed to get articles:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve articles.',
            ], 500);
        }
    }

    /**
     * Create a new article
     */
    public function store(StoreArticleRequest $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.create')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to create articles.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot create articles without tenant context.',
                ], 400);
            }

            $article = $this->service->createArticle($request->validated());

            return (new ArticleResource($article))
                ->additional([
                    'message' => 'Article created successfully 🎉',
                ]);

        } catch (\Exception $e) {
            Log::error('Failed to create article:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create article.',
            ], 500);
        }
    }

    /**
     * Get a single article
     */
    public function show(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view articles.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin does not have article context.',
                ], 404);
            }

            $article = $this->service->getArticle($id);

            return new ArticleResource($article);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to get article:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve article.',
            ], 500);
        }
    }

    /**
     * Update an article
     */
    public function update(UpdateArticleRequest $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to update articles.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot update articles without tenant context.',
                ], 400);
            }

            $article = $this->service->getArticle($id);
            $article = $this->service->updateArticle($article, $request->validated());

            return (new ArticleResource($article))
                ->additional([
                    'message' => 'Article updated successfully 🎉',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to update article:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update article.',
            ], 500);
        }
    }

    /**
     * Delete an article
     */
    public function destroy(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.delete')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to delete articles.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot delete articles without tenant context.',
                ], 400);
            }

            $article = $this->service->getArticle($id);
            $this->service->deleteArticle($article);

            return response()->json([
                'success' => true,
                'message' => 'Article deleted successfully.',
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to delete article:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete article.',
            ], 500);
        }
    }

    /**
     * Publish an article
     */
    public function publish(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to publish articles.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot publish articles without tenant context.',
                ], 400);
            }

            $article = $this->service->getArticle($id);
            $article = $this->service->publishArticle($article);

            return (new ArticleResource($article))
                ->additional([
                    'message' => 'Article published successfully ✅',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot publish article.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to publish article:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to publish article.',
            ], 500);
        }
    }

    /**
     * Unpublish an article
     */
    public function unpublish(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to unpublish articles.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot unpublish articles without tenant context.',
                ], 400);
            }

            $article = $this->service->getArticle($id);
            $article = $this->service->unpublishArticle($article);

            return (new ArticleResource($article))
                ->additional([
                    'message' => 'Article unpublished successfully 📝',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot unpublish article.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to unpublish article:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to unpublish article.',
            ], 500);
        }
    }

    /**
     * Archive an article
     */
    public function archive(Request $request, $id)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.update')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to archive articles.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Super Admin cannot archive articles without tenant context.',
                ], 400);
            }

            $article = $this->service->getArticle($id);
            $article = $this->service->archiveArticle($article);

            return (new ArticleResource($article))
                ->additional([
                    'message' => 'Article archived successfully 📦',
                ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Article not found.',
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot archive article.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to archive article:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive article.',
            ], 500);
        }
    }

    /**
     * Get article statistics
     */
    public function statistics(Request $request)
    {
        try {
            // if (!auth()->user()->hasPermissionTo('knowledge.view')) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view statistics.',
            //     ], 403);
            // }

            if (auth()->user()->hasRole('super-admin')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Super Admin: No tenant context required.',
                    'data' => $this->getEmptyStatistics(),
                ]);
            }

            $statistics = $this->service->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get article statistics:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve article statistics.',
            ], 500);
        }
    }

    /**
     * Get articles for AI retrieval (Public/Internal)
     */
    public function searchForAI(Request $request)
    {
        try {
            $request->validate([
                'query' => ['required', 'string', 'min:2'],
            ]);

            $articles = $this->service->getArticlesForAI($request->query);

            return response()->json([
                'success' => true,
                'data' => $articles,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to search articles for AI:', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search articles.',
            ], 500);
        }
    }

    protected function getEmptyStatistics(): array
    {
        return [
            'total' => 0,
            'draft' => 0,
            'published' => 0,
            'archived' => 0,
            'by_visibility' => [],
            'categories' => 0,
            'published_ai_eligible' => 0,
        ];
    }
}