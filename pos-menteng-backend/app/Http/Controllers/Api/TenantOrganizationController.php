<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantOrganizationController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly AuditService $audit) {}

    private function company(int $id): Company
    {
        return Company::query()->whereKey($id)->where('tenant_id', $this->context->tenantId())->firstOrFail();
    }

    private function branch(int $id): Branch
    {
        return Branch::query()->whereKey($id)
            ->whereHas('company', fn ($q) => $q->where('tenant_id', $this->context->tenantId()))
            ->firstOrFail();
    }

    public function index()
    {
        return ApiResponse::success(
            Company::query()->where('tenant_id', $this->context->tenantId())
                ->with(['branches.warehouses', 'branches.locations'])
                ->orderBy('name')->get()
        );
    }

    public function storeCompany(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'], 'name' => ['required','string','max:255'],
            'legal_name' => ['nullable','string','max:255'], 'tax_number' => ['nullable','string','max:255'],
            'email' => ['nullable','email','max:255'], 'phone' => ['nullable','string','max:50'], 'address' => ['nullable','string'],
            'timezone' => ['nullable','string','max:64'], 'currency' => ['nullable','string','size:3'],
        ]);
        if (Company::query()->where('tenant_id',$this->context->tenantId())->where('code',$data['code'])->exists()) {
            return response()->json(['status'=>'error','message'=>'Company code sudah digunakan pada tenant ini.'],409);
        }
        $company = DB::transaction(function () use ($data) {
            $data['tenant_id']=$this->context->tenantId(); $data['status']='active'; $data['timezone']=$data['timezone']??'Asia/Jakarta'; $data['currency']=$data['currency']??'IDR';
            $company=Company::create($data); $this->audit->record('created','organization.company',$company,null,$company->toArray()); return $company->fresh();
        });
        return ApiResponse::success($company,'Company berhasil ditambahkan.',201);
    }

    public function updateCompany(Request $request, int $id)
    {
        $company=$this->company($id);
        $data=$request->validate(['code'=>['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'],'name'=>['required','string','max:255'],'legal_name'=>['nullable','string','max:255'],'tax_number'=>['nullable','string','max:255'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'timezone'=>['nullable','string','max:64'],'currency'=>['nullable','string','size:3'],'status'=>['sometimes','in:active,inactive']]);
        if(Company::query()->where('tenant_id',$this->context->tenantId())->where('code',$data['code'])->where('id','<>',$id)->exists()) return response()->json(['status'=>'error','message'=>'Company code sudah digunakan pada tenant ini.'],409);
        $before=$company->toArray(); $company->update($data); $this->audit->record('updated','organization.company',$company,$before,$company->fresh()->toArray());
        return ApiResponse::success($company->fresh(),'Company berhasil diperbarui.');
    }

    public function storeBranch(Request $request)
    {
        $data=$request->validate(['company_id'=>['required','integer','exists:companies,id'],'code'=>['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'],'name'=>['required','string','max:255'],'type'=>['nullable','string','max:50'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric','between:-90,90'],'longitude'=>['nullable','numeric','between:-180,180']]);
        $company=$this->company((int)$data['company_id']);
        if(Branch::query()->where('company_id',$company->id)->where('code',$data['code'])->exists()) return response()->json(['status'=>'error','message'=>'Branch code sudah digunakan pada company ini.'],409);
        $branch=DB::transaction(function()use($data){$data['status']='active';$branch=Branch::create($data);Warehouse::create(['branch_id'=>$branch->id,'code'=>'MAIN','name'=>'Main Warehouse','type'=>'store','is_default'=>true,'status'=>'active']);$this->audit->record('created','organization.branch',$branch,null,$branch->fresh()->toArray());return $branch->fresh()->load(['warehouses','locations']);});
        return ApiResponse::success($branch,'Branch berhasil ditambahkan beserta warehouse MAIN.',201);
    }

    public function updateBranch(Request $request, int $id)
    {
        $branch=$this->branch($id);
        $data=$request->validate(['code'=>['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'],'name'=>['required','string','max:255'],'type'=>['nullable','string','max:50'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric','between:-90,90'],'longitude'=>['nullable','numeric','between:-180,180'],'status'=>['sometimes','in:active,inactive']]);
        if(Branch::query()->where('company_id',$branch->company_id)->where('code',$data['code'])->where('id','<>',$id)->exists()) return response()->json(['status'=>'error','message'=>'Branch code sudah digunakan pada company ini.'],409);
        $before=$branch->toArray(); $branch->update($data); $this->audit->record('updated','organization.branch',$branch,$before,$branch->fresh()->toArray());
        return ApiResponse::success($branch->fresh()->load(['warehouses','locations']),'Branch berhasil diperbarui.');
    }

    public function storeLocation(Request $request, int $branchId)
    {
        $branch=$this->branch($branchId);
        $data=$request->validate(['code'=>['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'],'name'=>['required','string','max:255'],'type'=>['required','in:store,warehouse,office'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric','between:-90,90'],'longitude'=>['nullable','numeric','between:-180,180'],'settings'=>['nullable','array']]);
        if(Location::query()->where('branch_id',$branch->id)->where('code',$data['code'])->exists()) return response()->json(['status'=>'error','message'=>'Location code sudah digunakan pada branch ini.'],409);
        $data['branch_id']=$branch->id;$data['status']='active';$location=Location::create($data);$this->audit->record('created','organization.location',$location,null,$location->toArray());
        return ApiResponse::success($location,'Location berhasil ditambahkan.',201);
    }

    public function updateLocation(Request $request, int $id)
    {
        $location=Location::query()->whereKey($id)->whereHas('branch.company',fn($q)=>$q->where('tenant_id',$this->context->tenantId()))->firstOrFail();
        $data=$request->validate(['code'=>['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'],'name'=>['required','string','max:255'],'type'=>['required','in:store,warehouse,office'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric','between:-90,90'],'longitude'=>['nullable','numeric','between:-180,180'],'settings'=>['nullable','array'],'status'=>['sometimes','in:active,inactive']]);
        if(Location::query()->where('branch_id',$location->branch_id)->where('code',$data['code'])->where('id','<>',$id)->exists()) return response()->json(['status'=>'error','message'=>'Location code sudah digunakan pada branch ini.'],409);
        $before=$location->toArray();$location->update($data);$this->audit->record('updated','organization.location',$location,$before,$location->fresh()->toArray());
        return ApiResponse::success($location->fresh(),'Location berhasil diperbarui.');
    }

    public function destroyLocation(int $id)
    {
        $location=Location::query()->whereKey($id)->whereHas('branch.company',fn($q)=>$q->where('tenant_id',$this->context->tenantId()))->firstOrFail();
        $before=$location->toArray();$location->update(['status'=>'inactive']);$this->audit->record('deactivated','organization.location',$location,$before,$location->fresh()->toArray());
        return ApiResponse::success($location->fresh(),'Location dinonaktifkan.');
    }
}
