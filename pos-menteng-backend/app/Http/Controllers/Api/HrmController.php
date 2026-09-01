<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OperationalExpense;
use App\Models\Payroll;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrmController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {
    }

    private function employeeQuery()
    {
        return Employee::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId());
    }

    public function summary()
    {
        $today = Carbon::today()->toDateString();
        $currentPeriod = Carbon::now()->format('Y-m');
        $employeeIds = $this->employeeQuery()->pluck('id');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_employees' => $employeeIds->count(),
                'present_today' => Attendance::whereIn('employee_id', $employeeIds)->whereDate('tanggal', $today)->count(),
                'late_today' => Attendance::whereIn('employee_id', $employeeIds)->whereDate('tanggal', $today)->where('status', 'terlambat')->count(),
                'pending_payroll' => Payroll::whereIn('employee_id', $employeeIds)->where('is_paid', false)->count(),
                'monthly_payroll_total' => Payroll::whereIn('employee_id', $employeeIds)->where('period', $currentPeriod)->sum('total_salary'),
            ],
        ]);
    }

    public function attendances(Request $request)
    {
        $employeeIds = $this->employeeQuery()->pluck('id');
        $attendances = Attendance::with('employee:id,name,position')
            ->whereIn('employee_id', $employeeIds)
            ->orderByDesc('tanggal')->orderByDesc('clock_in')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json(['status' => 'success', 'data' => $attendances]);
    }

    public function clockIn(Request $request)
    {
        $validated = $request->validate(['employee_id' => 'required|uuid']);
        $employee = $this->employeeQuery()->find($validated['employee_id']);
        if (! $employee) return response()->json(['status' => 'error', 'message' => 'Karyawan tidak ditemukan pada context aktif.'], 404);

        $tanggal = Carbon::today()->toDateString();
        $already = Attendance::where('employee_id', $employee->id)->whereDate('tanggal', $tanggal)->exists();
        if ($already) return response()->json(['status' => 'error', 'message' => 'Karyawan ini sudah clock-in hari ini.'], 400);

        $attendance = Attendance::create([
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
            'employee_id' => $employee->id,
            'tanggal' => $tanggal,
            'clock_in' => now(),
            'status' => 'hadir',
        ]);

        $this->audit->record('clock_in', 'hrm.attendance', $attendance, null, $attendance->toArray());
        return response()->json(['status' => 'success', 'message' => 'Clock-in berhasil.', 'data' => $attendance]);
    }

    public function payrolls(Request $request)
    {
        $employeeIds = $this->employeeQuery()->pluck('id');
        $payrolls = Payroll::with('employee:id,name,position')
            ->whereIn('employee_id', $employeeIds)
            ->orderByDesc('period')->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json(['status' => 'success', 'data' => $payrolls]);
    }

    public function generatePayroll(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid',
            'period' => 'required|string|max:20',
            'base_salary' => 'required|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'deduction' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
        ]);
        $employee = $this->employeeQuery()->find($validated['employee_id']);
        if (! $employee) return response()->json(['status' => 'error', 'message' => 'Karyawan tidak ditemukan pada context aktif.'], 404);

        $allowance = (float) ($validated['allowance'] ?? $validated['bonus'] ?? 0);
        $deduction = (float) ($validated['deduction'] ?? 0);
        $data = [
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
            'employee_id' => $employee->id,
            'period' => $validated['period'],
            'base_salary' => $validated['base_salary'],
            'allowance' => $allowance,
            'deduction' => $deduction,
            'total_salary' => (float) $validated['base_salary'] + $allowance - $deduction,
        ];

        $payroll = Payroll::create($data);
        $this->audit->record('created', 'hrm.payroll', $payroll, null, $payroll->toArray());
        return response()->json(['status' => 'success', 'message' => 'Slip gaji berhasil diterbitkan.', 'data' => $payroll], 201);
    }

    public function paySalary(string $id)
    {
        $employeeIds = $this->employeeQuery()->pluck('id');
        $payroll = Payroll::with('employee')->whereIn('employee_id', $employeeIds)->findOrFail($id);
        if ($payroll->is_paid) return response()->json(['status' => 'error', 'message' => 'Gaji ini sudah ditransfer sebelumnya.'], 400);

        DB::transaction(function () use ($payroll) {
            $old = $payroll->toArray();
            $payroll->update(['is_paid' => true]);
            OperationalExpense::create([
                'tenant_id' => $this->context->tenantId(),
                'company_id' => $this->context->companyId(),
                'branch_id' => $this->context->branchId(),
                'name' => 'Pembayaran Gaji: ' . $payroll->employee->name . ' (Periode ' . $payroll->period . ')',
                'amount' => $payroll->total_salary,
                'expense_date' => Carbon::today(),
                'recorded_by' => 'Sistem HRIS (Otomatis)',
            ]);
            $this->audit->record('paid', 'hrm.payroll', $payroll, $old, $payroll->fresh()->toArray());
        });

        return response()->json(['status' => 'success', 'message' => 'Gaji berhasil dibayar dan pengeluaran tercatat.']);
    }
}
