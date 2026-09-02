<?php

use App\Http\Controllers\Api\MenuImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['request.id', 'security.headers', 'auth:sanctum', 'throttle:erp', 'tenant'])->group(function (): void {
    Route::get('/inventory/menu-import/template', [MenuImportController::class, 'template'])
        ->middleware('permission:inventory.stock.view');
    Route::post('/inventory/menu-import', [MenuImportController::class, 'import'])
        ->middleware('permission:inventory.stock.adjust');
});
