<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Organization\Models\Location;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {}

    private function scopedQuery()
    {
        $query=Employee::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId());
        if ($this->context->locationId() !== null) $query->where('location_id',$this->context->locationId());
        return $query;
    }

    private function requireLocation(?int $locationId): ?int
    {
        $active=$this->context->locationId();
        $locationId=$locationId ?? $active;
        if ($locationId === null) return null;
        abort_unless(Location::query()->whereKey($locationId)->where('branch_id',$this->context->branchId())->whereHas('branch.company',fn($q)=>$q->where('id',$this->context->companyId())->where('tenant_id',$this->context->tenantId()))->exists(),403,'Location berada di luar scope aktif.');
        if ($active !== null) abort_unless($active === $locationId,403,'Location berada di luar scope aktif.');
        return $locationId;
    }

    public function index(Request $request)
    { return response()->json(['status'=>'success','data'=>$this->scopedQuery()->with('location')->orderByDesc('created_at')->paginate(min((int)$request->integer('per_page',50),100))]); }

    public function show(string $id)
    {
        $employee=$this->scopedQuery()->with('location')->find($id);
        if(!$employee) return response()->json(['status'=>'error','message'=>'Karyawan tidak ditemukan.'],404);
        return response()->json(['status'=>'success','data'=>$employee]);
    }

    public function search(Request $request)
    {
        $keyword=trim((string)$request->query('q','')); $query=$this->scopedQuery();
        if($keyword!=='') $query->where(fn($b)=>$b->where('name','like',"%{$keyword}%")->orWhere('WA','like',"%{$keyword}%")->orWhere('position','like',"%{$keyword}%"));
        return response()->json(['status'=>'success','data'=>$query->with('location')->orderByDesc('created_at')->paginate(min((int)$request->integer('per_page',50),100))]);
    }

    public function store(Request $request)
    {
        $validated=$request->validate(['name'=>'required|string|max:100','tanggal_lahir'=>'nullable|date','WA'=>'required|string|max:20','position'=>'required|string|max:50','join_date'=>'nullable|date','base_sallary'=>'required|numeric|min:0','status'=>'nullable|in:active,inactive','location_id'=>'nullable|integer']);
        $validated += ['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId()];
        $validated['location_id']=$this->requireLocation($validated['location_id'] ?? null); $validated['id']=(string)Str::uuid();
        $employee=Employee::create($validated); $this->audit->record('created','hrm.employee',$employee,null,$employee->toArray());
        return response()->json(['status'=>'success','message'=>'Data karyawan berhasil ditambahkan.','data'=>$employee->load('location')],201);
    }

    public function update(Request $request,string $id)
    {
        $employee=$this->scopedQuery()->find($id); if(!$employee) return response()->json(['status'=>'error','message'=>'Karyawan tidak ditemukan.'],404);
        $validated=$request->validate(['name'=>'sometimes|required|string|max:100','tanggal_lahir'=>'nullable|date','WA'=>'sometimes|required|string|max:20','position'=>'sometimes|required|string|max:50','join_date'=>'nullable|date','base_sallary'=>'sometimes|required|numeric|min:0','status'=>'nullable|in:active,inactive','location_id'=>'nullable|integer']);
        if(array_key_exists('location_id',$validated)) $validated['location_id']=$this->requireLocation($validated['location_id']);
        $old=$employee->toArray(); $employee->update($validated); $this->audit->record('updated','hrm.employee',$employee,$old,$employee->fresh()->toArray());
        return response()->json(['status'=>'success','message'=>'Data karyawan berhasil diperbarui.','data'=>$employee->fresh()->load('location')]);
    }

    public function destroy(string $id)
    {
        $employee=$this->scopedQuery()->find($id); if(!$employee) return response()->json(['status'=>'error','message'=>'Data karyawan tidak ditemukan.'],404);
        $old=$employee->toArray(); $employee->delete(); $this->audit->record('deleted','hrm.employee',$employee,$old,null);
        return response()->json(['status'=>'success','message'=>'Data karyawan berhasil dihapus.']);
    }
}
