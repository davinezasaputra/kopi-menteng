<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RawMaterialController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\CategoriesController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/login-pin', [AuthController::class, 'loginPin']);
Route::post('/midtrans/webhook', [PaymentController::class, 'handleWebhook']);

Route::middleware('auth:sanctum')->group(function(){
    Route::get('/me', function(Request $request) {return $request->user();});
    Route::post('/shifts/open', [ShiftController::class, 'open']);
    Route::post('/shifts/close', [ShiftController::class, 'close']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    Route::post('/products/{id}/recipe', [ProductController::class, 'syncRecipe']);
    Route::get('/categories', [CategoriesController::class, 'index']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/history', [OrderController::class, 'history']);
    Route::post('/orders/checkout', [OrderController::class, 'checkout' ]);
    

    Route::get('/raw-materials', [RawMaterialController::class, 'index']);
    Route::post('/raw-materials', [RawMaterialController::class, 'store']);
    Route::put('/raw-materials/{id}', [RawMaterialController::class, 'update']);
    Route::delete('/raw-materials/{id}', [RawMaterialController::class, 'destroy']);
    Route::put('/raw-materials/{id}/toggle-request', [RawMaterialController::class, 'toggleShoppingRequest']);
    Route::post('/raw-materials/{id}/restock', [RawMaterialController::class, 'restock']);

});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
