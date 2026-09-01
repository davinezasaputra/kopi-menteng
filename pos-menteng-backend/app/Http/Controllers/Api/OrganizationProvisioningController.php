<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Identity\Models\Membership;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\TenantLicense;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OrganizationProvisioningController extends Controller
{
    private const ROLE_DEFINITIONS = [
        'tenant-admin' => ['name' => 'Tenant Admin', 'permissions' => null],
        'branch-manager' => ['name' => 'Branch Manager', 'permissions' => ['pos.sale.view','pos.sale.create','inventory.stock.view','inventory.stock.adjust','organization.branch.view','hr.employee.view']],
        'cashier' => ['name' => 'Cashier', 'permissions' => ['pos.sale.view','pos.sale.create']],
        'accountant' => ['name' => 'Accountant', 'permissions' => ['accounting.journal.view','accounting.journal.create','accounting.journal.post']],
        'warehouse-manager' => ['name' => 'Warehouse Manager', 'permissions' => ['inventory.stock.view','inventory.stock.adjust']],
        'hr-manager' => ['name' => 'HR Manager', 'permissions' => ['hr.employee.view','hr.employee.manage']],
    ];

    public function storeTenant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'code' => ['required','string','max:50','unique:tenants,code'],
            'timezone' => ['nullable','string','max:64'],
            'currency' => ['nullable','string','size:3'],
        ]);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower($data['code']);
        $data['status'] = 'active';
        $data['timezone'] ??= 'Asia/Jakarta';
        $data['currency'] ??= 'IDR';
        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create($data);
            $this->provisionStandardRoles($tenant);
            return $tenant->fresh();
        });
        return response()->json(['status'=>'success','data'=>$tenant], 201);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required','integer','exists:tenants,id'], 'code' => ['required','string','max:50'], 'name' => ['required','string','max:255'],
            'legal_name' => ['nullable','string','max:255'], 'tax_number' => ['nullable','string','max:255'], 'email' => ['nullable','email'], 'phone' => ['nullable','string','max:50'],
            'address' => ['nullable','string'], 'timezone' => ['nullable','string','max:64'], 'currency' => ['nullable','string','size:3'],
        ]);
        if (Company::where('tenant_id',$data['tenant_id'])->where('code',$data['code'])->exists()) return response()->json(['message'=>'Company code already exists in this tenant.'],409);
        $data['status']='active'; $data['timezone'] ??= 'Asia/Jakarta'; $data['currency'] ??= 'IDR';
        return response()->json(['status'=>'success','data'=>Company::create($data)], 201);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required','integer','exists:companies,id'], 'code' => ['required','string','max:50'], 'name' => ['required','string','max:255'],
            'type' => ['nullable','string','max:50'], 'email' => ['nullable','email'], 'phone' => ['nullable','string','max:50'], 'address' => ['nullable','string'],
            'latitude' => ['nullable','numeric'], 'longitude' => ['nullable','numeric'],
        ]);

        $company = Company::query()->findOrFail($data['company_id']);
        $license = TenantLicense::query()->where('tenant_id', $company->tenant_id)->first();
        if ($license && $license->max_branches !== null) {
            $branchCount = Branch::query()->whereHas('company', fn ($q) => $q->where('tenant_id', $company->tenant_id))->count();
            if ($branchCount >= $license->max_branches) {
                return response()->json(['status' => 'error', 'message' => "Batas branch lisensi tercapai ({$license->max_branches}). Upgrade lisensi untuk menambah branch."], 422);
            }
        }

        if (Branch::where('company_id',$data['company_id'])->where('code',$data['code'])->exists()) return response()->json(['message'=>'Branch code already exists in this company.'],409);
        $data['status']='active';
        return response()->json(['status'=>'success','data'=>Branch::create($data)], 201);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required','integer','exists:branches,id'], 'code' => ['required','string','max:50'], 'name' => ['required','string','max:255'],
            'type' => ['nullable','string','max:50'], 'is_default' => ['nullable','boolean'],
        ]);
        if (Warehouse::where('branch_id',$data['branch_id'])->where('code',$data['code'])->exists()) return response()->json(['message'=>'Warehouse code already exists in this branch.'],409);
        $data['status']='active'; $data['type'] ??= 'general'; $data['is_default'] ??= false;
        return response()->json(['status'=>'success','data'=>Warehouse::create($data)], 201);
    }

    public function storeTenantAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required','integer','exists:tenants,id'], 'company_id' => ['required','integer','exists:companies,id'], 'branch_id' => ['required','integer','exists:branches,id'],
            'name' => ['required','string','max:255'], 'email' => ['required','email','unique:users,email'], 'password' => ['required','string','min:8'],
        ]);
        $company = Company::query()->whereKey($data['company_id'])->where('tenant_id',$data['tenant_id'])->firstOrFail();
        $branch = Branch::query()->whereKey($data['branch_id'])->where('company_id',$company->id)->firstOrFail();
        $license = TenantLicense::query()->where('tenant_id', $data['tenant_id'])->first();
        if ($license && $license->max_users !== null) {
            $activeUsers = Membership::query()->where('tenant_id', $data['tenant_id'])->where('status', 'active')->count();
            if ($activeUsers >= $license->max_users) {
                return response()->json(['status' => 'error', 'message' => "Batas user lisensi tercapai ({$license->max_users}). Upgrade lisensi untuk menambah user."], 422);
            }
        }

        return DB::transaction(function () use ($data, $branch) {
            $tenant = Tenant::findOrFail($data['tenant_id']);
            $roles = $this->provisionStandardRoles($tenant);
            $role = $roles['tenant-admin'];
            $user = User::create([
                'name'=>$data['name'], 'email'=>$data['email'], 'password'=>Hash::make($data['password']), 'role'=>'owner',
                'default_tenant_id'=>$data['tenant_id'], 'default_company_id'=>$data['company_id'], 'default_branch_id'=>$branch->id,
            ]);
            Membership::create([
                'tenant_id'=>$data['tenant_id'], 'user_id'=>$user->id, 'company_id'=>$data['company_id'], 'branch_id'=>$branch->id,
                'role_id'=>$role->id, 'status'=>'active', 'is_primary'=>true,
            ]);
            app(AuditService::class)->record('created','identity',$user,null,$user->only(['id','name','email']));
            return response()->json(['status'=>'success','message'=>'Tenant admin account created.','data'=>[
                'user'=>$user->only(['id','name','email']),
                'membership'=>['tenant_id'=>$data['tenant_id'],'company_id'=>$data['company_id'],'branch_id'=>$branch->id,'role_code'=>$role->code],
            ]],201);
        });
    }

    public function showTenant(Request $request, int $tenant): JsonResponse
    {
        return response()->json(['status'=>'success','data'=>Tenant::with(['companies.branches.warehouses'])->findOrFail($tenant)]);
    }

    private function provisionStandardRoles(Tenant $tenant): array
    {
        $roles = [];
        $allPermissions = Permission::query()->pluck('id','name');
        foreach (self::ROLE_DEFINITIONS as $code => $definition) {
            $role = Role::firstOrCreate(['tenant_id'=>$tenant->id,'code'=>$code],[
                'name'=>$definition['name'], 'description'=>null, 'is_system'=>true,
            ]);
            $names = $definition['permissions'] ?? $allPermissions->keys()->all();
            $ids = collect($names)->map(fn(string $name) => $allPermissions->get($name))->filter()->values()->all();
            $role->permissions()->sync($ids);
            $roles[$code] = $role;
        }
        return $roles;
    }
}
