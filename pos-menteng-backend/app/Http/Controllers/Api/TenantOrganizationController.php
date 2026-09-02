<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use App\Support\Responses\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantOrganizationController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly AuditService $audit) {}
    public function index(){return ApiResponse::success(Company::query()->where('tenant_id',$this->context->tenantId())->with(['branches.warehouses'])->orderBy('name')->get());}
    public function storeCompany(Request $request){
        $data=$request->validate(['code'=>['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'],'name'=>['required','string','max:255'],'legal_name'=>['nullable','string','max:255'],'tax_number'=>['nullable','string','max:255'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'timezone'=>['nullable','string','max:64'],'currency'=>['nullable','string','size:3']]);
        if(Company::query()->where('tenant_id',$this->context->tenantId())->where('code',$data['code'])->exists())return response()->json(['status'=>'error','message'=>'Company code sudah digunakan pada tenant ini.'],409);
        $company=DB::transaction(function()use($data){$data['tenant_id']=$this->context->tenantId();$data['status']='active';$data['timezone']=$data['timezone']??'Asia/Jakarta';$data['currency']=$data['currency']??'IDR';$company=Company::create($data);$this->audit->record('created','organization.company',$company,null,$company->toArray());return $company->fresh();});
        return ApiResponse::success($company,'Company berhasil ditambahkan.',201);
    }
    public function storeBranch(Request $request){
        $data=$request->validate(['company_id'=>['required','integer','exists:companies,id'],'code'=>['required','string','max:50','regex:/^[A-Za-z0-9._-]+$/'],'name'=>['required','string','max:255'],'type'=>['nullable','string','max:50'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric','between:-90,90'],'longitude'=>['nullable','numeric','between:-180,180']]);
        $company=Company::query()->whereKey($data['company_id'])->where('tenant_id',$this->context->tenantId())->first();
        if(!$company)return response()->json(['status'=>'error','message'=>'Company berada di luar tenant aktif.'],403);
        if(Branch::query()->where('company_id',$company->id)->where('code',$data['code'])->exists())return response()->json(['status'=>'error','message'=>'Branch code sudah digunakan pada company ini.'],409);
        $branch=DB::transaction(function()use($data){$data['status']='active';$branch=Branch::create($data);Warehouse::create(['branch_id'=>$branch->id,'code'=>'MAIN','name'=>'Main Warehouse','type'=>'store','is_default'=>true,'status'=>'active']);$this->audit->record('created','organization.branch',$branch,null,$branch->fresh()->toArray());return $branch->fresh()->load('warehouses');});
        return ApiResponse::success($branch,'Branch berhasil ditambahkan beserta warehouse MAIN.',201);
    }
}
