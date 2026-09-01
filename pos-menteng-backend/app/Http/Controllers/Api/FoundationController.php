<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Support\Responses\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class FoundationController extends Controller
{
    public function roles(Request $request)
    {
        $context = app(TenantContext::class);
        return ApiResponse::success(Role::query()->where(function ($q) use ($context) {
            $q->whereNull('tenant_id')->orWhere('tenant_id', $context->tenantId());
        })->with('permissions')->orderBy('name')->get());
    }

    public function permissions()
    {
        return ApiResponse::success(Permission::query()->orderBy('module')->orderBy('resource')->orderBy('action')->get());
    }

    public function memberships(Request $request)
    {
        $context = app(TenantContext::class);
        $query = Membership::query()->where('tenant_id', $context->tenantId())->with(['user','role','company','branch']);
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return ApiResponse::success($query->paginate(min($request->integer('per_page', 50), 100)));
    }

    public function context()
    {
        $context = app(TenantContext::class);
        return ApiResponse::success([
            'tenant_id' => $context->tenantId(),
            'company_id' => $context->companyId(),
            'branch_id' => $context->branchId(),
            'role' => $context->membership()?->role?->code,
        ]);
    }

    public function auditLogs(Request $request)
    {
        $context = app(TenantContext::class);
        $query = AuditLog::query()->where('tenant_id', $context->tenantId());
        if ($request->filled('module')) $query->where('module', $request->string('module'));
        if ($request->filled('event')) $query->where('event', $request->string('event'));
        return ApiResponse::success($query->orderByDesc('created_at')->paginate(min($request->integer('per_page', 50), 100)));
    }
}
