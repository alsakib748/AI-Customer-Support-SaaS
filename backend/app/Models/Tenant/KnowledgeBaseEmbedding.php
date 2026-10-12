<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseEmbedding extends Model
{
    protected $table = 'knowledge_base_embeddings';

    protected $connection = 'tenant';

    protected $fillable = [
        'article_id',
        'chunk_index',
        'content',
        'content_hash',
        'article_version',
        'embedding',
        'model',
        'dimensions',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'dimensions' => 'integer',
        'embedding' => 'array',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'article_id');
    }
}
