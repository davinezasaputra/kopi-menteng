<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Models\OperationalExpense;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class OperationalExpenseAccountingService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ErpAccountingService $accounting,
    ) {}

    public function postExpense(OperationalExpense $expense): void
    {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if (
            (int) $expense->tenant_id !== (int) $membership->tenant_id ||
            (int) $expense->company_id !== (int) $membership->company_id ||
            (int) $expense->branch_id !== (int) $membership->branch_id
        ) {
            throw ValidationException::withMessages(['expense' => 'Operational expense is outside the active ERP context.']);
        }

        $amount = round((float) $expense->amount, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Operational expense amount must be greater than zero.']);
        }

        if (! $expense->expense_date) {
            throw ValidationException::withMessages(['expense_date' => 'Operational expense date is required.']);
        }

        $expenseAccount = $this->resolveExpenseAccount($membership->tenant_id, $membership->company_id);
        $cash = $this->resolveAccount($membership->tenant_id, $membership->company_id, '1000');

        $this->accounting->postSourceJournal(
            'operational_expense',
            (string) $expense->id,
            'Operational expense '.$expense->name,
            [
                [
                    'account_id' => $expenseAccount->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Operational expense '.$expense->name,
                ],
                [
                    'account_id' => $cash->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Cash payment for operational expense',
                ],
            ],
            (int) $expense->branch_id,
            $expense->expense_date->toDateString(),
            (int) $membership->user_id,
        );
    }

    private function resolveExpenseAccount(int $tenantId, int $companyId): ErpAccount
    {
        $account = $this->resolveAccount($tenantId, $companyId, '5100', false);
        if ($account) {
            return $account;
        }

        $account = ErpAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('type', 'expense')
            ->where('code', '!=', '5000')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'accounting' => 'No active postable operational expense account is configured for this company.',
            ]);
        }

        return $account;
    }

    private function resolveAccount(int $tenantId, int $companyId, string $code, bool $required = true): ?ErpAccount
    {
        $account = ErpAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->first();

        if (! $account && $required) {
            throw ValidationException::withMessages([
                'accounting' => "Required ERP account {$code} is not configured for this company.",
            ]);
        }

        return $account;
    }
}
