<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OperationalExpense;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HrmController extends Controller
{
    public function summary()
    {
        $today = Carbon::today()->toDateString();
        $currentPeriod = Carbon::now()->format('Y-m');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_employees' => Employee::count(),
                'present_today' => Attendance::whereDate('tanggal', $today)->count(),
                'late_today' => Attendance::whereDate('tanggal', $today)->where('status', 'terlambat')->count(),
                'pending_payroll' => Payroll::where('is_paid', false)->count(),
                'monthly_payroll_total' => Payroll::where('period', $currentPeriod)->sum('total_salary'),
            ],
        ]);
    }

    public function attendances()
    {
        $attendances = Attendance::with('employee:id,name,position')->orderBy('tanggal', 'desc')->orderBy('clock_in', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $attendances,
        ]);
    }

    public function clockIn(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
        ]);

        $tanggal = Carbon::today()->toDateString();

        $alreadyClockedIn = Attendance::where('employee_id', $validated['employee_id'])
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($alreadyClockedIn) {
            return response()->json([
                'status' => 'error',
                'message' => 'Karyawan ini sudah clock-in hari ini.',
            ], 400);
        }

        $attendance = Attendance::create([
            'employee_id' => $validated['employee_id'],
            'tanggal' => $tanggal,
            'clock_in' => now(),
            'status' => 'hadir',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Clock-in berhasil.',
            'data' => $attendance,
        ]);
    }

    public function payrolls()
    {
        $payrolls = Payroll::with('employee:id,name,position')->orderBy('period', 'desc')->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $payrolls,
        ]);
    }

    public function generatePayroll(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period' => 'required|string',
            'base_salary' => 'required|numeric|min:0',
            'allowance' => 'nullable|numeric|min:0',
            'deduction' => 'nullable|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
        ]);

        $validated['allowance'] = $validated['allowance'] ?? $validated['bonus'] ?? 0;
        $validated['deduction'] = $validated['deduction'] ?? 0;
        unset($validated['bonus']);
        $validated['total_salary'] = (float) $validated['base_salary'] + (float) $validated['allowance'] - (float) $validated['deduction'];

        $payroll = Payroll::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Slip gaji berhasil diterbitkan.',
            'data' => $payroll,
        ]);
    }

    public function paySalary($id)
    {
        $payroll = Payroll::with('employee')->findOrFail($id);

        if ($payroll->is_paid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gaji ini sudah ditransfer sebelumnya.',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $payroll->update(['is_paid' => true]);

            OperationalExpense::create([
                'name' => 'Pembayaran Gaji: ' . $payroll->employee->name . ' (Periode ' . $payroll->period . ')',
                'amount' => $payroll->total_salary,
                'expense_date' => Carbon::today(),
                'recorded_by' => 'Sistem HRIS (Otomatis)',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Gaji berhasil dibayar dan pengeluaran otomatis dicatat di dashboard.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan sinkronisasi ERP: ' . $e->getMessage(),
            ], 500);
        }
    }
}