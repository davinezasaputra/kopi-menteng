<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\Employee;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceActionController extends Controller
{
    public function __construct(private readonly TenantContext $context, private readonly AuditService $audit) {}

    private function employeeQuery()
    {
        $query = Employee::query()->where('tenant_id', $this->context->tenantId())->where('company_id', $this->context->companyId())->where('branch_id', $this->context->branchId());
        if ($this->context->locationId() !== null) $query->where('location_id', $this->context->locationId());
        return $query;
    }

    private function setting(): AttendanceSetting
    {
        return AttendanceSetting::firstOrCreate(
            ['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId()],
            ['clock_in_time'=>'08:00:00','clock_in_grace_minutes'=>15,'clock_out_time'=>'17:00:00','clock_out_grace_minutes'=>0,'auto_absence_enabled'=>false],
        );
    }

    public function settings() { return response()->json(['status'=>'success','data'=>$this->setting()]); }

    public function updateSettings(Request $request)
    {
        $v=$request->validate(['clock_in_time'=>['required','date_format:H:i'],'clock_in_grace_minutes'=>['required','integer','min:0','max:240'],'clock_out_time'=>['required','date_format:H:i'],'clock_out_grace_minutes'=>['required','integer','min:0','max:240'],'auto_absence_enabled'=>['required','boolean']]);
        $setting=$this->setting(); $before=$setting->toArray(); $setting->update($v);
        $this->audit->record('updated','hrm.attendance_settings',$setting,$before,$setting->fresh()->toArray());
        return response()->json(['status'=>'success','message'=>'Aturan absensi berhasil disimpan.','data'=>$setting->fresh()]);
    }

    public function penalties()
    {
        $rows=DB::table('attendance_penalties')->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->orderBy('penalty_type')->orderBy('duration_threshold')->get();
        return response()->json(['status'=>'success','data'=>$rows]);
    }

    public function updatePenalties(Request $request)
    {
        $v=$request->validate(['penalties'=>['required','array','max:50'],'penalties.*.penalty_type'=>['required','in:late,absence'],'penalties.*.duration_threshold'=>['required','string','max:20'],'penalties.*.amount_type'=>['required','in:fixed,percentage'],'penalties.*.amount'=>['required','numeric','min:0'],'penalties.*.is_active'=>['sometimes','boolean']]);
        DB::transaction(function() use($v):void { DB::table('attendance_penalties')->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->delete(); foreach($v['penalties'] as $row){DB::table('attendance_penalties')->insert(['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId(),'penalty_type'=>$row['penalty_type'],'duration_threshold'=>$row['duration_threshold'],'amount_type'=>$row['amount_type'],'amount'=>$row['amount'],'is_active'=>(bool)($row['is_active']??true),'created_at'=>now(),'updated_at'=>now()]);}});
        $this->audit->record('updated','hrm.attendance_penalties',$this->setting(),null,['count'=>count($v['penalties'])]);
        return $this->penalties();
    }

    public function clockIn(Request $request)
    {
        $v=$request->validate(['employee_id'=>['required','uuid']]); $employee=$this->employeeQuery()->find($v['employee_id']);
        if(!$employee) return response()->json(['status'=>'error','message'=>'Karyawan tidak ditemukan pada context aktif.'],404);
        $now=Carbon::now('Asia/Jakarta'); $existing=Attendance::where('employee_id',$employee->id)->whereDate('tanggal',$now->toDateString())->first();
        if($existing) return response()->json(['status'=>'error','message'=>'Attendance untuk karyawan ini hari ini sudah ada.'],409);
        $setting=$this->setting(); $rule=Carbon::createFromFormat('Y-m-d H:i:s',$now->toDateString().' '.$setting->clock_in_time,'Asia/Jakarta')->addMinutes((int)$setting->clock_in_grace_minutes);
        $late=$now->greaterThan($rule) ? (int)ceil($rule->diffInSeconds($now)/60) : 0;
        $attendance=Attendance::create(['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId(),'location_id'=>$employee->location_id,'employee_id'=>$employee->id,'tanggal'=>$now->toDateString(),'clock_in'=>$now,'clock_out'=>null,'status'=>$late>0?'terlambat':'hadir','late_minute'=>$late,'early_leave_minute'=>0,'notes'=>$late>0?'Clock-in melewati batas toleransi.':null]);
        $this->audit->record('clock_in','hrm.attendance',$attendance,null,$attendance->toArray());
        return response()->json(['status'=>'success','message'=>$late>0?'Clock-in tercatat sebagai terlambat.':'Clock-in berhasil.','data'=>$attendance],201);
    }

    public function clockOut(Request $request)
    {
        $v=$request->validate(['employee_id'=>['required','uuid']]); $employee=$this->employeeQuery()->find($v['employee_id']);
        if(!$employee) return response()->json(['status'=>'error','message'=>'Karyawan tidak ditemukan pada context aktif.'],404);
        $now=Carbon::now('Asia/Jakarta'); $attendance=Attendance::where('employee_id',$employee->id)->whereDate('tanggal',$now->toDateString())->first();
        if(!$attendance) return response()->json(['status'=>'error','message'=>'Belum ada attendance hari ini.'],422); if($attendance->clock_out) return response()->json(['status'=>'error','message'=>'Clock-out hari ini sudah tercatat.'],409);
        $setting=$this->setting(); $rule=Carbon::createFromFormat('Y-m-d H:i:s',$now->toDateString().' '.$setting->clock_out_time,'Asia/Jakarta')->subMinutes((int)$setting->clock_out_grace_minutes);
        $early=$now->lessThan($rule) ? (int)ceil($now->diffInSeconds($rule)/60) : 0; $before=$attendance->toArray();
        $attendance->update(['clock_out'=>$now,'early_leave_minute'=>$early,'status'=>$early>0&&$attendance->status==='hadir'?'pulang_cepat':$attendance->status]);
        $this->audit->record('clock_out','hrm.attendance',$attendance,$before,$attendance->fresh()->toArray());
        return response()->json(['status'=>'success','message'=>$early>0?'Clock-out tercatat sebagai pulang cepat.':'Clock-out berhasil.','data'=>$attendance->fresh()]);
    }

    public function setStatus(Request $request,string $id)
    {
        $v=$request->validate(['status'=>['required','in:hadir,sakit,terlambat,absen'],'late_minute'=>['nullable','integer','min:0','max:1440'],'notes'=>['nullable','string','max:1000']]);
        $attendance=Attendance::query()->where('id',$id)->where('tenant_id',$this->context->tenantId())->where('company_id',$this->context->companyId())->where('branch_id',$this->context->branchId())->firstOrFail();
        if($this->context->locationId()!==null&&(int)$attendance->location_id!==(int)$this->context->locationId()) abort(404);
        $before=$attendance->toArray(); $attendance->update(['status'=>$v['status'],'late_minute'=>(int)($v['late_minute']??0),'notes'=>$v['notes']??$attendance->notes,'clock_in'=>in_array($v['status'],['sakit','absen'],true)?null:$attendance->clock_in]);
        $this->audit->record('status_changed','hrm.attendance',$attendance,$before,$attendance->fresh()->toArray());
        return response()->json(['status'=>'success','message'=>'Status attendance diperbarui.','data'=>$attendance->fresh()]);
    }

    public function offDuty(Request $request)
    {
        $v=$request->validate(['employee_id'=>['required','uuid'],'tanggal'=>['required','date'],'notes'=>['required','string','max:1000']]); $employee=$this->employeeQuery()->find($v['employee_id']);
        if(!$employee) return response()->json(['status'=>'error','message'=>'Karyawan tidak ditemukan pada context aktif.'],404);
        $attendance=Attendance::updateOrCreate(['employee_id'=>$employee->id,'tanggal'=>$v['tanggal']],['tenant_id'=>$this->context->tenantId(),'company_id'=>$this->context->companyId(),'branch_id'=>$this->context->branchId(),'location_id'=>$employee->location_id,'clock_in'=>null,'clock_out'=>null,'status'=>'offduty','late_minute'=>0,'early_leave_minute'=>0,'notes'=>$v['notes']]);
        $this->audit->record('offduty','hrm.attendance',$attendance,null,$attendance->fresh()->toArray());
        return response()->json(['status'=>'success','message'=>'Off-duty berhasil dicatat.','data'=>$attendance->fresh()],201);
    }

    public function export(Request $request)
    {
        $v=$request->validate(['year'=>['required','integer','min:2000','max:2100'],'month'=>['required','integer','min:1','max:12']]); $ids=$this->employeeQuery()->pluck('id'); $rows=Attendance::with('employee:id,name,position')->whereIn('employee_id',$ids)->whereYear('tanggal',$v['year'])->whereMonth('tanggal',$v['month'])->orderBy('tanggal')->get(); $filename='attendance_'.$v['year'].'_'.str_pad((string)$v['month'],2,'0',STR_PAD_LEFT).'.csv';
        return response()->streamDownload(function() use($rows):void { $out=fopen('php://output','w'); fputcsv($out,['Tanggal','Karyawan','Jabatan','Status','Clock In','Clock Out','Terlambat (menit)','Pulang Cepat (menit)','Catatan']); foreach($rows as $row){fputcsv($out,[$row->tanggal?->format('Y-m-d'),$row->employee?->name,$row->employee?->position,$row->status,$row->clock_in?->format('H:i:s'),$row->clock_out?->format('H:i:s'),$row->late_minute,$row->early_leave_minute,$row->notes]);} fclose($out); },$filename,['Content-Type'=>'text/csv; charset=UTF-8']);
    }
}
