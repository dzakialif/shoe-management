<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    protected BrandService $brandService;

    public function __construct(BrandService $brandService)
    {
        $this->brandService = $brandService;
    }

    public function index(): JsonResponse
    {
        return apiResponse(
            data: BrandResource::collection($this->brandService->all()),
            message: 'List brands retrieved successfully.',
        );
    }

    public function show(Brand $brand): JsonResponse
    {
        return apiResponse(
            data: new BrandResource($brand),
            message: 'Brand retrieved successfully.',
        );
    }

    public function store(StoreBrandRequest $request): JsonResponse
    {
        $brand = $this->brandService->create($request->validated());

        return apiResponse(
            data: new BrandResource($brand),
            message: 'Brand created successfully.',
            status: 201,
        );
    }

    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $brand = $this->brandService->update($brand, $request->validated());

        return apiResponse(
            data: new BrandResource($brand),
            message: 'Brand updated successfully.',
        );
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->brandService->delete($brand);

        return apiResponse(
            message: 'Brand deleted successfully.',
        );
    }
}
