<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Models\RestockHistory;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class RestockAccountingService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ErpAccountingService $accounting,
    ) {}

    public function postRestock(RestockHistory $restock): void
    {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if (
            (int) $restock->tenant_id !== (int) $membership->tenant_id ||
            (int) $restock->company_id !== (int) $membership->company_id ||
            (int) $restock->branch_id !== (int) $membership->branch_id
        ) {
            throw ValidationException::withMessages(['restock' => 'Restock is outside the active ERP context.']);
        }

        $amount = round((float) $restock->total_cost, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['total_cost' => 'Restock total cost must be greater than zero.']);
        }

        $createdBy = auth()->id();
        if ($createdBy === null) {
            throw ValidationException::withMessages(['created_by' => 'Authenticated restock actor is required.']);
        }

        $inventory = $this->account($membership->tenant_id, $membership->company_id, '1100');
        $cash = $this->account($membership->tenant_id, $membership->company_id, '1000');

        $this->accounting->postSourceJournal(
            'inventory_restock',
            (string) $restock->id,
            'Inventory restock #'.$restock->id,
            [
                [
                    'account_id' => $inventory->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Raw material inventory receipt',
                ],
                [
                    'account_id' => $cash->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Cash payment for raw material restock',
                ],
            ],
            (int) $restock->branch_id,
            $restock->created_at?->toDateString(),
            $createdBy,
        );
    }

    private function account(int $tenantId, int $companyId, string $code): ErpAccount
    {
        $account = ErpAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'accounting' => "Required ERP account {$code} is not configured for this company.",
            ]);
        }

        return $account;
    }
}
