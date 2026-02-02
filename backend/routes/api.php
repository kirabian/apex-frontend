<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\OnlineShopController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\ProductTypeController;
use App\Http\Controllers\InventoryController;

// ... (previous routes)



Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // ... users, branches, etc ...
    Route::apiResource('users', UserController::class);
    Route::apiResource('branches', BranchController::class);
    Route::apiResource('warehouses', WarehouseController::class);
    Route::apiResource('online-shops', OnlineShopController::class);

    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('distributors', DistributorController::class);
    Route::apiResource('brands', BrandController::class);
    Route::apiResource('product-types', ProductTypeController::class);

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn']);
    Route::get('/inventory/products-lookup', [InventoryController::class, 'getProducts']);
});
