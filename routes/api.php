<?php

use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\ContentPageController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\ReturnController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Catalogue (public)
Route::get('/store', [CatalogController::class, 'store']);
Route::get('/categories', [CatalogController::class, 'categories']);
Route::get('/products', [CatalogController::class, 'products']);
Route::get('/sitemap', [CatalogController::class, 'sitemap']);
Route::get('/products/{slug}', [CatalogController::class, 'product']);
Route::get('/banners', [CatalogController::class, 'banners']);
Route::get('/sections', [CatalogController::class, 'sections']);
Route::get('/returns/reasons', [ReturnController::class, 'reasons']);
Route::get('/regions', [RegionController::class, 'index']);
Route::get('/pages', [ContentPageController::class, 'index']);
Route::get('/pages/{slug}', [ContentPageController::class, 'show']);

// Checkout
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/checkout/quote', [OrderController::class, 'quote']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/orders/track', [OrderController::class, 'track']);
    Route::post('/orders/{number}/pay', [OrderController::class, 'pay']);
    Route::post('/products/{slug}/reviews', [CatalogController::class, 'review']);
    Route::post('/products/{slug}/notify', [CatalogController::class, 'notifyWhenBack']);
    Route::post('/returns', [ReturnController::class, 'store']);
});
Route::get('/orders/{number}', [OrderController::class, 'show']);
Route::get('/orders/{number}/payment-status', [OrderController::class, 'paymentStatus'])->middleware('throttle:60,1');

// Webhooks from the mobile money provider
Route::post('/payments/callback/{driver}', PaymentCallbackController::class);

// Logged-in customer (used later for "My orders")
Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');
