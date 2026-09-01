<?php

namespace App\Domain\Sales\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Sales\Models\{SalesFulfillment,SalesOrder};
use App\Domain\Inventory\Models\InventoryReservation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesFulfillmentService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly AuditService $audit,
    ) {}

    public function createFromApprovedOrder(SalesOrder $order): SalesFulfillment
    {
        $this->assertContext($order);

        return DB::transaction(function () use ($order) {
            $row = SalesOrder::query()
                ->with(['items','inventoryReservation'])
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($row->status !== 'approved') {
                throw ValidationException::withMessages(['status'=>'Only approved sales orders can create fulfillment.']);
            }

            if (! $row->inventory_reservation_id) {
                throw ValidationException::withMessages(['reservation'=>'Sales order has no inventory reservation.']);
            }

            $reservation = InventoryReservation::query()
                ->with('items')
                ->where('tenant_id',$row->tenant_id)
                ->where('company_id',$row->company_id)
                ->where('branch_id',$row->branch_id)
                ->findOrFail($row->inventory_reservation_id);

            if ($reservation->status !== 'active') {
                throw ValidationException::withMessages(['reservation'=>'Inventory reservation is not active.']);
            }

            $existing = SalesFulfillment::query()
                ->where('tenant_id',$row->tenant_id)
                ->where('sales_order_id',$row->id)
                ->with(['items.product','salesOrder'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $reservationItems = $reservation->items->keyBy('product_id');

            $fulfillment = SalesFulfillment::create([
                'tenant_id'=>$row->tenant_id,
                'company_id'=>$row->company_id,
                'branch_id'=>$row->branch_id,
                'warehouse_id'=>$row->warehouse_id,
                'sales_order_id'=>$row->id,
                'fulfillment_number'=>$this->numbers->next('sales_fulfillment','FUL'),
                'status'=>'draft',
                'created_by'=>auth()->id(),
                'notes'=>'Generated from '.$row->order_number,
            ]);

            foreach ($row->items as $orderItem) {
                $reservationItem = $reservationItems->get($orderItem->product_id);
                if (! $reservationItem) {
                    throw ValidationException::withMessages([
                        'reservation'=>"No reservation found for product {$orderItem->product_id}."
                    ]);
                }

                $reserved = (float)$reservationItem->quantity - (float)$reservationItem->fulfilled_quantity;
                if ($reserved + 0.0001 < (float)$orderItem->quantity) {
                    throw ValidationException::withMessages([
                        'reservation'=>"Reserved quantity is insufficient for product {$orderItem->product_id}."
                    ]);
                }

                $fulfillment->items()->create([
                    'product_id'=>$orderItem->product_id,
                    'ordered_quantity'=>$orderItem->quantity,
                    'reserved_quantity'=>$reserved,
                    'picked_quantity'=>0,
                    'packed_quantity'=>0,
                ]);
            }

            $fulfillment->load(['items.product','salesOrder']);
            $this->audit->record('created','sales_fulfillment',$fulfillment,null,$fulfillment->toArray());

            return $fulfillment;
        });
    }

    public function pick(SalesFulfillment $fulfillment, array $items): SalesFulfillment
    {
        return DB::transaction(function () use ($fulfillment,$items) {
            $this->assertContext($fulfillment);

            $row=SalesFulfillment::query()->with('items')->lockForUpdate()->findOrFail($fulfillment->id);

            if (!in_array($row->status,['draft','picking'],true)) {
                throw ValidationException::withMessages(['status'=>'Only draft or picking fulfillment can be picked.']);
            }

            $updates=$this->normalizeQuantities($items);
            foreach($updates as $itemId=>$qty){
                $item=$row->items->firstWhere('id',$itemId);
                if(!$item){
                    throw ValidationException::withMessages(['items'=>'Fulfillment item not found.']);
                }

                $newPicked=(float)$item->picked_quantity+$qty;
                if($newPicked>(float)$item->reserved_quantity){
                    throw ValidationException::withMessages(['items'=>'Picked quantity cannot exceed reserved quantity.']);
                }

                $item->picked_quantity=$newPicked;
                $item->save();
            }

            $allPicked=$row->items->every(fn($i)=>(float)$i->picked_quantity >= (float)$i->reserved_quantity);
            $row->status=$allPicked?'picked':'picking';
            if($row->status==='picked'){
                $row->picked_by=auth()->id();
                $row->picked_at=now();
            }
            $row->save();

            $this->audit->record('picked','sales_fulfillment',$row,null,['status'=>$row->status]);
            return $row->fresh(['items.product','salesOrder']);
        });
    }

    public function pack(SalesFulfillment $fulfillment): SalesFulfillment
    {
        return DB::transaction(function () use ($fulfillment) {
            $this->assertContext($fulfillment);

            $row=SalesFulfillment::query()->with('items')->lockForUpdate()->findOrFail($fulfillment->id);
            if($row->status!=='picked'){
                throw ValidationException::withMessages(['status'=>'Only fully picked fulfillment can be packed.']);
            }

            foreach($row->items as $item){
                $item->packed_quantity=$item->picked_quantity;
                $item->save();
            }

            $row->status='packed';
            $row->packed_by=auth()->id();
            $row->packed_at=now();
            $row->save();

            $this->audit->record('packed','sales_fulfillment',$row,null,['status'=>'packed']);
            return $row->fresh(['items.product','salesOrder']);
        });
    }

    private function normalizeQuantities(array $items): array
    {
        $result=[];
        foreach($items as $item){
            $id=(string)($item['sales_fulfillment_item_id']??'');
            $qty=(float)($item['quantity']??0);
            if($id===''||$qty<=0){
                throw ValidationException::withMessages(['items'=>'Each picking item requires a valid item id and positive quantity.']);
            }
            $result[$id]=($result[$id]??0)+$qty;
        }
        return $result;
    }

    private function assertContext(SalesOrder|SalesFulfillment $model): void
    {
        $membership=$this->context->membership();
        if(!$membership ||
            (int)$model->tenant_id !== (int)$membership->tenant_id ||
            (int)$model->company_id !== (int)$membership->company_id ||
            (int)$model->branch_id !== (int)$membership->branch_id
        ){
            throw ValidationException::withMessages(['fulfillment'=>'Document is outside the active ERP context.']);
        }
    }
}
