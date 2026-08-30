<?php

namespace App\Observers;

use App\Models\Leave;
use App\Models\Attendance;

class LeaveObserver
{
    /**
     * Handle the Leave "created" event.
     */
    public function created(Leave $leave): void
    {
        //
    }

    /**
     * Handle the Leave "updated" event - auto-create attendance for approved leaves
     */
    public function updated(Leave $leave): void
    {
        // When leave is approved, create attendance records for each day
        if ($leave->isDirty('status') && $leave->status === 'approved') {
            $this->createAttendanceRecords($leave);
        }
    }

    /**
     * Handle the Leave "deleted" event.
     */
    public function deleted(Leave $leave): void
    {
        // Delete associated attendance records when leave is deleted
        Attendance::where('leave_id', $leave->id)->delete();
    }

    /**
     * Create attendance records for each day of the leave
     */
    private function createAttendanceRecords(Leave $leave): void
    {
        $startDate = $leave->start_date;
        $endDate = $leave->end_date;
        $employeeId = $leave->employee_id;

        // Create attendance record for each day of the leave
        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            // Check if attendance already exists
            $exists = Attendance::where('employee_id', $employeeId)
                ->whereDate('tanggal', $currentDate)
                ->exists();

            if (!$exists) {
                Attendance::create([
                    'employee_id' => $employeeId,
                    'tanggal' => $currentDate,
                    'clock_in' => $currentDate->startOfDay(),
                    'status' => $leave->type, // Use leave type as status
                    'leave_id' => $leave->id,
                    'notes' => $leave->reason,
                ]);
            }

            $currentDate = $currentDate->addDay();
        }
    }
}
