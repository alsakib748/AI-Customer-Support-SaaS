<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KnowledgeBaseArticle extends Model
{
    use SoftDeletes;

    protected $table = 'knowledge_base_articles';

    protected $connection = 'tenant';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'visibility',
        'author_id',
        'published_at',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'visibility_label',
        'visibility_color',
        'is_draft',
        'is_published',
        'is_archived',
        'is_public',
        'is_ai_eligible',
        'is_internal',
    ];

    // ============================================
    // BOOT
    // ============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title);
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'category_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'draft' => 'secondary',
            'published' => 'success',
            'archived' => 'danger',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getVisibilityLabelAttribute(): string
    {
        $labels = [
            'ai' => 'AI Only',
            'public' => 'Public',
            'internal' => 'Internal',
        ];
        return $labels[$this->visibility] ?? ucfirst($this->visibility);
    }

    public function getVisibilityColorAttribute(): string
    {
        $colors = [
            'ai' => 'info',
            'public' => 'success',
            'internal' => 'warning',
        ];
        return $colors[$this->visibility] ?? 'secondary';
    }

    public function getIsDraftAttribute(): bool
    {
        return $this->status === 'draft';
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published';
    }

    public function getIsArchivedAttribute(): bool
    {
        return $this->status === 'archived';
    }

    public function getIsPublicAttribute(): bool
    {
        return $this->visibility === 'public';
    }

    public function getIsAiEligibleAttribute(): bool
    {
        return in_array($this->visibility, ['ai', 'public']);
    }

    public function getIsInternalAttribute(): bool
    {
        return $this->visibility === 'internal';
    }

    public function getAuthorNameAttribute(): ?string
    {
        if (!$this->author_id) {
            return null;
        }
        return 'User #' . $this->author_id;
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByVisibility($query, $visibility)
    {
        return $query->where('visibility', $visibility);
    }

    public function scopeAiEligible($query)
    {
        return $query->whereIn('visibility', ['ai', 'public']);
    }

    public function scopePublishedAndEligible($query)
    {
        return $query->where('status', 'published')
            ->whereIn('visibility', ['ai', 'public']);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'ILIKE', "%{$search}%")
                ->orWhere('excerpt', 'ILIKE', "%{$search}%")
                ->orWhere('content', 'ILIKE', "%{$search}%");
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    // ============================================
    // METHODS
    // ============================================

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function isAiEligible(): bool
    {
        return $this->isPublished() && in_array($this->visibility, ['ai', 'public']);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            'draft' => ['published', 'archived'],
            'published' => ['draft', 'archived'],
            'archived' => ['draft'],
        ];

        return in_array($newStatus, $transitions[$this->status] ?? []);
    }

    public function publish(): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function archive(): void
    {
        $this->update([
            'status' => 'archived',
        ]);
    }

    public function restoreFromArchive(): void
    {
        $this->update([
            'status' => 'draft',
        ]);
    }

    public function getExcerpt(int $length = 150): string
    {
        if ($this->excerpt) {
            return $this->excerpt;
        }

        $content = strip_tags($this->content);
        return Str::limit($content, $length);
    }
}