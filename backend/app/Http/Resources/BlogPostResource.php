<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blog_category_id' => $this->blog_category_id,
            'author_id' => $this->author_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'cover_media' => $this->cover_media,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'tags' => $this->tags,
            'seo' => $this->seo,
            'is_featured' => $this->is_featured,
            'category' => new BlogCategoryResource($this->whenLoaded('category')),
            'author' => new UserProfileResource($this->whenLoaded('author')),
        ];
    }
}
