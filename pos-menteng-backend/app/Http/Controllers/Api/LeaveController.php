<?php

namespace App\Http\Controllers\Api;

use App\Models\Leave;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveController extends BaseController
{
    /**
     * Get all leaves with filtering
     */
    public function index(Request $request): JsonResponse
    {
        $query = Leave::with('employee');

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('month')) {
            $query->whereMonth('start_date', $request->month);
        }

        $leaves = $query->orderBy('start_date', 'desc')->paginate(15);

        return $this->sendSuccess($leaves, 'Leaves retrieved successfully');
    }

    /**
     * Create new leave request
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'type' => 'required|in:cuti_tahunan,sakit,izin,libur_nasional,lainnya',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $leave = Leave::create($validated);

        return $this->sendSuccess($leave->load('employee'), 'Leave request created successfully', 201);
    }

    /**
     * Get leave by ID
     */
    public function show(Leave $leave): JsonResponse
    {
        return $this->sendSuccess($leave->load('employee'), 'Leave retrieved successfully');
    }

    /**
     * Approve leave request
     */
    public function approve(Request $request, Leave $leave): JsonResponse
    {
        if ($leave->status !== 'pending') {
            return $this->sendError('Only pending leaves can be approved', 400);
        }

        $leave->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return $this->sendSuccess($leave, 'Leave approved successfully');
    }

    /**
     * Reject leave request
     */
    public function reject(Request $request, Leave $leave): JsonResponse
    {
        if ($leave->status !== 'pending') {
            return $this->sendError('Only pending leaves can be rejected', 400);
        }

        $leave->update([
            'status' => 'rejected',
            'approved_at' => now(),
            'approved_by' => auth()->id(),
        ]);

        return $this->sendSuccess($leave, 'Leave rejected successfully');
    }

    /**
     * Get attendance report for a month
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer',
        ]);

        $attendance = Attendance::where('employee_id', $validated['employee_id'])
            ->whereYear('tanggal', $validated['year'])
            ->whereMonth('tanggal', $validated['month'])
            ->orderBy('tanggal')
            ->get()
            ->map(function ($record) {
                return [
                    'date' => $record->tanggal->format('Y-m-d'),
                    'status' => $record->status,
                    'clock_in' => $record->clock_in?->format('H:i:s'),
                    'clock_out' => $record->clock_out?->format('H:i:s'),
                    'late_minute' => $record->late_minute,
                    'is_leave' => $record->isLeave(),
                    'notes' => $record->notes,
                ];
            });

        $summary = [
            'total_workdays' => $attendance->where('status', 'hadir')->count(),
            'total_late' => $attendance->where('status', 'terlambat')->count(),
            'total_leave' => $attendance->filter(fn($a) => $a['is_leave'])->count(),
            'breakdown' => $attendance->countBy('status'),
        ];

        return $this->sendSuccess([
            'attendance' => $attendance,
            'summary' => $summary,
        ], 'Attendance report retrieved successfully');
    }

    /**
     * Delete leave
     */
    public function destroy(Leave $leave): JsonResponse
    {
        $leave->delete();
        return $this->sendSuccess(null, 'Leave deleted successfully');
    }
}
