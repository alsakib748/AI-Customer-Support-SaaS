<?php
// app/Services/Analytics/KnowledgeBaseAnalyticsService.php

namespace App\Services\Analytics;

use App\Models\Tenant\KnowledgeBaseArticle;
use App\Models\Tenant\KnowledgeBaseCategory;

class KnowledgeBaseAnalyticsService extends BaseAnalyticsService
{
    public function report(): array
    {
        return $this->remember('knowledge_base', function () {
            return [
                'summary' => $this->summary(),
                'status_breakdown' => $this->statusBreakdown(),
                'category_breakdown' => $this->categoryBreakdown(),
            ];
        });
    }

    protected function summary(): array
    {
        return [
            'total_articles' => KnowledgeBaseArticle::count(),
            'published' => KnowledgeBaseArticle::where('status', 'published')->count(),
            'draft' => KnowledgeBaseArticle::where('status', 'draft')->count(),
            'archived' => KnowledgeBaseArticle::where('status', 'archived')->count(),
            'total_categories' => KnowledgeBaseCategory::count(),
            'ai_eligible' => KnowledgeBaseArticle::where('status', 'published')
                ->whereIn('visibility', ['ai', 'public'])
                ->count(),
        ];
    }

    protected function statusBreakdown(): array
    {
        return [
            'labels' => ['Draft', 'Published', 'Archived'],
            'values' => [
                KnowledgeBaseArticle::where('status', 'draft')->count(),
                KnowledgeBaseArticle::where('status', 'published')->count(),
                KnowledgeBaseArticle::where('status', 'archived')->count(),
            ],
        ];
    }

    protected function categoryBreakdown(): array
    {
        $rows = KnowledgeBaseCategory::withCount('articles')
            ->orderByDesc('articles_count')
            ->limit(20)
            ->get();

        return [
            'labels' => $rows->pluck('name')->toArray(),
            'values' => $rows->pluck('articles_count')->map(fn($v) => (int) $v)->toArray(),
        ];
    }
}
