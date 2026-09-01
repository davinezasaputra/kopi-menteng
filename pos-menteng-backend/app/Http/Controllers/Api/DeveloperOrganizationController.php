<?php

namespace App\Http\Controllers\Api;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Company;
use App\Domain\Organization\Models\Location;
use App\Domain\Organization\Models\Tenant;
use App\Domain\Organization\Models\Warehouse;
use App\Http\Controllers\Controller;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeveloperOrganizationController extends Controller
{
    public function show(int $tenant): JsonResponse
    {
        $record = Tenant::query()
            ->with(['companies' => fn ($query) => $query->orderBy('name'), 'companies.branches' => fn ($query) => $query->orderBy('name'), 'companies.branches.locations' => fn ($query) => $query->orderBy('type')->orderBy('name'), 'companies.branches.warehouses' => fn ($query) => $query->orderBy('name')])
            ->findOrFail($tenant);
        return response()->json(['status'=>'success','data'=>$record]);
    }

    public function storeCompany(Request $request, int $tenant): JsonResponse
    {
        $tenantRecord = Tenant::query()->findOrFail($tenant);
        $data = $request->validate(['code'=>['required','string','max:50'],'name'=>['required','string','max:255'],'legal_name'=>['nullable','string','max:255'],'tax_number'=>['nullable','string','max:100'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'timezone'=>['nullable','string','max:64'],'currency'=>['nullable','string','size:3'],'status'=>['nullable',Rule::in(['active','inactive','suspended'])]]);
        $data['code']=strtoupper($data['code']); $data['tenant_id']=$tenant; $data['status']??='active'; $data['timezone']??=$tenantRecord->timezone; $data['currency']??=$tenantRecord->currency;
        return response()->json(['status'=>'success','data'=>Company::create($data)],201);
    }

    public function updateCompany(Request $request, int $tenant, int $company): JsonResponse
    {
        $record=Company::query()->where('tenant_id',$tenant)->findOrFail($company);
        $data=$request->validate(['code'=>['required','string','max:50'],'name'=>['required','string','max:255'],'legal_name'=>['nullable','string','max:255'],'tax_number'=>['nullable','string','max:100'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'timezone'=>['required','string','max:64'],'currency'=>['required','string','size:3'],'status'=>['required',Rule::in(['active','inactive','suspended'])]]);
        $data['code']=strtoupper($data['code']); $record->update($data); return response()->json(['status'=>'success','data'=>$record->fresh()]);
    }

    public function storeBranch(Request $request, int $tenant, int $company): JsonResponse
    {
        $companyRecord=Company::query()->where('tenant_id',$tenant)->findOrFail($company);
        $data=$request->validate(['code'=>['required','string','max:50'],'name'=>['required','string','max:255'],'type'=>['nullable','string','max:50'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric'],'longitude'=>['nullable','numeric'],'status'=>['nullable',Rule::in(['active','inactive','suspended'])]]);
        $data['code']=strtoupper($data['code']); $data['company_id']=$companyRecord->id; $data['status']??='active'; $data['type']??='store';
        return response()->json(['status'=>'success','data'=>Branch::create($data)],201);
    }

    public function updateBranch(Request $request, int $tenant, int $company, int $branch): JsonResponse
    {
        $record=Branch::query()->where('company_id',$company)->whereHas('company',fn($query)=>$query->where('tenant_id',$tenant))->findOrFail($branch);
        $data=$request->validate(['code'=>['required','string','max:50'],'name'=>['required','string','max:255'],'type'=>['nullable','string','max:50'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric'],'longitude'=>['nullable','numeric'],'status'=>['required',Rule::in(['active','inactive','suspended'])]]);
        $data['code']=strtoupper($data['code']); $record->update($data); return response()->json(['status'=>'success','data'=>$record->fresh()]);
    }

    public function storeLocation(Request $request, int $tenant, int $company, int $branch): JsonResponse
    {
        $branchRecord=Branch::query()->where('company_id',$company)->whereHas('company',fn($query)=>$query->where('tenant_id',$tenant))->findOrFail($branch);
        $data=$request->validate(['code'=>['required','string','max:50'],'name'=>['required','string','max:255'],'type'=>['required',Rule::in(['store','warehouse','office'])],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric'],'longitude'=>['nullable','numeric'],'status'=>['nullable',Rule::in(['active','inactive','suspended'])],'settings'=>['nullable','array']]);
        $data['code']=strtoupper($data['code']); $data['branch_id']=$branchRecord->id; $data['status']??='active';

        return app(DatabaseManager::class)->transaction(function () use ($data, $branchRecord): JsonResponse {
            $location=Location::create($data);
            $warehouse=null;
            if ($location->type==='warehouse') {
                $warehouse=Warehouse::create([
                    'branch_id'=>$branchRecord->id,
                    'location_id'=>$location->id,
                    'code'=>$location->code,
                    'name'=>$location->name,
                    'type'=>'warehouse',
                    'is_default'=>false,
                    'status'=>$location->status,
                ]);
            }
            return response()->json(['status'=>'success','data'=>$location->fresh(),'warehouse'=>$warehouse],201);
        });
    }

    public function updateLocation(Request $request, int $tenant, int $company, int $branch, int $location): JsonResponse
    {
        $record=Location::query()->where('branch_id',$branch)->whereHas('branch.company',fn($query)=>$query->where('id',$company)->where('tenant_id',$tenant))->findOrFail($location);
        $data=$request->validate(['code'=>['required','string','max:50'],'name'=>['required','string','max:255'],'type'=>['required',Rule::in(['store','warehouse','office'])],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'address'=>['nullable','string'],'latitude'=>['nullable','numeric'],'longitude'=>['nullable','numeric'],'status'=>['required',Rule::in(['active','inactive','suspended'])],'settings'=>['nullable','array']]);
        $oldType=$record->type; $data['code']=strtoupper($data['code']); $record->update($data);

        if ($record->type==='warehouse') {
            Warehouse::query()->updateOrCreate(['location_id'=>$record->id],['branch_id'=>$branch,'code'=>$record->code,'name'=>$record->name,'type'=>'warehouse','status'=>$record->status]);
        } elseif ($oldType==='warehouse') {
            Warehouse::query()->where('location_id',$record->id)->update(['status'=>'inactive']);
        }
        return response()->json(['status'=>'success','data'=>$record->fresh()]);
    }
}
