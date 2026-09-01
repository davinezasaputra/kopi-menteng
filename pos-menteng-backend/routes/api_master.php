<?php

use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('/warehouses', function () {
        $context = app(TenantContext::class);

        return response()->json([
            'status' => 'success',
            'data' => Warehouse::query()
                ->whereHas('branch', function ($query) use ($context): void {
                    $query->where('company_id', $context->companyId())
                        ->whereKey($context->branchId());
                })
                ->where('status', 'active')
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
        ]);
    });

    Route::get('/roles', function () {
        $context = app(TenantContext::class);

        return response()->json([
            'status' => 'success',
            'data' => Role::query()
                ->where(function ($query) use ($context): void {
                    $query->whereNull('tenant_id')
                        ->orWhere('tenant_id', $context->tenantId());
                })
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code', 'description', 'is_system']),
        ]);
    });
});
