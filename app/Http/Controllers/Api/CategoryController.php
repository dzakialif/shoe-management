<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    protected CategoryService $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index(Request $request): JsonResponse
    {
        return apiResponse(
            data: CategoryResource::collection($this->categoryService->paginated($request)),
            message: 'List categories retrieved successfully.',
        );
    }

    public function options(): JsonResponse
    {
        return apiResponse(
            data: $this->categoryService->options(),
            message: 'Category options retrieved successfully.',
        );
    }

    public function show(Category $category): JsonResponse
    {
        return apiResponse(
            data: new CategoryResource($category),
            message: 'Category retrieved successfully.',
        );
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create($request->validated());

        return apiResponse(
            data: new CategoryResource($category),
            message: 'Category created successfully.',
            status: 201,
        );
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->categoryService->update($category, $request->validated());

        return apiResponse(
            data: new CategoryResource($category),
            message: 'Category updated successfully.',
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->categoryService->delete($category);

        return apiResponse(
            message: 'Category deleted successfully.',
        );
    }
}
