<?php

namespace App\Ai\Tools;

use App\Ai\Services\SemanticSearchService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class RAGSearchTool extends BaseTool
{
    protected SemanticSearchService $searchService;

    public function __construct(SemanticSearchService $searchService)
    {
        parent::__construct();
        $this->searchService = $searchService;
    }

    public function name(): string
    {
        return 'rag_search';
    }

    public function description(): string
    {
        return 'Search the knowledge base using semantic search (RAG) to find relevant information for customer questions.';
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
                'default' => 3,
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
            $limit = $parameters['limit'] ?? 3;

            if (empty($query)) {
                return $this->error('Search query is required.');
            }

            // Perform semantic search
            $result = $this->searchService->searchWithContext($query, $limit);

            $this->logExecution('rag_search', $parameters, $result);

            return [
                'success' => true,
                'results' => $result,
                'count' => count($result),
                'message' => 'RAG search completed successfully.',
            ];

        } catch (\Exception $e) {
            Log::error('RAG search failed:', ['error' => $e->getMessage()]);
            return $this->error('Failed to perform RAG search: ' . $e->getMessage());
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
}
