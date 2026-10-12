<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\RegionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Catalogue (public)
Route::get('/store', [CatalogController::class, 'store']);
Route::get('/categories', [CatalogController::class, 'categories']);
Route::get('/products', [CatalogController::class, 'products']);
Route::get('/products/{slug}', [CatalogController::class, 'product']);
Route::get('/banners', [CatalogController::class, 'banners']);
Route::get('/regions', [RegionController::class, 'index']);

// Checkout
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/checkout/quote', [OrderController::class, 'quote']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/track', [OrderController::class, 'track']);
    Route::post('/orders/{number}/pay', [OrderController::class, 'pay']);
});
Route::get('/orders/{number}', [OrderController::class, 'show']);
Route::get('/orders/{number}/payment-status', [OrderController::class, 'paymentStatus'])->middleware('throttle:60,1');

// Webhooks from the mobile money provider
Route::post('/payments/callback/{driver}', PaymentCallbackController::class);

// Logged-in customer (used later for "My orders")
Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');
