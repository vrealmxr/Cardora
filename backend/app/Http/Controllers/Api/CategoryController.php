<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return CategoryResource::collection(
            Category::query()->with('children')->orderBy('sort_order')->get()
        );
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create($request->validated());

        return (new CategoryResource($category->load('children')))->response()->setStatusCode(201);
    }

    public function show(Category $category)
    {
        return new CategoryResource($category->load(['children', 'products']));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update($request->validated());

        return new CategoryResource($category->fresh()->load('children'));
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->noContent();
    }
}
