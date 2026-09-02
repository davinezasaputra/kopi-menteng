<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Core\Models\DocumentSequence;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\CostCenter;
use App\Domain\Organization\Models\Department;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class FoundationController extends Controller
{
    public function roles(Request $request){
        $context=app(TenantContext::class);
        return ApiResponse::success(Role::query()->where(fn($q)=>$q->whereNull('tenant_id')->orWhere('tenant_id',$context->tenantId()))->with('permissions')->orderBy('name')->get());
    }
    public function permissions(){return ApiResponse::success(Permission::query()->orderBy('module')->orderBy('resource')->orderBy('action')->get());}
    public function memberships(Request $request){
        $context=app(TenantContext::class); $query=Membership::query()->where('tenant_id',$context->tenantId())->with(['user','role.permissions','company','branch','location','permissionOverrides.permission']);
        if($request->filled('status'))$query->where('status',$request->string('status'));
        if($request->filled('company_id'))$query->where('company_id',$request->integer('company_id'));
        if($request->filled('branch_id'))$query->where('branch_id',$request->integer('branch_id'));
        if($request->filled('location_id'))$query->where('location_id',$request->integer('location_id'));
        return ApiResponse::success($query->paginate(min($request->integer('per_page',50),100)));
    }
    public function myMemberships(){
        $userId=request()->user()->getAuthIdentifier();
        return ApiResponse::success(Membership::query()->where('user_id',$userId)->where('status','active')->with(['tenant','company','branch','location','role'])->orderByDesc('is_primary')->get());
    }
    public function context(){
        $context=app(TenantContext::class); $membership=$context->membership();
        $permissions = $membership?->permissionOverrides?->isNotEmpty()
            ? $membership->permissionOverrides->pluck('permission.name')->filter()->values()->all()
            : ($membership?->role?->permissions?->pluck('name')->values()->all() ?? []);
        return ApiResponse::success([
            'tenant_id'=>$context->tenantId(),'company_id'=>$context->companyId(),'branch_id'=>$context->branchId(),
            'location_id'=>$context->locationId(),'location_type'=>$context->locationType(),
            'role'=>$membership?->role?->code,'permissions'=>$permissions,
        ]);
    }
    public function auditLogs(Request $request){
        $context=app(TenantContext::class); $query=AuditLog::query()->where('tenant_id',$context->tenantId());
        if($request->filled('module'))$query->where('module',$request->string('module')); if($request->filled('event'))$query->where('event',$request->string('event'));
        return ApiResponse::success($query->orderByDesc('created_at')->paginate(min($request->integer('per_page',50),100)));
    }
    public function documentSequences(Request $request){
        $context=app(TenantContext::class);
        return ApiResponse::success(DocumentSequence::query()->where('tenant_id',$context->tenantId())->orderBy('document_type')->orderBy('period','desc')->paginate(min($request->integer('per_page',50),100)));
    }
    public function departments(){
        $context=app(TenantContext::class);
        return ApiResponse::success(Department::query()->where('company_id',$context->companyId())->orderBy('code')->get());
    }
    public function costCenters(){
        $context=app(TenantContext::class);
        return ApiResponse::success(CostCenter::query()->where('company_id',$context->companyId())->orderBy('code')->get());
    }
}
