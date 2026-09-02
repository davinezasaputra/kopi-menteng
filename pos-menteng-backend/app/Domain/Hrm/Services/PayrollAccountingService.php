<?php

namespace App\Domain\Hrm\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Models\Payroll;
use App\Support\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PayrollAccountingService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ErpAccountingService $accounting,
    ) {}

    public function postPayment(Payroll $payroll): ErpJournalBatch
    {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if (
            (int) $payroll->tenant_id !== (int) $membership->tenant_id ||
            (int) $payroll->company_id !== (int) $membership->company_id ||
            (int) $payroll->branch_id !== (int) $membership->branch_id
        ) {
            throw ValidationException::withMessages(['payroll' => 'Payroll is outside the active ERP context.']);
        }

        if (! $payroll->is_paid) {
            throw ValidationException::withMessages(['payroll' => 'Only paid payroll can be posted to ERP accounting.']);
        }

        if (! preg_match('/^\d{4}-\d{2}$/', (string) $payroll->period)) {
            throw ValidationException::withMessages(['period' => 'Payroll period must use YYYY-MM format.']);
        }

        $journalDate = Carbon::createFromFormat('!Y-m-d', $payroll->period . '-01')
            ->endOfMonth()
            ->toDateString();

        $expense = $this->resolveSalaryExpenseAccount($membership->tenant_id, $membership->company_id);
        $cash = $this->resolveCashAccount($membership->tenant_id, $membership->company_id);
        $amount = (float) $payroll->total_salary;

        if ($amount <= 0) {
            throw ValidationException::withMessages(['total_salary' => 'Payroll total salary must be greater than zero.']);
        }

        return $this->accounting->postSourceJournal(
            'payroll_payment',
            (string) $payroll->id,
            'Payroll payment for period ' . $payroll->period,
            [
                [
                    'account_id' => $expense->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Salary expense for payroll period ' . $payroll->period,
                ],
                [
                    'account_id' => $cash->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Salary payment for payroll period ' . $payroll->period,
                ],
            ],
            (int) $payroll->branch_id,
            $journalDate,
        );
    }

    private function resolveSalaryExpenseAccount(int $tenantId, int $companyId): ErpAccount
    {
        $preferred = ErpAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->whereIn('code', ['5200', '5100'])
            ->where('type', 'expense')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderByRaw("CASE code WHEN '5200' THEN 0 WHEN '5100' THEN 1 ELSE 2 END")
            ->first();

        if ($preferred) {
            return $preferred;
        }

        $fallback = ErpAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', '!=', '5000')
            ->orderBy('code')
            ->first();

        if ($fallback) {
            return $fallback;
        }

        throw ValidationException::withMessages([
            'accounting' => 'No active postable salary expense account is configured for this company.',
        ]);
    }

    private function resolveCashAccount(int $tenantId, int $companyId): ErpAccount
    {
        $preferred = ErpAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->whereIn('code', ['1000', '1010'])
            ->whereIn('type', ['asset'])
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderByRaw("CASE code WHEN '1000' THEN 0 WHEN '1010' THEN 1 ELSE 2 END")
            ->first();

        if ($preferred) {
            return $preferred;
        }

        $fallback = ErpAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('type', 'asset')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->first();

        if ($fallback) {
            return $fallback;
        }

        throw ValidationException::withMessages([
            'accounting' => 'No active postable cash or bank account is configured for this company.',
        ]);
    }
}
