<?php

namespace App\Http\Resources\KnowledgeBase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'visibility' => $this->visibility,
            'visibility_label' => $this->visibility_label,
            'visibility_color' => $this->visibility_color,
            'is_draft' => $this->is_draft,
            'is_published' => $this->is_published,
            'is_archived' => $this->is_archived,
            'is_public' => $this->is_public,
            'is_ai_eligible' => $this->is_ai_eligible,
            'is_internal' => $this->is_internal,
            'author_id' => $this->author_id,
            'author_name' => $this->author_name,
            'published_at' => $this->published_at?->toISOString(),
            'sort_order' => $this->sort_order,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}