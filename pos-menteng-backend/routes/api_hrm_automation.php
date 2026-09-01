<?php

use App\Http\Controllers\Api\HrmController;
use Illuminate\Support\Facades\Route;

Route::middleware(['request.id', 'security.headers', 'auth:sanctum', 'throttle:erp', 'tenant'])->group(function (): void {
    Route::get('/hrm/payroll/automation/config', [HrmController::class, 'getPayrollAutomationConfig'])
        ->middleware('permission:hr.payroll.automation.view');
    Route::patch('/hrm/payroll/automation/config', [HrmController::class, 'updatePayrollAutomationConfig'])
        ->middleware('permission:hr.payroll.automation.manage');
    Route::post('/hrm/payrolls/{id}/generate-auto', [HrmController::class, 'generatePayrollAuto'])
        ->middleware('permission:hr.payroll.automation.run');
    Route::post('/hrm/payrolls/{id}/send-whatsapp', [HrmController::class, 'sendPayrollWhatsApp'])
        ->middleware('permission:hr.payroll.notification.send');
    Route::get('/hrm/payroll/notifications', [HrmController::class, 'payrollNotifications'])
        ->middleware('permission:hr.payroll.notification.view');
    Route::get('/hrm/payroll/notifications/{id}/status', [HrmController::class, 'payrollNotificationStatus'])
        ->middleware('permission:hr.payroll.notification.view');
});
