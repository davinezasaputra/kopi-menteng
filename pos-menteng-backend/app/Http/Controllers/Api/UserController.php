<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
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
        $tenantId = app(TenantContext::class)->tenantId();
        $users = User::query()
            ->whereHas('memberships', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', 'active'))
            ->with(['memberships' => fn ($q) => $q->where('tenant_id', $tenantId)->where('status', 'active')->with('role')])
            ->orderBy('name')
            ->get();

        return response()->json(['status' => 'success', 'data' => $users]);
    }

    public function store(Request $request)
    {
        $context = app(TenantContext::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role_code' => 'required|string|max:100',
            'company_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
        ]);

        $role = Role::query()->where('tenant_id', $context->tenantId())->where('code', $validated['role_code'])->firstOrFail();
        $companyId = $validated['company_id'] ?? $context->companyId();
        $branchId = $validated['branch_id'] ?? $context->branchId();

        if ($companyId !== null) {
            abort_unless(Company::query()->whereKey($companyId)->where('tenant_id', $context->tenantId())->exists(), 403);
        }
        if ($branchId !== null) {
            abort_unless(Branch::query()->whereKey($branchId)->whereHas('company', fn ($q) => $q->where('tenant_id', $context->tenantId())->whereKey($companyId))->exists(), 403);
        }

        $user = DB::transaction(function () use ($validated, $context, $role, $companyId, $branchId) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => match ($role->code) {
                    'tenant-admin' => 'owner',
                    'branch-manager' => 'manager',
                    'cashier' => 'kasir',
                    default => 'kasir',
                },
                'default_tenant_id' => $context->tenantId(),
                'default_company_id' => $companyId,
                'default_branch_id' => $branchId,
            ]);

            Membership::create([
                'tenant_id' => $context->tenantId(), 'user_id' => $user->id,
                'company_id' => $companyId, 'branch_id' => $branchId,
                'role_id' => $role->id, 'status' => 'active', 'is_primary' => true,
            ]);

            return $user;
        });

        app(AuditService::class)->record('created', 'users', $user, null, $user->only(['id','name','email']));

        return response()->json(['status' => 'success', 'data' => $user->only(['id','name','email'])], 201);
    }

    public function destroy($id)
    {
        $context = app(TenantContext::class);
        $membership = Membership::query()
            ->where('tenant_id', $context->tenantId())
            ->where('user_id', $id)
            ->where('status', 'active')
            ->firstOrFail();

        abort_if((int) $id === (int) auth()->id(), 403, 'You cannot remove your own active membership.');

        $old = $membership->only(['tenant_id','user_id','company_id','branch_id','role_id','status']);
        $membership->update(['status' => 'inactive', 'is_primary' => false]);
        app(AuditService::class)->record('membership_revoked', 'users', $membership, $old, $membership->only(['tenant_id','user_id','company_id','branch_id','role_id','status']));

        return response()->json(['status' => 'success', 'message' => 'User membership revoked.']);
    }
}
