<?php
// app/Ai/Services/KnowledgeBaseChunkingService.php

namespace App\Ai\Services;

use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Support\Facades\Log;

class KnowledgeBaseChunkingService
{
    protected $chunkSize = 500;
    protected $overlap = 50;

    /**
     * Chunk article content into smaller pieces
     */
    public function chunkArticle(KnowledgeBaseArticle $article): array
    {
        try {
            $content = $this->cleanContent($article->content);
            $chunks = [];

            // Split by paragraphs first
            $paragraphs = preg_split('/\n\s*\n/', $content);

            $currentChunk = '';
            $chunkIndex = 0;

            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (empty($paragraph)) {
                    continue;
                }

                // If adding this paragraph exceeds chunk size, save current chunk
                if (strlen($currentChunk) + strlen($paragraph) > $this->chunkSize) {
                    if (!empty($currentChunk)) {
                        $chunks[] = [
                            'content' => trim($currentChunk),
                            'index' => $chunkIndex++,
                            'article_id' => $article->id,
                            'title' => $article->title,
                            'category' => $article->category?->name,
                        ];
                    }
                    $currentChunk = $paragraph;
                } else {
                    $currentChunk .= ($currentChunk ? "\n\n" : '') . $paragraph;
                }
            }

            // Add the last chunk
            if (!empty($currentChunk)) {
                $chunks[] = [
                    'content' => trim($currentChunk),
                    'index' => $chunkIndex++,
                    'article_id' => $article->id,
                    'title' => $article->title,
                    'category' => $article->category?->name,
                ];
            }

            // Create overlapping chunks for better retrieval
            if (count($chunks) > 1) {
                $overlappedChunks = [];
                $overlapSize = $this->overlap;

                for ($i = 0; $i < count($chunks); $i++) {
                    $chunk = $chunks[$i];
                    if ($i > 0) {
                        // Add overlap from previous chunk
                        $prevContent = $chunks[$i - 1]['content'];
                        $overlapText = substr($prevContent, -$overlapSize);
                        $chunk['content'] = $overlapText . "\n\n" . $chunk['content'];
                        $chunk['has_overlap'] = true;
                    }
                    $overlappedChunks[] = $chunk;
                }

                $chunks = $overlappedChunks;
            }

            Log::info('Article chunked successfully', [
                'article_id' => $article->id,
                'chunks' => count($chunks),
            ]);

            return $chunks;

        } catch (\Exception $e) {
            Log::error('Failed to chunk article:', [
                'article_id' => $article->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Clean content for chunking
     */
    protected function cleanContent(string $content): string
    {
        // Remove HTML tags
        $content = strip_tags($content);

        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Remove special characters
        $content = preg_replace('/[^\w\s\.\,\!\?\-\']/', ' ', $content);

        return trim($content);
    }

    /**
     * Chunk all published articles
     */
    public function chunkAllPublishedArticles(): array
    {
        $articles = KnowledgeBaseArticle::published()
            ->whereIn('visibility', ['ai', 'public'])
            ->get();

        $results = [];

        foreach ($articles as $article) {
            $chunks = $this->chunkArticle($article);
            $results[$article->id] = [
                'article' => $article->title,
                'chunks' => count($chunks),
                'chunks_data' => $chunks,
            ];
        }

        return $results;
    }
}