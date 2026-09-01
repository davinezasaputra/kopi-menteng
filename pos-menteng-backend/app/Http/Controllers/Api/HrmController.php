<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Hrm\Services\PayrollAutomationService;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OperationalExpense;
use App\Models\Payroll;
use App\Models\PayrollNotification;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrmController extends Controller
{
    public function __construct(private readonly TenantContext $context,private readonly AuditService $audit,private readonly PayrollAutomationService $payrollAutomation) {}

    private function employeeQuery()
    {
        $query=Employee::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId());
        if($this->context->locationId()!==null)$query->where('location_id',$this->context->locationId());
        return $query;
    }

    public function summary(){
        $today=Carbon::today()->toDateString();$period=Carbon::now()->format('Y-m');$ids=$this->employeeQuery()->pluck('id');
        return response()->json(['status'=>'success','data'=>['total_employees'=>$ids->count(),'present_today'=>Attendance::whereIn('employee_id',$ids)->whereDate('tanggal',$today)->count(),'late_today'=>Attendance::whereIn('employee_id',$ids)->whereDate('tanggal',$today)->where('status','terlambat')->count(),'pending_payroll'=>Payroll::whereIn('employee_id',$ids)->where('is_paid',false)->count(),'monthly_payroll_total'=>Payroll::whereIn('employee_id',$ids)->where('period',$period)->sum('total_salary')]]);
    }

    public function attendances(Request $request){$ids=$this->employeeQuery()->pluck('id');return response()->json(['status'=>'success','data'=>Attendance::with('employee:id,name,position')->whereIn('employee_id',$ids)->orderByDesc('tanggal')->orderByDesc('clock_in')->paginate(min((int)$request->integer('per_page',50),100))]);}

    public function clockIn(Request $request){
        $validated=$request->validate(['employee_id'=>'required|uuid']);$employee=$this->employeeQuery()->find($validated['employee_id']);
        if(!$employee)return response()->json(['status'=>'error','message'=>'Karyawan tidak ditemukan pada context aktif.'],404);
        $tanggal=Carbon::today()->toDateString();if(Attendance::where('employee_id',$employee->id)->whereDate('tanggal',$tanggal)->exists())return response()->json(['status'=>'error','message'=>'Karyawan ini sudah clock-in hari ini.'],400);
        $attendance=Attendance::create(['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId(),'location_id'=>$employee->location_id,'employee_id'=>$employee->id,'tanggal'=>$tanggal,'clock_in'=>now(),'status'=>'hadir']);
        $this->audit->record('clock_in','hrm.attendance',$attendance,null,$attendance->toArray());return response()->json(['status'=>'success','message'=>'Clock-in berhasil.','data'=>$attendance]);
    }

    public function payrolls(Request $request){$ids=$this->employeeQuery()->pluck('id');return response()->json(['status'=>'success','data'=>Payroll::with('employee:id,name,position')->whereIn('employee_id',$ids)->orderByDesc('period')->orderByDesc('id')->paginate(min((int)$request->integer('per_page',50),100))]);}

    public function generatePayroll(Request $request){
        $v=$request->validate(['employee_id'=>'required|uuid','period'=>['required','date_format:Y-m'],'base_salary'=>'required|numeric|min:0','allowance'=>'nullable|numeric|min:0','deduction'=>'nullable|numeric|min:0','bonus'=>'nullable|numeric|min:0']);
        $employee=$this->employeeQuery()->find($v['employee_id']);if(!$employee)return response()->json(['status'=>'error','message'=>'Karyawan tidak ditemukan pada context aktif.'],404);
        $allowance=(float)($v['allowance']??$v['bonus']??0);$deduction=(float)($v['deduction']??0);$payroll=Payroll::create(['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId(),'location_id'=>$employee->location_id,'employee_id'=>$employee->id,'period'=>$v['period'],'base_salary'=>$v['base_salary'],'allowance'=>$allowance,'deduction'=>$deduction,'total_salary'=>max(0,(float)$v['base_salary']+$allowance-$deduction)]);
        $this->audit->record('created','hrm.payroll',$payroll,null,$payroll->toArray());return response()->json(['status'=>'success','message'=>'Slip gaji berhasil diterbitkan.','data'=>$payroll],201);
    }

    public function getPayrollAutomationConfig(){return response()->json(['status'=>'success','data'=>$this->payrollAutomation->getConfig()]);}
    public function updatePayrollAutomationConfig(Request $request){$v=$request->validate(['enable_auto_fill'=>'sometimes|boolean','enable_whatsapp_notification'=>'sometimes|boolean','whatsapp_recipient_employee'=>'sometimes|boolean','whatsapp_recipient_manager'=>'sometimes|boolean','manager_phone'=>'nullable|string|max:32','notification_timing'=>['sometimes',Rule::in(['immediate','next_day','after_approval'])],'message_template'=>'nullable|string|max:2000']);return response()->json(['status'=>'success','message'=>'Konfigurasi automation payroll disimpan.','data'=>$this->payrollAutomation->updateConfig($v)]);}

    public function generatePayrollAuto(string $id){$payroll=$this->payrollScopedQuery()->findOrFail($id);if($payroll->is_paid)return response()->json(['status'=>'error','message'=>'Payroll yang sudah dibayar tidak dapat di-auto-fill ulang.'],409);return response()->json(['status'=>'success','message'=>'Payroll berhasil diisi otomatis dari data karyawan dan attendance.','data'=>$this->payrollAutomation->autoFillPayroll($payroll),'payroll'=>$payroll->fresh()->load('employee:id,name,position')]);}
    public function sendPayrollWhatsApp(string $id){$payroll=$this->payrollScopedQuery()->with('employee')->findOrFail($id);if(!$payroll->is_paid)return response()->json(['status'=>'error','message'=>'Slip gaji harus berstatus dibayar sebelum dikirim via WhatsApp.'],409);return response()->json(['status'=>'success','message'=>'Pengiriman payroll diproses.','data'=>$this->payrollAutomation->handlePaidPayroll($payroll)]);}

    public function payrollNotifications(Request $request){$n=PayrollNotification::with(['payroll.employee:id,name,position'])->whereHas('payroll',fn($q)=>$q->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->when($this->context->locationId()!==null,fn($q)=>$q->where('location_id',$this->context->locationId())))->orderByDesc('id')->paginate(min((int)$request->integer('per_page',50),100));return response()->json(['status'=>'success','data'=>$n]);}
    public function payrollNotificationStatus(string $id){$n=PayrollNotification::with('payroll.employee')->whereKey($id)->whereHas('payroll',fn($q)=>$q->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->when($this->context->locationId()!==null,fn($q)=>$q->where('location_id',$this->context->locationId())))->firstOrFail();return response()->json(['status'=>'success','data'=>$this->payrollAutomation->syncNotificationStatus($n)]);}

    public function paySalary(string $id){
        $payroll=$this->payrollScopedQuery()->with('employee')->findOrFail($id);if($payroll->is_paid)return response()->json(['status'=>'error','message'=>'Gaji ini sudah ditransfer sebelumnya.'],400);
        DB::transaction(function()use($payroll){$old=$payroll->toArray();$payroll->update(['is_paid'=>true]);OperationalExpense::create(['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId(),'name'=>'Pembayaran Gaji: '.$payroll->employee->name.' (Periode '.$payroll->period.')','amount'=>$payroll->total_salary,'expense_date'=>Carbon::today(),'recorded_by'=>'Sistem HRIS (Otomatis)']);$this->audit->record('paid','hrm.payroll',$payroll,$old,$payroll->fresh()->toArray());});
        try{$automation=$this->payrollAutomation->handlePaidPayroll($payroll->fresh());}catch(\Throwable $e){$this->audit->record('payroll_notification_dispatch_failed','hrm.payroll',$payroll,null,['error'=>$e->getMessage()]);$automation=['status'=>'failed','message'=>'Pembayaran sukses, tetapi automation WhatsApp/PDF gagal diproses.','error'=>$e->getMessage()];}
        return response()->json(['status'=>'success','message'=>'Gaji berhasil dibayar dan pengeluaran tercatat.','automation'=>$automation]);
    }

    private function payrollScopedQuery(){
        $query=Payroll::query()->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId());
        if($this->context->locationId()!==null)$query->where('location_id',$this->context->locationId());
        return $query;
    }
}
