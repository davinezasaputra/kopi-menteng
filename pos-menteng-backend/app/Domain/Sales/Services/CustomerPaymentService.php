<?php
namespace App\Domain\Sales\Services;
use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Sales\Models\{CustomerPayment,SalesInvoice};
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CustomerPaymentService{
 public function __construct(private readonly TenantContext $context,private readonly DocumentNumberService $numbers,private readonly ErpAccountingService $accounting,private readonly AuditService $audit){}
 public function pay(SalesInvoice $invoice,float $amount,string $method,?string $reference=null,?string $notes=null):CustomerPayment{
  return DB::transaction(function()use($invoice,$amount,$method,$reference,$notes){
   $m=$this->context->membership(); if(!$m)throw ValidationException::withMessages(['context'=>'No active ERP context.']);
   $row=SalesInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
   if((int)$row->tenant_id!==(int)$m->tenant_id||(int)$row->company_id!==(int)$m->company_id||(int)$row->branch_id!==(int)$m->branch_id)throw ValidationException::withMessages(['invoice'=>'Invoice is outside active ERP context.']);
   if($amount<=0)throw ValidationException::withMessages(['amount'=>'Payment amount must be greater than zero.']);
   $requestId=request()->attributes->get('request_id');
   if($requestId){$e=CustomerPayment::where('tenant_id',$m->tenant_id)->where('request_id',$requestId)->first();if($e)return $e->load('invoice');}
   $out=(float)$row->outstanding_amount;
   if($amount>$out+0.009)throw ValidationException::withMessages(['amount'=>"Payment exceeds invoice outstanding amount: {$out}."]);
   $payment=CustomerPayment::create([
    'tenant_id'=>$row->tenant_id,'company_id'=>$row->company_id,'branch_id'=>$row->branch_id,'sales_invoice_id'=>$row->id,
    'customer_id'=>$row->customer_id,'customer_name_snapshot'=>$row->customer_name_snapshot,
    'payment_number'=>$this->numbers->next('customer_payment','PAY'),'payment_date'=>now()->toDateString(),
    'amount'=>$amount,'method'=>$method,'reference'=>$reference,'paid_by'=>auth()->id(),'request_id'=>$requestId,'notes'=>$notes
   ]);
   $row->paid_amount=(float)$row->paid_amount+$amount;
   $row->outstanding_amount=max(0,(float)$row->total_amount-(float)$row->paid_amount);
   $row->status=$row->outstanding_amount<=0.009?'paid':'partially_paid'; $row->save();
   $cashCode=$method==='bank'?'1010':'1000';
   $cash=ErpAccount::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('code',$cashCode)->firstOrFail();
   $ar=ErpAccount::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('code','1200')->firstOrFail();
   $this->accounting->postSourceJournal('customer_payment',(string)$payment->id,'Customer payment '.$payment->payment_number,[
    ['account_id'=>$cash->id,'debit'=>$amount,'credit'=>0,'description'=>'Customer payment received'],
    ['account_id'=>$ar->id,'debit'=>0,'credit'=>$amount,'description'=>'Reduce accounts receivable'],
   ],(int)$row->branch_id);
   $this->audit->record('created','customer_payment',$payment,null,$payment->toArray());
   return $payment->fresh(['invoice','customer','payer']);
  });
 }
 public function list(){ $m=$this->context->membership(); if(!$m)throw ValidationException::withMessages(['context'=>'No active ERP context.']); return CustomerPayment::with(['invoice','customer','payer'])->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->latest('payment_date')->paginate(50);}
}
