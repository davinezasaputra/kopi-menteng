<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Accounting\Models\ErpJournalBatch;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

class PurchasingReconciliationService
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function reconcile(PurchaseOrder $order): array
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
            throw ValidationException::withMessages(['purchase_order_id' => 'Purchase order is outside the active ERP context.']);
        }

        $order->load(['items', 'supplier', 'warehouse']);

        $receipts = GoodsReceipt::query()
            ->with('items')
            ->where('tenant_id', $membership->tenant_id)
            ->where('company_id', $membership->company_id)
            ->where('branch_id', $membership->branch_id)
            ->where('purchase_order_id', $order->id)
            ->get();

        $invoices = SupplierInvoice::query()
            ->where('tenant_id', $membership->tenant_id)
            ->where('company_id', $membership->company_id)
            ->where('branch_id', $membership->branch_id)
            ->where('purchase_order_id', $order->id)
            ->get();

        $orderQuantity = (float) $order->items->sum(fn ($item) => (float) $item->quantity);
        $receivedQuantity = (float) $order->items->sum(fn ($item) => (float) $item->received_quantity);
        $remainingQuantity = max(0, $orderQuantity - $receivedQuantity);

        $receiptValue = round($receipts->sum(
            fn (GoodsReceipt $receipt) => $receipt->items->sum(fn ($item) => (float) $item->line_value)
        ), 2);

        $invoiceValue = round($invoices->sum(fn (SupplierInvoice $invoice) => (float) $invoice->total_amount), 2);
        $paidAmount = round($invoices->sum(fn (SupplierInvoice $invoice) => (float) $invoice->paid_amount), 2);
        $outstanding = round(max(0, $invoiceValue - $paidAmount), 2);

        $journalRows = ErpJournalBatch::query()
            ->with('lines')
            ->where('tenant_id', $membership->tenant_id)
            ->where('company_id', $membership->company_id)
            ->where(function ($query) use ($order, $receipts, $invoices) {
                $query
                    ->where(fn ($q) => $q->where('source_type', 'purchase_order')->where('source_id', (string) $order->id))
                    ->orWhere(function ($q) use ($receipts) {
                        $q->where('source_type', 'goods_receipt')
                            ->whereIn('source_id', $receipts->pluck('id')->map(fn ($id) => (string) $id)->all());
                    })
                    ->orWhere(function ($q) use ($invoices) {
                        $q->where('source_type', 'supplier_invoice')
                            ->whereIn('source_id', $invoices->pluck('id')->map(fn ($id) => (string) $id)->all());
                    });
            })
            ->get();

        $inventoryDebit = round($journalRows->sum(
            fn (ErpJournalBatch $journal) => $journal->lines->where('debit', '>', 0)->sum(fn ($line) => (float) $line->debit)
        ), 2);

        $apCredit = round($journalRows->sum(
            fn (ErpJournalBatch $journal) => $journal->lines->where('credit', '>', 0)->sum(fn ($line) => (float) $line->credit)
        ), 2);

        $quantityOk = $receivedQuantity <= $orderQuantity;
        $receiptInvoiceOk = abs($receiptValue - $invoiceValue) < 0.01;
        $paymentOk = $paidAmount <= $invoiceValue + 0.01;
        $accountingOk = abs($inventoryDebit - $apCredit) < 0.01;

        return [
            'purchase_order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'ordered_quantity' => round($orderQuantity, 4),
                'received_quantity' => round($receivedQuantity, 4),
                'remaining_quantity' => round($remainingQuantity, 4),
            ],
            'goods_receipt' => [
                'count' => $receipts->count(),
                'received_value' => $receiptValue,
            ],
            'supplier_invoice' => [
                'count' => $invoices->count(),
                'invoice_value' => $invoiceValue,
                'paid_amount' => $paidAmount,
                'outstanding' => $outstanding,
            ],
            'accounting' => [
                'journal_count' => $journalRows->count(),
                'inventory_debit' => $inventoryDebit,
                'ap_credit' => $apCredit,
                'balanced' => $accountingOk,
            ],
            'checks' => [
                'quantity_not_over_received' => $quantityOk,
                'receipt_matches_invoice' => $receiptInvoiceOk,
                'payment_not_over_invoice' => $paymentOk,
                'accounting_balanced' => $accountingOk,
            ],
            'reconciled' => $quantityOk && $receiptInvoiceOk && $paymentOk && $accountingOk,
        ];
    }
}
