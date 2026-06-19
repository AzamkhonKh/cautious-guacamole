<?php

use App\Http\Controllers\API\BatchProfitApiController;
use App\Http\Controllers\API\ClientRefundApiController;
use App\Http\Controllers\API\OrderApiController;
use App\Http\Controllers\API\ProductApiController;
use App\Http\Controllers\API\ProviderRefundApiController;
use App\Http\Controllers\API\PurchaseApiController;
use App\Http\Controllers\API\StorageApiController;
use Illuminate\Support\Facades\Route;

Route::post('/purchases', PurchaseApiController::class);
Route::post('/refunds/provider', ProviderRefundApiController::class);
Route::get('/products/available', [ProductApiController::class, 'available']);
Route::post('/orders', OrderApiController::class);
Route::post('/refunds/client', ClientRefundApiController::class);
Route::get('/storages/remaining-quantities', [StorageApiController::class, 'remainingQuantities']);
Route::get('/batches/profit', BatchProfitApiController::class);
