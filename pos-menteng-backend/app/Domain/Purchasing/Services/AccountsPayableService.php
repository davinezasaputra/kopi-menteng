<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Domain\Purchasing\Models\SupplierPayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountsPayableService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly AuditService $audit,
    ) {}

    public function createInvoice(
        GoodsReceipt $receipt,
        string $invoiceNumber,
        ?string $invoiceDate = null,
        ?string $dueDate = null,
        ?string $notes = null,
    ): SupplierInvoice {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if (
            (int)$receipt->tenant_id !== (int)$membership->tenant_id ||
            (int)$receipt->company_id !== (int)$membership->company_id ||
            (int)$receipt->branch_id !== (int)$membership->branch_id
        ) {
            throw ValidationException::withMessages(['goods_receipt_id'=>'Goods receipt is outside the active ERP context.']);
        }

        if ($invoiceNumber === '') {
            throw ValidationException::withMessages(['invoice_number'=>'Supplier invoice number is required.']);
        }

        $requestId = request()->attributes->get('request_id');

        return DB::transaction(function () use ($membership,$receipt,$invoiceNumber,$invoiceDate,$dueDate,$notes,$requestId) {
            if ($requestId) {
                $existing = SupplierInvoice::query()
                    ->where('tenant_id',$membership->tenant_id)
                    ->where('request_id',$requestId)
                    ->first();

                if ($existing) return $existing;
            }

            $receipt->loadMissing('items','order','supplier');

            $subtotal = round($receipt->items->sum(fn ($item) => (float)$item->line_value),2);

            $invoice = SupplierInvoice::create([
                'tenant_id'=>$membership->tenant_id,
                'company_id'=>$membership->company_id,
                'branch_id'=>$membership->branch_id,
                'supplier_id'=>$receipt->supplier_id,
                'purchase_order_id'=>$receipt->purchase_order_id,
                'goods_receipt_id'=>$receipt->id,
                'invoice_number'=>$invoiceNumber,
                'invoice_date'=>$invoiceDate ?? now()->toDateString(),
                'due_date'=>$dueDate,
                'subtotal'=>$subtotal,
                'tax_amount'=>0,
                'discount_amount'=>0,
                'total_amount'=>$subtotal,
                'paid_amount'=>0,
                'status'=>'open',
                'created_by'=>auth()->id(),
                'request_id'=>$requestId,
                'notes'=>$notes,
            ]);

            $invoice->load(['supplier','order','goodsReceipt','creator']);
            $this->audit->record('created','supplier_invoice',$invoice,null,$invoice->toArray());

            return $invoice;
        });
    }

    public function pay(SupplierInvoice $invoice, float $amount, string $method = 'bank_transfer', ?string $reference = null, ?string $notes = null): SupplierPayment
    {
        $membership = $this->context->membership();

        if (
            ! $membership ||
            (int)$invoice->tenant_id !== (int)$membership->tenant_id ||
            (int)$invoice->company_id !== (int)$membership->company_id ||
            (int)$invoice->branch_id !== (int)$membership->branch_id
        ) {
            throw ValidationException::withMessages(['invoice'=>'Supplier invoice is outside the active ERP context.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount'=>'Payment amount must be greater than zero.']);
        }

        if (! in_array($method,['cash','bank_transfer','giro','other'],true)) {
            throw ValidationException::withMessages(['method'=>'Unsupported payment method.']);
        }

        $requestId = request()->attributes->get('request_id');

        return DB::transaction(function () use ($membership,$invoice,$amount,$method,$reference,$notes,$requestId) {
            if ($requestId) {
                $existing = SupplierPayment::query()
                    ->where('tenant_id',$membership->tenant_id)
                    ->where('request_id',$requestId)
                    ->first();

                if ($existing) return $existing;
            }

            $row = SupplierInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $outstanding = (float)$row->total_amount - (float)$row->paid_amount;

            if ($amount > $outstanding) {
                throw ValidationException::withMessages(['amount'=>"Payment exceeds outstanding AP balance: {$outstanding}."]);
            }

            $payment = SupplierPayment::create([
                'tenant_id'=>$membership->tenant_id,
                'company_id'=>$membership->company_id,
                'branch_id'=>$membership->branch_id,
                'supplier_id'=>$row->supplier_id,
                'supplier_invoice_id'=>$row->id,
                'payment_number'=>$this->numbers->next('supplier_payment','SP'),
                'payment_date'=>now()->toDateString(),
                'amount'=>$amount,
                'method'=>$method,
                'reference'=>$reference,
                'paid_by'=>auth()->id(),
                'request_id'=>$requestId,
                'notes'=>$notes,
            ]);

            $row->paid_amount = (float)$row->paid_amount + $amount;
            $row->status = $row->paid_amount >= $row->total_amount ? 'paid' : 'partially_paid';
            $row->save();

            $payment->load(['supplier','invoice','payer']);
            $this->audit->record('created','supplier_payment',$payment,null,$payment->toArray());

            return $payment;
        });
    }
}
