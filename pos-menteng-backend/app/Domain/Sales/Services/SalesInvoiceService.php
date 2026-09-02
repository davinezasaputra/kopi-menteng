<?php

namespace App\Domain\Sales\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Sales\Models\{SalesInvoice,SalesShipment,SalesOrder};
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\OrganizationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(private readonly TenantContext $context,private readonly OrganizationScope $scope,private readonly DocumentNumberService $numbers,private readonly AuditService $audit,private readonly ErpAccountingService $accounting) {}
    public function createFromShipment(SalesShipment $shipment): SalesInvoice{
        return DB::transaction(function()use($shipment){$m=$this->context->membership();if(!$m)throw ValidationException::withMessages(['context'=>'No active ERP context.']);
            $row=SalesShipment::query()->with(['salesOrder.items','salesOrder.customer'])->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->whereIn('warehouse_id',$this->scope->warehouseIds())->lockForUpdate()->findOrFail($shipment->id);
            if($row->status!=='shipped')throw ValidationException::withMessages(['status'=>'Only shipped shipments can be invoiced.']);
            $existing=SalesInvoice::query()->where('tenant_id',$m->tenant_id)->where('sales_shipment_id',$row->id)->with(['items.product','salesOrder','salesShipment','customer','creator'])->first();if($existing)return $existing;
            $order=$row->salesOrder;if(!$order||$order->status!=='approved')throw ValidationException::withMessages(['sales_order_id'=>'Shipment is not linked to an approved sales order.']);
            $subtotal=(float)$order->subtotal;$discount=(float)$order->discount_amount;$tax=(float)$order->tax_amount;$total=round($subtotal-$discount+$tax,2);if($total<0)throw ValidationException::withMessages(['total_amount'=>'Invoice total cannot be negative.']);
            $requestId=request()->attributes->get('request_id');if($requestId){$same=SalesInvoice::query()->where('tenant_id',$m->tenant_id)->where('request_id',$requestId)->first();if($same)return $same->load(['items.product','salesOrder','salesShipment','customer','creator']);}
            $invoice=SalesInvoice::create(['tenant_id'=>$row->tenant_id,'company_id'=>$row->company_id,'branch_id'=>$row->branch_id,'location_id'=>$row->location_id ?? $m->location_id,'sales_order_id'=>$order->id,'sales_shipment_id'=>$row->id,'customer_id'=>$order->customer_id,'customer_name_snapshot'=>$order->customer_name_snapshot,'invoice_number'=>$this->numbers->next('sales_invoice','INV'),'invoice_date'=>now()->toDateString(),'due_date'=>now()->addDays(30)->toDateString(),'subtotal'=>$subtotal,'discount_amount'=>$discount,'tax_amount'=>$tax,'total_amount'=>$total,'paid_amount'=>0,'outstanding_amount'=>$total,'status'=>$total>0?'unpaid':'paid','created_by'=>auth()->id(),'request_id'=>$requestId,'notes'=>'Generated from shipment '.$row->shipment_number]);
            foreach($order->items as $item)$invoice->items()->create(['product_id'=>$item->product_id,'quantity'=>$item->quantity,'unit_price'=>$item->unit_price,'discount_amount'=>$item->discount_amount,'tax_amount'=>$item->tax_amount,'line_total'=>$item->line_total]);
            $ar=ErpAccount::where('tenant_id',$row->tenant_id)->where('company_id',$row->company_id)->where('code','1200')->where('is_active',true)->where('is_postable',true)->firstOrFail();$revenue=ErpAccount::where('tenant_id',$row->tenant_id)->where('company_id',$row->company_id)->where('code','4000')->where('is_active',true)->where('is_postable',true)->firstOrFail();
            $this->accounting->postSourceJournal('sales_invoice',(string)$invoice->id,'Sales invoice '.$invoice->invoice_number,[['account_id'=>$ar->id,'debit'=>$total,'credit'=>0,'description'=>'Recognize accounts receivable'],['account_id'=>$revenue->id,'debit'=>0,'credit'=>$total,'description'=>'Recognize sales revenue']],(int)$row->branch_id,$row->shipment_date?->toDateString());
            $invoice->load(['items.product','salesOrder','salesShipment','customer','creator']);$this->audit->record('created','sales_invoice',$invoice,null,$invoice->toArray());return $invoice;});
    }
    public function list(){ $m=$this->context->membership();if(!$m)throw ValidationException::withMessages(['context'=>'No active ERP context.']);return SalesInvoice::query()->with(['salesOrder','salesShipment','customer','items.product','creator'])->where('tenant_id',$m->tenant_id)->where('company_id',$m->company_id)->where('branch_id',$m->branch_id)->whereHas('salesShipment',fn($q)=>$q->whereIn('warehouse_id',$this->scope->warehouseIds()))->latest('invoice_date')->latest('created_at')->paginate(50); }
}
