<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('/warehouses', function (): \Illuminate\Http\JsonResponse {
        $context = app(TenantContext::class);
        $branchId = $context->branchId();

        $query = Warehouse::query()
            ->whereHas('branch.company', function ($query) use ($context): void {
                $query->where('tenant_id', $context->tenantId());
            })
            ->where('status', 'active')
            ->orderBy('is_default', 'desc')
            ->orderBy('name');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get(['id', 'branch_id', 'code', 'name', 'type', 'is_default', 'status']),
        ]);
    });

    Route::get('/roles', function (Request $request): \Illuminate\Http\JsonResponse {
        $context = app(TenantContext::class);

        $roles = Role::query()
            ->where(function ($query) use ($context): void {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $context->tenantId());
            })
            ->with('permissions:id,name')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'code', 'description', 'is_system']);

        return response()->json([
            'status' => 'success',
            'data' => $roles,
        ]);
    });
});
