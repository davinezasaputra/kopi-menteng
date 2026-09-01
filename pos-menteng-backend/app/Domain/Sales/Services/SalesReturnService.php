<?php
namespace App\Domain\Sales\Services;
use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Sales\Models\{SalesInvoice,SalesReturn,SalesReturnItem};
use App\Domain\Organization\Models\Warehouse;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class SalesReturnService{
 public function __construct(private readonly TenantContext $context,private readonly DocumentNumberService $numbers,private readonly InventoryService $inventory,private readonly ErpAccountingService $accounting,private readonly AuditService $audit){}
 public function create(SalesInvoice $invoice,Warehouse $warehouse,array $items,?string $reason=null):SalesReturn{
  return DB::transaction(function()use($invoice,$warehouse,$items,$reason){
   $m=$this->context->membership();if(!$m)throw ValidationException::withMessages(['context'=>'No active ERP context.']);
   $i=SalesInvoice::query()->with('items')->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->lockForUpdate()->findOrFail($invoice->id);
   if($i->status==='cancelled')throw ValidationException::withMessages(['invoice'=>'Cancelled invoice cannot be returned.']);
   if((int)$warehouse->branch_id!==(int)$m->branch_id)throw ValidationException::withMessages(['warehouse_id'=>'Warehouse is outside active branch.']);
   $requestId=request()->attributes->get('request_id'); if($requestId){$e=SalesReturn::where('tenant_id',$m->tenant_id)->where('request_id',$requestId)->first();if($e)return $e->load(['items.product','invoice','warehouse']);}
   $invoiceItems=$i->items->keyBy('product_id'); $normalized=[];$total=0;
   foreach($items as $x){$productId=$x['product_id'];$qty=(float)$x['quantity'];if($qty<=0)throw ValidationException::withMessages(['items'=>'Return quantity must be positive.']);$src=$invoiceItems->get($productId);if(!$src)throw ValidationException::withMessages(['items'=>'Product was not sold on this invoice.']);$already=(float)SalesReturnItem::whereHas('salesReturn',fn($q)=>$q->where('sales_invoice_id',$i->id))->where('product_id',$productId)->sum('quantity');$remaining=max(0,(float)$src->quantity-$already);if($qty>$remaining)throw ValidationException::withMessages(['items'=>"Return exceeds sold quantity. Remaining: {$remaining}."]);$line=round($qty*(float)$src->unit_price,2);$normalized[]=['product_id'=>$productId,'quantity'=>$qty,'unit_price'=>(float)$src->unit_price,'line_total'=>$line];$total+=$line;}
   $r=SalesReturn::create(['tenant_id'=>$i->tenant_id,'company_id'=>$i->company_id,'branch_id'=>$i->branch_id,'warehouse_id'=>$warehouse->id,'sales_invoice_id'=>$i->id,'customer_id'=>$i->customer_id,'customer_name_snapshot'=>$i->customer_name_snapshot,'return_number'=>$this->numbers->next('sales_return','RET'),'return_date'=>now()->toDateString(),'status'=>'posted','total_amount'=>$total,'created_by'=>auth()->id(),'request_id'=>$requestId,'reason'=>$reason]);
   foreach($normalized as $x){$p=Product::findOrFail($x['product_id']);$this->inventory->receive($warehouse,$p,$x['quantity'],(float)max(0,(float)$p->price*0.4),'sales_return',(string)$r->id,$reason);SalesReturnItem::create(['sales_return_id'=>$r->id]+$x);}
   $rev=ErpAccount::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('code','4000')->firstOrFail();
   $ar=ErpAccount::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('code','1200')->firstOrFail();
   $inv=ErpAccount::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('code','1100')->firstOrFail();
   $cogs=ErpAccount::where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('code','5000')->firstOrFail();
   $this->accounting->postSourceJournal('sales_return',(string)$r->id,'Sales return '.$r->return_number,[['account_id'=>$rev->id,'debit'=>$total,'credit'=>0,'description'=>'Reverse sales revenue'],['account_id'=>$ar->id,'debit'=>0,'credit'=>$total,'description'=>'Reduce accounts receivable']],(int)$r->branch_id);
   $this->accounting->postSourceJournal('sales_return_cogs',(string)$r->id,'Sales return inventory '.$r->return_number,[['account_id'=>$inv->id,'debit'=>$total,'credit'=>0,'description'=>'Returned inventory'],['account_id'=>$cogs->id,'debit'=>0,'credit'=>$total,'description'=>'Reverse COGS']],(int)$r->branch_id);
   $this->audit->record('posted','sales_return',$r,null,$r->toArray());return $r->load(['items.product','invoice','warehouse']);
  });
 }
 public function list(){ $m=$this->context->membership();return SalesReturn::with(['invoice','warehouse','items.product','customer'])->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->latest('return_date')->paginate(50);}
}
