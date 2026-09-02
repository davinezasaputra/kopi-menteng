<?php

use App\Http\Controllers\Api\AttendanceActionController;
use App\Http\Controllers\Api\HrmController;
use Illuminate\Support\Facades\Route;

Route::middleware(['request.id', 'security.headers', 'auth:sanctum', 'throttle:erp', 'tenant'])->group(function (): void {
    Route::get('/hrm/payroll/automation/config', [HrmController::class, 'getPayrollAutomationConfig'])
        ->middleware('permission:hr.employee.view');
    Route::patch('/hrm/payroll/automation/config', [HrmController::class, 'updatePayrollAutomationConfig'])
        ->middleware('permission:hr.employee.manage');
    Route::post('/hrm/payrolls/{id}/generate-auto', [HrmController::class, 'generatePayrollAuto'])
        ->middleware('permission:hr.employee.manage');
    Route::post('/hrm/payrolls/{id}/send-whatsapp', [HrmController::class, 'sendPayrollWhatsApp'])
        ->middleware('permission:hr.employee.manage');
    Route::get('/hrm/payroll/notifications', [HrmController::class, 'payrollNotifications'])
        ->middleware('permission:hr.employee.view');
    Route::get('/hrm/payroll/notifications/{id}/status', [HrmController::class, 'payrollNotificationStatus'])
        ->middleware('permission:hr.employee.view');

    Route::get('/hrm/attendance/settings', [AttendanceActionController::class, 'settings'])
        ->middleware('permission:hr.employee.view');
    Route::patch('/hrm/attendance/settings', [AttendanceActionController::class, 'updateSettings'])
        ->middleware('permission:hr.employee.manage');
    Route::get('/hrm/attendance/penalties', [AttendanceActionController::class, 'penalties'])
        ->middleware('permission:hr.employee.view');
    Route::put('/hrm/attendance/penalties', [AttendanceActionController::class, 'updatePenalties'])
        ->middleware('permission:hr.employee.manage');
    Route::post('/hrm/attendance/clock-in', [AttendanceActionController::class, 'clockIn'])
        ->middleware('permission:hr.employee.manage');
    Route::post('/hrm/attendance/clock-out', [AttendanceActionController::class, 'clockOut'])
        ->middleware('permission:hr.employee.manage');
    Route::post('/hrm/attendances/{id}/status', [AttendanceActionController::class, 'setStatus'])
        ->middleware('permission:hr.employee.manage');
    Route::post('/hrm/attendance/off-duty', [AttendanceActionController::class, 'offDuty'])
        ->middleware('permission:hr.employee.manage');
    Route::get('/hrm/attendances/export', [AttendanceActionController::class, 'export'])
        ->middleware('permission:hr.employee.view');
});
