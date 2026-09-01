<?php

use App\Domain\Identity\Models\Role;
use App\Http\Controllers\Api\ReceiptTemplateController;
use App\Support\Tenancy\OrganizationScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('/warehouses', function (): \Illuminate\Http\JsonResponse {
        $context = app(TenantContext::class);
        $scope = app(OrganizationScope::class);
        $query = $scope->warehouseQuery()->orderByDesc('is_default')->orderBy('name');

        return response()->json([
            'status' => 'success',
            'data' => $query->get(['id','branch_id','location_id','code','name','type','is_default','status']),
        ]);
    });

    Route::get('/roles', function (Request $request): \Illuminate\Http\JsonResponse {
        $context = app(TenantContext::class);
        $roles = Role::query()
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $context->tenantId()))
            ->with('permissions:id,name')
            ->orderBy('is_system','desc')->orderBy('name')
            ->get(['id','tenant_id','name','code','description','is_system']);
        return response()->json(['status'=>'success','data'=>$roles]);
    });

    Route::get('/pos/receipt-template', [ReceiptTemplateController::class, 'show'])->middleware('permission:pos.receipt_template.view');
    Route::put('/pos/receipt-template', [ReceiptTemplateController::class, 'update'])->middleware('permission:pos.receipt_template.manage');
});
