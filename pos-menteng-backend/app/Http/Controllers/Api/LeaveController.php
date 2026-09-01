<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Organization\Models\Branch;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Support\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditService $audit,
    ) {}

    private function employeeIds()
    {
        return Employee::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->pluck('id');
    }

    private function scopedQuery()
    {
        return Leave::query()->whereIn('employee_id', $this->employeeIds());
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scopedQuery()->with('employee');
        if ($request->filled('employee_id')) $query->where('employee_id', $request->employee_id);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('month')) $query->whereMonth('start_date', $request->month);
        $leaves = $query->orderByDesc('start_date')->paginate(min((int) $request->integer('per_page', 50), 100));
        return response()->json(['status' => 'success', 'data' => $leaves]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid',
            'type' => 'required|in:cuti_tahunan,sakit,izin,libur_nasional,lainnya',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        if (! $this->employeeIds()->contains($validated['employee_id'])) {
            return response()->json(['status' => 'error', 'message' => 'Karyawan berada di luar context aktif.'], 422);
        }

        $leave = Leave::create($validated + [
            'tenant_id' => $this->context->tenantId(),
            'company_id' => $this->context->companyId(),
            'branch_id' => $this->context->branchId(),
            'status' => 'pending',
        ]);
        $this->audit->record('created', 'hrm.leave', $leave, null, $leave->toArray());
        return response()->json(['status' => 'success', 'data' => $leave->load('employee')], 201);
    }

    public function show(string $id): JsonResponse
    {
        $leave = $this->scopedQuery()->find($id);
        if (! $leave) return response()->json(['status' => 'error', 'message' => 'Cuti tidak ditemukan.'], 404);
        return response()->json(['status' => 'success', 'data' => $leave->load('employee')]);
    }

    public function approve(string $id): JsonResponse
    {
        $leave = $this->scopedQuery()->find($id);
        if (! $leave) return response()->json(['status' => 'error', 'message' => 'Cuti tidak ditemukan.'], 404);
        if ($leave->status !== 'pending') return response()->json(['status' => 'error', 'message' => 'Only pending leaves can be approved.'], 400);
        $old = $leave->toArray();
        $leave->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => auth()->id()]);
        $this->audit->record('approved', 'hrm.leave', $leave, $old, $leave->fresh()->toArray());
        return response()->json(['status' => 'success', 'data' => $leave->fresh(), 'message' => 'Leave approved successfully']);
    }

    public function reject(string $id): JsonResponse
    {
        $leave = $this->scopedQuery()->find($id);
        if (! $leave) return response()->json(['status' => 'error', 'message' => 'Cuti tidak ditemukan.'], 404);
        if ($leave->status !== 'pending') return response()->json(['status' => 'error', 'message' => 'Only pending leaves can be rejected.'], 400);
        $old = $leave->toArray();
        $leave->update(['status' => 'rejected', 'approved_at' => now(), 'approved_by' => auth()->id()]);
        $this->audit->record('rejected', 'hrm.leave', $leave, $old, $leave->fresh()->toArray());
        return response()->json(['status' => 'success', 'data' => $leave->fresh(), 'message' => 'Leave rejected successfully']);
    }

    public function attendanceReport(Request $request): JsonResponse
    {
        $validated = $request->validate(['employee_id' => 'required|uuid', 'month' => 'required|integer|between:1,12', 'year' => 'required|integer|min:2000|max:2100']);
        if (! $this->employeeIds()->contains($validated['employee_id'])) return response()->json(['status' => 'error', 'message' => 'Karyawan berada di luar context aktif.'], 422);
        $attendance = Attendance::where('employee_id', $validated['employee_id'])
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->whereYear('tanggal', $validated['year'])->whereMonth('tanggal', $validated['month'])->orderBy('tanggal')->get()
            ->map(fn ($record) => [
                'date' => $record->tanggal->format('Y-m-d'), 'status' => $record->status,
                'clock_in' => $record->clock_in?->format('H:i:s'), 'clock_out' => $record->clock_out?->format('H:i:s'),
                'late_minute' => $record->late_minute, 'is_leave' => $record->isLeave(), 'notes' => $record->notes,
            ]);
        return response()->json(['status' => 'success', 'data' => [
            'attendance' => $attendance,
            'summary' => ['total_workdays' => $attendance->where('status', 'hadir')->count(), 'total_late' => $attendance->where('status', 'terlambat')->count(), 'total_leave' => $attendance->filter(fn ($a) => $a['is_leave'])->count(), 'breakdown' => $attendance->countBy('status')],
        ]]);
    }

    public function destroy(string $id): JsonResponse
    {
        $leave = $this->scopedQuery()->find($id);
        if (! $leave) return response()->json(['status' => 'error', 'message' => 'Cuti tidak ditemukan.'], 404);
        $old = $leave->toArray();
        $leave->delete();
        $this->audit->record('deleted', 'hrm.leave', $leave, $old, null);
        return response()->json(['status' => 'success', 'message' => 'Leave deleted successfully']);
    }
}
