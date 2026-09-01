<?php

namespace App\Domain\Sales\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Sales\Models\{SalesOrder,SalesOrderItem};
use App\Models\Customer;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\OrganizationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function __construct(private readonly TenantContext $context,private readonly OrganizationScope $scope,private readonly DocumentNumberService $numbers,private readonly AuditService $audit,private readonly InventoryReservationService $reservations) {}
    public function create(Warehouse $warehouse,array $items,?Customer $customer=null,?string $notes=null,float $discountAmount=0,float $taxAmount=0): SalesOrder{
        $membership=$this->context->membership();if(!$membership)throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        if((int)$warehouse->branch_id!==(int)$membership->branch_id||!in_array((int)$warehouse->id,$this->scope->warehouseIds(),true))throw ValidationException::withMessages(['warehouse_id'=>'Warehouse is outside the active organization/location scope.']);
        if($customer&&$customer->tenant_id!==null&&(int)$customer->tenant_id!==(int)$membership->tenant_id)throw ValidationException::withMessages(['customer_id'=>'Customer is outside the active tenant.']);
        $normalized=[];foreach($items as $item){$product=Product::query()->where('tenant_id',$membership->tenant_id)->findOrFail($item['product_id']);$qty=(float)$item['quantity'];$price=(float)$item['unit_price'];if($qty<=0||$price<0)throw ValidationException::withMessages(['items'=>'Quantity must be greater than zero and unit price cannot be negative.']);$id=(string)$product->id;if(!isset($normalized[$id]))$normalized[$id]=['product_id'=>$product->id,'quantity'=>0,'unit_price'=>$price,'discount_amount'=>(float)($item['discount_amount']??0),'tax_amount'=>(float)($item['tax_amount']??0),'notes'=>$item['notes']??null];$normalized[$id]['quantity']+=$qty;$normalized[$id]['unit_price']=$price;}
        $requestId=request()->attributes->get('request_id');return DB::transaction(function()use($membership,$warehouse,$normalized,$customer,$notes,$discountAmount,$taxAmount,$requestId){
            if($requestId){$existing=SalesOrder::query()->where('tenant_id',$membership->tenant_id)->where('request_id',$requestId)->with(['customer','warehouse','items.product','creator','submitter'])->first();if($existing){$this->assertContext($existing);return $existing;}}
            $subtotal=round(collect($normalized)->sum(fn($i)=>$i['quantity']*$i['unit_price']),2);$grandTotal=round($subtotal-max(0,$discountAmount)+max(0,$taxAmount),2);
            $order=SalesOrder::create(['tenant_id'=>$membership->tenant_id,'company_id'=>$membership->company_id,'branch_id'=>$membership->branch_id,'location_id'=>$membership->location_id,'warehouse_id'=>$warehouse->id,'customer_id'=>$customer?->id,'customer_name_snapshot'=>$customer?->name,'order_number'=>$this->numbers->next('sales_order','SO'),'order_date'=>now()->toDateString(),'status'=>'draft','subtotal'=>$subtotal,'discount_amount'=>max(0,$discountAmount),'tax_amount'=>max(0,$taxAmount),'grand_total'=>$grandTotal,'created_by'=>auth()->id(),'request_id'=>$requestId,'notes'=>$notes]);
            foreach($normalized as $item){$discount=max(0,(float)$item['discount_amount']);$tax=max(0,(float)$item['tax_amount']);$lineBase=$item['quantity']*$item['unit_price'];SalesOrderItem::create(['sales_order_id'=>$order->id,'product_id'=>$item['product_id'],'quantity'=>$item['quantity'],'unit_price'=>$item['unit_price'],'discount_amount'=>$discount,'tax_amount'=>$tax,'line_total'=>round($lineBase-$discount+$tax,2),'notes'=>$item['notes']]);}
            $order->load(['customer','warehouse','items.product','creator']);$this->audit->record('created','sales_order',$order,null,$order->toArray());return $order;});
    }
    public function submit(SalesOrder $order): SalesOrder{return DB::transaction(function()use($order){$this->assertContext($order);$row=SalesOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);if($row->status!=='draft')throw ValidationException::withMessages(['status'=>'Only draft sales orders can be submitted.']);if($row->items->isEmpty())throw ValidationException::withMessages(['items'=>'Sales order must contain at least one item.']);$old=$row->only(['status']);$row->status='submitted';$row->submitted_by=auth()->id();$row->submitted_at=now();$row->save();$row->load(['customer','warehouse','items.product','submitter']);$this->audit->record('submitted','sales_order',$row,$old,['status'=>'submitted']);return $row;});}
    public function cancel(SalesOrder $order): SalesOrder{return DB::transaction(function()use($order){$this->assertContext($order);$row=SalesOrder::query()->lockForUpdate()->findOrFail($order->id);if(!in_array($row->status,['draft','submitted','approved'],true))throw ValidationException::withMessages(['status'=>'Only draft, submitted or approved sales orders can be cancelled.']);$old=$row->only(['status','inventory_reservation_id']);if($row->status==='approved'&&$row->inventory_reservation_id){$reservation=InventoryReservation::query()->where('tenant_id',$row->tenant_id)->where('company_id',$row->company_id)->where('branch_id',$row->branch_id)->findOrFail($row->inventory_reservation_id);if($reservation->status==='active')$this->reservations->release($reservation);$row->inventory_reservation_id=null;}$row->status='cancelled';$row->cancelled_by=auth()->id();$row->cancelled_at=now();$row->save();$row->load(['customer','warehouse','items.product','canceller']);$this->audit->record('cancelled','sales_order',$row,$old,['status'=>'cancelled']);return $row;});}
    private function assertContext(SalesOrder $order): void{$m=$this->context->membership();if(!$m||(int)$order->tenant_id!==(int)$m->tenant_id||(int)$order->company_id!==(int)$m->company_id||(int)$order->branch_id!==(int)$m->branch_id||!in_array((int)$order->warehouse_id,$this->scope->warehouseIds(),true))throw ValidationException::withMessages(['order'=>'Sales order is outside the active organization/location scope.']);}
}
