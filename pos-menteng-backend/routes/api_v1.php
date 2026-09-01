<?php

use App\Domain\Identity\Models\Permission;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\DeveloperOrganizationController;
use App\Http\Controllers\Api\DeveloperTenantController;
use App\Http\Controllers\Api\FoundationController;
use App\Http\Controllers\Api\OrganizationProvisioningController;
use App\Http\Controllers\Api\UserController;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['request.id','security.headers'])->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:erp-login');
        Route::post('/login-pin', [AuthController::class, 'loginPin'])->middleware('throttle:erp-login');
        Route::middleware(['auth:sanctum','tenant'])->post('/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum','throttle:erp'])->group(function () {
        Route::get('/me', [FoundationController::class, 'context'])->middleware('tenant');
        Route::post('/context', function (Request $request) {
            $data=$request->validate(['tenant_id'=>['nullable','integer'],'company_id'=>['nullable','integer'],'branch_id'=>['nullable','integer']]);
            foreach(['tenant_id'=>'X-Tenant-ID','company_id'=>'X-Company-ID','branch_id'=>'X-Branch-ID'] as $field=>$header){if(array_key_exists($field,$data)&&$data[$field]!==null)$request->headers->set($header,(string)$data[$field]);}
            app(TenantContext::class)->resolveFor($request->user(),$request);
            return app(FoundationController::class)->context();
        });

        Route::middleware('tenant')->group(function () {
            Route::get('/my-memberships', [FoundationController::class, 'myMemberships']);
            Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.user.view');
            Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.user.create');
            Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:users.user.delete');
            Route::get('/roles', [FoundationController::class, 'roles'])->middleware('permission:rbac.role.view');
            Route::get('/permissions', [FoundationController::class, 'permissions'])->middleware('permission:rbac.role.view');
            Route::get('/memberships', [FoundationController::class, 'memberships'])->middleware('permission:rbac.role.view');
            Route::get('/audit-logs', [FoundationController::class, 'auditLogs'])->middleware('permission:audit.audit_log.view');
            Route::get('/document-sequences', [FoundationController::class, 'documentSequences'])->middleware('permission:rbac.role.view');
            Route::get('/organizations/departments', [FoundationController::class, 'departments'])->middleware('permission:organization.branch.view');
            Route::get('/organizations/cost-centers', [FoundationController::class, 'costCenters'])->middleware('permission:organization.branch.view');
        });

        Route::prefix('developer')->middleware('platform.admin')->group(function () {
            Route::get('/license-catalog', [DeveloperController::class, 'licenseCatalog']);
            Route::get('/permissions', fn () => response()->json(['status' => 'success', 'data' => Permission::query()->orderBy('module')->orderBy('resource')->orderBy('action')->get()]));
            Route::get('/tenants', [DeveloperController::class, 'tenants']);
            Route::post('/provision-tenant', [DeveloperController::class, 'provisionTenant']);
            Route::put('/tenants/{tenant}', [DeveloperTenantController::class, 'update']);
            Route::get('/tenants/{tenant}/organization', [DeveloperOrganizationController::class, 'show']);
            Route::post('/tenants/{tenant}/companies', [DeveloperOrganizationController::class, 'storeCompany']);
            Route::put('/tenants/{tenant}/companies/{company}', [DeveloperOrganizationController::class, 'updateCompany']);
            Route::post('/tenants/{tenant}/companies/{company}/branches', [DeveloperOrganizationController::class, 'storeBranch']);
            Route::put('/tenants/{tenant}/companies/{company}/branches/{branch}', [DeveloperOrganizationController::class, 'updateBranch']);
            Route::post('/tenants/{tenant}/companies/{company}/branches/{branch}/locations', [DeveloperOrganizationController::class, 'storeLocation']);
            Route::put('/tenants/{tenant}/companies/{company}/branches/{branch}/locations/{location}', [DeveloperOrganizationController::class, 'updateLocation']);
            Route::get('/tenants/{tenant}/license', [DeveloperController::class, 'tenantLicense']);
            Route::put('/tenants/{tenant}/license', [DeveloperController::class, 'updateTenantLicense']);
            Route::get('/tenants/{tenant}/subscription', [DeveloperController::class, 'subscription']);
            Route::put('/tenants/{tenant}/subscription', [DeveloperController::class, 'updateSubscription']);
            Route::get('/tenants/{tenant}/license-events', [DeveloperController::class, 'licenseEvents']);
            Route::get('/tenants/{tenant}/admins', [DeveloperController::class, 'tenantAdmins']);
            Route::put('/tenant-admins/{membership}', [DeveloperController::class, 'updateTenantAdmin']);
        });

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
