<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlogPostRequest;
use App\Http\Requests\UpdateBlogPostRequest;
use App\Http\Resources\BlogPostResource;
use App\Models\BlogPost;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $posts = BlogPost::query()
            ->with(['category', 'author'])
            ->when(
                $request->string('search')->isNotEmpty(),
                function ($query) use ($request) {
                    $search = $request->string('search');
                    $query->where(function ($builder) use ($search) {
                        $builder
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('excerpt', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $request->filled('category_slug'),
                fn ($query) => $query->whereHas('category', function ($builder) use ($request) {
                    $builder->where('slug', $request->string('category_slug'));
                })
            )
            ->when(
                $request->boolean('published_only', true),
                fn ($query) => $query->where('status', 'published')
            )
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at')
            ->paginate($request->integer('per_page', 12));

        return BlogPostResource::collection($posts);
    }

    public function store(StoreBlogPostRequest $request)
    {
        $post = BlogPost::create([
            ...$request->validated(),
            'author_id' => $request->user()->getKey(),
        ]);

        return (new BlogPostResource($post->load(['category', 'author'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(BlogPost $blogPost)
    {
        return new BlogPostResource($blogPost->load(['category', 'author']));
    }

    public function update(UpdateBlogPostRequest $request, BlogPost $blogPost)
    {
        abort_unless(
            $blogPost->author_id === $request->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $blogPost->update($request->validated());

        return new BlogPostResource($blogPost->fresh()->load(['category', 'author']));
    }

    public function destroy(BlogPost $blogPost)
    {
        abort_unless(
            $blogPost->author_id === request()->user()->getKey(),
            403,
            __('api.errors.forbidden')
        );

        $blogPost->delete();

        return response()->noContent();
    }
}
