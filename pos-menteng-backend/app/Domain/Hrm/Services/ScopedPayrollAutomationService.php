<?php

namespace App\Domain\Hrm\Services;

use App\Domain\Audit\Services\AuditService;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScopedPayrollAutomationService extends PayrollAutomationService
{
    public function __construct(
        private readonly TenantContext $scopedContext,
        AuditService $audit,
    ) {
        parent::__construct($scopedContext, $audit);
    }

    public function calculateDeductions($employee, Carbon $period, $attendance = null, float $baseSalary = 0): float
    {
        if (! Schema::hasTable('attendance_penalties')) return 0.0;

        $attendance ??= $employee->attendances()
            ->where('tenant_id', $this->scopedContext->tenantId())
            ->where('company_id', $this->scopedContext->companyId())
            ->where('branch_id', $this->scopedContext->branchId())
            ->whereBetween('tanggal', [$period->toDateString(), $period->copy()->endOfMonth()->toDateString()])
            ->get(['status', 'late_minute']);

        $lateMinutes = (int) $attendance->sum('late_minute');
        $absenceDays = $attendance->filter(fn ($row) => in_array((string) ($row->status ?? ''), ['absence', 'absen', 'alpha', 'tidak_hadir'], true))->count();
        $penalty = 0.0;

        if ($lateMinutes > 0) {
            $rules = DB::table('attendance_penalties')
                ->where('tenant_id', $this->scopedContext->tenantId())
                ->where('company_id', $this->scopedContext->companyId())
                ->where('branch_id', $this->scopedContext->branchId())
                ->where('penalty_type', 'late')
                ->where('is_active', true)
                ->get();

            $rule = $rules
                ->filter(fn ($row) => $this->threshold((string) $row->duration_threshold) <= $lateMinutes)
                ->sortByDesc(fn ($row) => $this->threshold((string) $row->duration_threshold))
                ->first();
            if ($rule) $penalty += $this->amount($rule, $baseSalary);
        }

        if ($absenceDays > 0) {
            $rule = DB::table('attendance_penalties')
                ->where('tenant_id', $this->scopedContext->tenantId())
                ->where('company_id', $this->scopedContext->companyId())
                ->where('branch_id', $this->scopedContext->branchId())
                ->where('penalty_type', 'absence')
                ->where('is_active', true)
                ->get()
                ->filter(fn ($row) => $this->threshold((string) $row->duration_threshold) <= $absenceDays)
                ->sortByDesc(fn ($row) => $this->threshold((string) $row->duration_threshold))
                ->first();
            if ($rule) $penalty += $absenceDays * $this->amount($rule, $baseSalary);
        }

        return round(max(0, $penalty), 2);
    }

    private function amount(object $rule, float $baseSalary): float
    {
        return (string) $rule->amount_type === 'percentage'
            ? max(0, $baseSalary * ((float) $rule->amount / 100))
            : max(0, (float) $rule->amount);
    }

    private function threshold(string $value): int
    {
        if (preg_match('/(\d+)\s*hour/i', $value, $m)) return (int) $m[1] * 60;
        if (preg_match('/(\d+)\s*minute/i', $value, $m)) return (int) $m[1];
        return max(0, (int) $value);
    }
}
