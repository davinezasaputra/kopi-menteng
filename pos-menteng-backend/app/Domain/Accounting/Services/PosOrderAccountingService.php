<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Models\Order;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class PosOrderAccountingService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly ErpAccountingService $accounting,
    ) {
    }

    public function postPaidOrder(Order $order): void
    {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if (
            (int) $order->tenant_id !== (int) $membership->tenant_id ||
            (int) $order->company_id !== (int) $membership->company_id ||
            (int) $order->branch_id !== (int) $membership->branch_id
        ) {
            throw ValidationException::withMessages(['order' => 'POS order is outside the active ERP context.']);
        }

        if (! in_array($order->status, ['paid', 'settlement'], true)) {
            throw ValidationException::withMessages(['status' => 'Only paid POS orders can be posted to ERP accounting.']);
        }

        $amount = round((float) $order->total, 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['total' => 'POS order total must be greater than zero.']);
        }

        $cashCode = $order->payment_method === 'cash' ? '1000' : '1010';
        $cash = $this->account($membership->tenant_id, $membership->company_id, $cashCode);
        $revenue = $this->account($membership->tenant_id, $membership->company_id, '4000');

        $this->accounting->postSourceJournal(
            'pos_sale_payment',
            (string) $order->id,
            'POS sale '.$order->invoice_number,
            [
                [
                    'account_id' => $cash->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => 'Settlement POS '.$order->payment_method,
                ],
                [
                    'account_id' => $revenue->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => 'Sales revenue POS',
                ],
            ],
            (int) $order->branch_id,
            $order->created_at?->toDateString(),
            $order->user_id ? (int) $order->user_id : null,
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
