<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Purchasing\Models\{SupplierCreditNote,SupplierInvoice,SupplierReturn};
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierCreditNoteService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly ErpAccountingService $accounting,
        private readonly AuditService $audit,
    ) {}

    public function createFromReturn(
        SupplierReturn $supplierReturn,
        ?SupplierInvoice $invoice,
        string $creditNoteNumber,
        ?string $reason = null,
        ?string $notes = null,
    ): SupplierCreditNote {
        $membership=$this->context->membership();

        if(!$membership){
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if(
            (int)$supplierReturn->tenant_id !== (int)$membership->tenant_id ||
            (int)$supplierReturn->company_id !== (int)$membership->company_id ||
            (int)$supplierReturn->branch_id !== (int)$membership->branch_id
        ){
            throw ValidationException::withMessages(['supplier_return_id'=>'Supplier return is outside the active ERP context.']);
        }

        if($invoice){
            if(
                (int)$invoice->tenant_id !== (int)$membership->tenant_id ||
                (int)$invoice->company_id !== (int)$membership->company_id ||
                (int)$invoice->branch_id !== (int)$membership->branch_id ||
                (int)$invoice->supplier_id !== (int)$supplierReturn->supplier_id
            ){
                throw ValidationException::withMessages(['supplier_invoice_id'=>'Supplier invoice is outside the active supplier context.']);
            }
        }

        if($creditNoteNumber===''){
            throw ValidationException::withMessages(['credit_note_number'=>'Credit note number is required.']);
        }

        $requestId=request()->attributes->get('request_id');

        return DB::transaction(function() use($membership,$supplierReturn,$invoice,$creditNoteNumber,$reason,$notes,$requestId){
            if($requestId){
                $existing=SupplierCreditNote::query()
                    ->where('tenant_id',$membership->tenant_id)
                    ->where('request_id',$requestId)
                    ->with(['supplierReturn','supplierInvoice'])
                    ->first();
                if($existing) return $existing;
            }

            $supplierReturn->loadMissing('items');
            $amount=round((float)$supplierReturn->items->sum(fn($item)=>(float)$item->line_value),2);

            if($amount<=0){
                throw ValidationException::withMessages(['supplier_return_id'=>'Supplier return has no positive value.']);
            }

            if($invoice){
                $invoice->lockForUpdate();
                $outstanding=max(0,(float)$invoice->total_amount-(float)$invoice->paid_amount);
                $alreadyCredited=(float)SupplierCreditNote::query()
                    ->where('supplier_invoice_id',$invoice->id)
                    ->sum('amount');
                $remainingCapacity=max(0,$outstanding-$alreadyCredited);

                if($amount>$remainingCapacity){
                    throw ValidationException::withMessages([
                        'amount'=>"Credit note exceeds invoice outstanding capacity: {$remainingCapacity}."
                    ]);
                }
            }

            $note=SupplierCreditNote::create([
                'tenant_id'=>$membership->tenant_id,
                'company_id'=>$membership->company_id,
                'branch_id'=>$membership->branch_id,
                'supplier_id'=>$supplierReturn->supplier_id,
                'supplier_return_id'=>$supplierReturn->id,
                'supplier_invoice_id'=>$invoice?->id,
                'credit_note_number'=>$creditNoteNumber,
                'credit_note_date'=>now()->toDateString(),
                'amount'=>$amount,
                'applied_amount'=>0,
                'remaining_amount'=>$amount,
                'status'=>'open',
                'created_by'=>auth()->id(),
                'request_id'=>$requestId,
                'reason'=>$reason,
                'notes'=>$notes,
            ]);

            if($invoice){
                $this->applyToInvoice($note,$invoice,$amount);
            }

            $apAccount=$this->accountByCode('2100');
            $inventoryAccount=$this->accountByCode('1100');

            $this->accounting->postSourceJournal(
                'supplier_credit_note',
                (string)$note->id,
                'Supplier credit note ' . $note->credit_note_number,
                [
                    ['account_id'=>$apAccount->id,'debit'=>0,'credit'=>$amount,'description'=>'Supplier credit note liability reduction'],
                    ['account_id'=>$inventoryAccount->id,'debit'=>$amount,'credit'=>0,'description'=>'Credit note inventory reversal'],
                ],
                (int)$note->branch_id
            );

            $this->audit->record('created','supplier_credit_note',$note,null,$note->toArray());

            return $note->fresh(['supplier','supplierReturn','supplierInvoice']);
        });
    }

    private function applyToInvoice(SupplierCreditNote $note,SupplierInvoice $invoice,float $amount): void
    {
        $note->applied_amount=$amount;
        $note->remaining_amount=0;
        $note->status='applied';
        $note->save();

        $invoice->paid_amount=max(0,(float)$invoice->paid_amount-$amount);
        $invoice->status=$invoice->paid_amount >= $invoice->total_amount ? 'paid' : ($invoice->paid_amount > 0 ? 'partially_paid' : 'open');
        $invoice->save();
    }

    private function accountByCode(string $code): ErpAccount
    {
        $membership=$this->context->membership();

        return ErpAccount::query()
            ->where('tenant_id',$membership->tenant_id)
            ->where('company_id',$membership->company_id)
            ->where('code',$code)
            ->where('is_active',true)
            ->where('is_postable',true)
            ->firstOrFail();
    }
}
