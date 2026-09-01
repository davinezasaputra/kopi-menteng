<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FoundationController;
use App\Http\Controllers\Api\OrganizationProvisioningController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['request.id','security.headers'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:erp-login');
        Route::post('/login-pin', [AuthController::class, 'loginPin'])->middleware('throttle:erp-login');
        Route::middleware(['auth:sanctum','tenant'])->post('/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum','throttle:erp','tenant'])->group(function () {
        Route::get('/me', [FoundationController::class, 'context']);
        Route::post('/context', function (Request $request) {
            $request->validate([
                'tenant_id' => ['nullable','integer'],
                'company_id' => ['nullable','integer'],
                'branch_id' => ['nullable','integer'],
            ]);
            return app(FoundationController::class)->context();
        });

        Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.user.view');
        Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.user.create');
        Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.user.delete');

        Route::get('/roles', [FoundationController::class, 'roles'])->middleware('permission:rbac.role.view');
        Route::get('/permissions', [FoundationController::class, 'permissions'])->middleware('permission:rbac.role.view');
        Route::get('/memberships', [FoundationController::class, 'memberships'])->middleware('permission:rbac.role.view');
        Route::get('/audit-logs', [FoundationController::class, 'auditLogs'])->middleware('permission:audit.audit_log.view');

        Route::prefix('organizations')->middleware('platform.admin')->group(function () {
            Route::post('/tenants', [OrganizationProvisioningController::class, 'storeTenant']);
            Route::post('/companies', [OrganizationProvisioningController::class, 'storeCompany']);
            Route::post('/branches', [OrganizationProvisioningController::class, 'storeBranch']);
            Route::post('/warehouses', [OrganizationProvisioningController::class, 'storeWarehouse']);
            Route::post('/tenant-admins', [OrganizationProvisioningController::class, 'storeTenantAdmin']);
            Route::get('/tenants/{tenant}', [OrganizationProvisioningController::class, 'showTenant']);
        });
    });
});
