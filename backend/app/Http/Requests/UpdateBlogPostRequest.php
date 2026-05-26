<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBlogPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blog_category_id' => ['nullable', 'exists:blog_categories,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('blog_posts', 'slug')->ignore($this->route('blogPost')),
            ],
            'excerpt' => ['nullable', 'string'],
            'content' => ['nullable', 'array'],
            'cover_media' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'max:50'],
            'published_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }
}
