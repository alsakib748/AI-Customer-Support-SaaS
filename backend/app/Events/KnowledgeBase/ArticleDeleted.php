<?php

namespace App\Events\KnowledgeBase;

use App\Models\Tenant\KnowledgeBaseArticle;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleDeleted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public KnowledgeBaseArticle $article)
    {
        //
    }
}
