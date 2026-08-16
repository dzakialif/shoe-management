<?php

use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ShoeController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json(['message' => 'pong']);
});

Route::prefix('v1')->group(function () {
    // Options (dropdown) — WAJIB sebelum apiResource agar "options" tidak tertangkap sebagai {category}/{brand}
    Route::get('categories/options', [CategoryController::class, 'options']);
    Route::get('brands/options', [BrandController::class, 'options']);

    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('shoes', ShoeController::class);
});