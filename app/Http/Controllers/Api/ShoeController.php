<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShoeRequest;
use App\Http\Requests\UpdateShoeRequest;
use App\Http\Resources\ShoeResource;
use App\Models\Shoe;
use App\Services\ShoeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShoeController extends Controller
{
    protected ShoeService $shoeService;

    public function __construct(ShoeService $shoeService)
    {
        $this->shoeService = $shoeService;
    }

    public function index(Request $request): JsonResponse
    {
        return apiResponse(
            data: ShoeResource::collection($this->shoeService->paginated($request)),
            message: 'List shoes retrieved successfully.',
        );
    }

    public function show(Shoe $shoe): JsonResponse
    {
        return apiResponse(
            data: new ShoeResource($shoe),
            message: 'Shoe retrieved successfully.',
        );
    }

    public function store(StoreShoeRequest $request): JsonResponse
    {
        $shoe = $this->shoeService->create($request->validated());

        return apiResponse(
            data: new ShoeResource($shoe),
            message: 'Shoe created successfully.',
            status: 201,
        );
    }

    public function update(UpdateShoeRequest $request, Shoe $shoe): JsonResponse
    {
        $shoe = $this->shoeService->update($shoe, $request->validated());

        return apiResponse(
            data: new ShoeResource($shoe),
            message: 'Shoe updated successfully.',
        );
    }

    public function destroy(Shoe $shoe): JsonResponse
    {
        $this->shoeService->delete($shoe);

        return apiResponse(
            message: 'Shoe deleted successfully.',
        );
    }
}
