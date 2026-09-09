<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class KnowledgeBaseCategory extends Model
{
    use SoftDeletes;

    protected $table = 'knowledge_base_categories';

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'status_label',
        'status_color',
        'articles_count',
    ];

    // ============================================
    // BOOT
    // ============================================

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function articles(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticle::class, 'category_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'active' => 'Active',
            'inactive' => 'Inactive',
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        $colors = [
            'active' => 'success',
            'inactive' => 'secondary',
        ];
        return $colors[$this->status] ?? 'secondary';
    }

    public function getArticlesCountAttribute(): int
    {
        return $this->articles()->count();
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ILIKE', "%{$search}%")
                ->orWhere('description', 'ILIKE', "%{$search}%");
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ============================================
    // METHODS
    // ============================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'inactive']);
    }

    public function getPublishedArticlesCount(): int
    {
        return $this->articles()->where('status', 'published')->count();
    }
}