<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationProvisioningController extends Controller
{
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

        return response()->json(['status'=>'success','data'=>Tenant::create($data)], 201);
    }

    public function storeCompany(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required','integer','exists:tenants,id'],
            'code' => ['required','string','max:50'],
            'name' => ['required','string','max:255'],
            'legal_name' => ['nullable','string','max:255'],
            'tax_number' => ['nullable','string','max:255'],
            'email' => ['nullable','email'],
            'phone' => ['nullable','string','max:50'],
            'address' => ['nullable','string'],
            'timezone' => ['nullable','string','max:64'],
            'currency' => ['nullable','string','size:3'],
        ]);

        if (Company::where('tenant_id',$data['tenant_id'])->where('code',$data['code'])->exists()) {
            return response()->json(['message'=>'Company code already exists in this tenant.'],409);
        }
        $data['status']='active';
        $data['timezone'] ??= 'Asia/Jakarta';
        $data['currency'] ??= 'IDR';

        return response()->json(['status'=>'success','data'=>Company::create($data)], 201);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required','integer','exists:companies,id'],
            'code' => ['required','string','max:50'],
            'name' => ['required','string','max:255'],
            'type' => ['nullable','string','max:50'],
            'email' => ['nullable','email'],
            'phone' => ['nullable','string','max:50'],
            'address' => ['nullable','string'],
            'latitude' => ['nullable','numeric'],
            'longitude' => ['nullable','numeric'],
        ]);

        if (Branch::where('company_id',$data['company_id'])->where('code',$data['code'])->exists()) {
            return response()->json(['message'=>'Branch code already exists in this company.'],409);
        }
        $data['status']='active';

        return response()->json(['status'=>'success','data'=>Branch::create($data)], 201);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required','integer','exists:branches,id'],
            'code' => ['required','string','max:50'],
            'name' => ['required','string','max:255'],
            'type' => ['nullable','string','max:50'],
            'is_default' => ['nullable','boolean'],
        ]);

        if (Warehouse::where('branch_id',$data['branch_id'])->where('code',$data['code'])->exists()) {
            return response()->json(['message'=>'Warehouse code already exists in this branch.'],409);
        }
        $data['status']='active';
        $data['type'] ??= 'general';
        $data['is_default'] ??= false;

        return response()->json(['status'=>'success','data'=>Warehouse::create($data)], 201);
    }

    public function showTenant(Request $request, int $tenant): JsonResponse
    {
        $data = Tenant::with(['companies.branches.warehouses'])->findOrFail($tenant);
        return response()->json(['status'=>'success','data'=>$data]);
    }
}
