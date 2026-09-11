<?php

namespace App\Ai\Tools;

use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SearchKnowledgeBaseTool extends BaseTool
{

    public function name(): string
    {
        return 'search_knowledge_base';
    }

    public function description(): string
    {
        return 'Search the knowledge base for relevant articles and information.';
    }

    public function parameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'The search query',
                'required' => true,
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of results to return',
                'default' => 5,
            ],
        ];
    }

    public function execute(array $parameters): array
    {
        try {
            if (!$this->isAuthorized()) {
                return $this->error('Unauthorized to search knowledge base.');
            }

            $query = $parameters['query'] ?? '';
            $limit = $parameters['limit'] ?? 5;

            if (empty($query)) {
                return $this->error('Search query is required.');
            }

            // Search only published and AI-eligible articles
            $results = KnowledgeBaseArticle::publishedAndEligible()
                ->with('category')
                ->where(function ($q) use ($query) {
                    $q->where('title', 'ILIKE', "%{$query}%")
                        ->orWhere('excerpt', 'ILIKE', "%{$query}%")
                        ->orWhere('content', 'ILIKE', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            $formattedResults = $results->map(function ($article) {
                return [
                    'id' => $article->id,
                    'title' => $article->title,
                    'excerpt' => $article->excerpt ?? Str::limit((string) $article->content, 150),
                    'content' => $article->content,
                    'category' => $article->category?->name,
                    'url' => route('knowledge-base.articles.show', $article->slug),
                ];
            });

            $this->logExecution('search_knowledge_base', $parameters, $formattedResults);

            return [
                'success' => true,
                'results' => $formattedResults->toArray(),
                'count' => $formattedResults->count(),
                'message' => 'Knowledge base search completed.',
            ];

        } catch (\Exception $e) {
            Log::error('Knowledge base search failed:', ['error' => $e->getMessage()]);
            return $this->error('Failed to search knowledge base: ' . $e->getMessage());
        }
    }

    protected function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'results' => [],
            'count' => 0,
        ];
    }


    // /**
    //  * Get the description of the tool's purpose.
    //  */
    // public function description(): Stringable|string
    // {
    //     return 'A description of the tool.';
    // }

    // /**
    //  * Execute the tool.
    //  */
    // public function handle(Request $request): Stringable|string
    // {
    //     //
    // }

    // /**
    //  * Get the tool's schema definition.
    //  */
    // public function schema(JsonSchema $schema): array
    // {
    //     return [
    //         'value' => $schema->string()->required(),
    //     ];
    // }
}