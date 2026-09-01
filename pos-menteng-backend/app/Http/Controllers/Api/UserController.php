<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\TenantLicense;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $context = app(TenantContext::class);
        $query = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('tenant_id',$context->tenantId())->where('status','active'))
            ->with(['memberships' => fn ($q) => $q->where('tenant_id',$context->tenantId())->where('status','active')->with(['role','company','branch','location'])])
            ->orderBy('name');

        if ($context->locationId() !== null) {
            $query->whereHas('memberships', fn ($q) => $q->where('tenant_id',$context->tenantId())->where('status','active')->where('location_id',$context->locationId()));
        } elseif ($context->branchId() !== null) {
            $query->whereHas('memberships', fn ($q) => $q->where('tenant_id',$context->tenantId())->where('status','active')->where('branch_id',$context->branchId()));
        }

        return response()->json(['status'=>'success','data'=>$query->get()]);
    }

    public function store(Request $request)
    {
        $context = app(TenantContext::class);
        $validated = $request->validate([
            'name'=>'required|string|max:255','email'=>'required|email|unique:users,email','password'=>'required|string|min:8',
            'role_code'=>'required|string|max:100','company_id'=>'nullable|integer','branch_id'=>'nullable|integer','location_id'=>'nullable|integer',
        ]);

        $license = TenantLicense::query()->where('tenant_id',$context->tenantId())->first();
        if ($license && $license->max_users !== null) {
            $activeUsers = Membership::query()->where('tenant_id',$context->tenantId())->where('status','active')->count();
            if ($activeUsers >= $license->max_users) return response()->json(['status'=>'error','message'=>"Batas user lisensi tercapai ({$license->max_users}). Upgrade lisensi untuk menambah user."],422);
        }

        $role = Role::query()->where('tenant_id',$context->tenantId())->where('code',$validated['role_code'])->firstOrFail();
        $companyId = $validated['company_id'] ?? $context->companyId();
        $branchId = $validated['branch_id'] ?? $context->branchId();
        $locationId = $validated['location_id'] ?? $context->locationId();

        if ($context->locationId() !== null && (int)$locationId !== (int)$context->locationId()) abort(403,'Akun Anda hanya dapat membuat user pada location scope aktif.');
        if ($companyId !== null) abort_unless(Company::query()->whereKey($companyId)->where('tenant_id',$context->tenantId())->exists(),403);
        if ($branchId !== null) abort_unless(Branch::query()->whereKey($branchId)->whereHas('company',fn($q)=>$q->where('tenant_id',$context->tenantId())->whereKey($companyId))->exists(),403);
        if ($locationId !== null) abort_unless(Location::query()->whereKey($locationId)->where('branch_id',$branchId)->whereHas('branch.company',fn($q)=>$q->where('id',$companyId)->where('tenant_id',$context->tenantId()))->exists(),403);

        $user = DB::transaction(function () use ($validated,$context,$role,$companyId,$branchId,$locationId) {
            $user = User::create([
                'name'=>$validated['name'],'email'=>$validated['email'],'password'=>Hash::make($validated['password']),
                'role'=>match($role->code){'tenant-admin'=>'owner','branch-manager'=>'manager','cashier'=>'kasir',default=>'kasir'},
                'default_tenant_id'=>$context->tenantId(),'default_company_id'=>$companyId,'default_branch_id'=>$branchId,
            ]);
            Membership::create(['tenant_id'=>$context->tenantId(),'user_id'=>$user->id,'company_id'=>$companyId,'branch_id'=>$branchId,'location_id'=>$locationId,'role_id'=>$role->id,'status'=>'active','is_primary'=>true]);
            return $user;
        });

        app(AuditService::class)->record('created','users',$user,null,$user->only(['id','name','email']));
        return response()->json(['status'=>'success','data'=>$user->only(['id','name','email'])],201);
    }

    public function destroy($id)
    {
        $context = app(TenantContext::class);
        $membership = Membership::query()->where('tenant_id',$context->tenantId())->where('user_id',$id)->where('status','active')->firstOrFail();
        if ($context->locationId() !== null && (int)$membership->location_id !== (int)$context->locationId()) abort(403,'User berada di luar location scope aktif.');
        abort_if((int)$id === (int)auth()->id(),403,'You cannot remove your own active membership.');
        $old=$membership->only(['tenant_id','user_id','company_id','branch_id','location_id','role_id','status']);
        $membership->update(['status'=>'inactive','is_primary'=>false]);
        app(AuditService::class)->record('membership_revoked','users',$membership,$old,$membership->only(['tenant_id','user_id','company_id','branch_id','location_id','role_id','status']));
        return response()->json(['status'=>'success','message'=>'User membership revoked.']);
    }
}
