<?php

namespace App\Domain\Sales\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Sales\Models\{SalesFulfillment,SalesShipment};
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesShipmentService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly InventoryReservationService $reservations,
        private readonly AuditService $audit,
        private readonly ErpAccountingService $accounting,
    ) {}

    public function ship(
        SalesFulfillment $fulfillment,
        ?string $carrierName = null,
        ?string $trackingNumber = null,
        ?string $notes = null,
    ): SalesShipment {
        return DB::transaction(function () use ($fulfillment,$carrierName,$trackingNumber,$notes) {
            $this->assertContext($fulfillment);

            $row=SalesFulfillment::query()
                ->with(['items','salesOrder','salesOrder.inventoryReservation'])
                ->lockForUpdate()
                ->findOrFail($fulfillment->id);

            if($row->status!=='packed'){
                throw ValidationException::withMessages([
                    'status'=>'Only packed fulfillment can be shipped.'
                ]);
            }

            $existing=SalesShipment::query()
                ->where('tenant_id',$row->tenant_id)
                ->where('sales_fulfillment_id',$row->id)
                ->with(['salesOrder','fulfillment','inventoryReservation','shipper'])
                ->first();

            if($existing){
                return $existing;
            }

            foreach($row->items as $item){
                if((float)$item->packed_quantity !== (float)$item->picked_quantity ||
                    (float)$item->packed_quantity !== (float)$item->reserved_quantity){
                    throw ValidationException::withMessages([
                        'items'=>'Shipment requires all fulfillment quantities to be fully packed.'
                    ]);
                }
            }

            $reservationId=$row->salesOrder?->inventory_reservation_id;
            if(!$reservationId){
                throw ValidationException::withMessages([
                    'reservation'=>'Sales order has no linked inventory reservation.'
                ]);
            }

            $reservation=InventoryReservation::query()
                ->where('tenant_id',$row->tenant_id)
                ->where('company_id',$row->company_id)
                ->where('branch_id',$row->branch_id)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($reservationId);

            if($reservation->status!=='active'){
                throw ValidationException::withMessages([
                    'reservation'=>"Reservation must be active to ship. Current status: {$reservation->status}."
                ]);
            }

            $expected = $reservation->items->sum(fn($item)=>(float)$item->quantity);
            $packed = $row->items->sum(fn($item)=>(float)$item->packed_quantity);

            if(abs($expected-$packed)>0.0001){
                throw ValidationException::withMessages([
                    'items'=>'Packed quantity does not match the reserved quantity.'
                ]);
            }

            // Physical stock issue happens exactly once inside the existing
            // reservation fulfillment engine.
            // Capture inventory cost before the reservation fulfillment changes the balance.
            $totalCost = round(
                $row->items->sum(function ($item) use ($row) {
                    return (float)$item->packed_quantity *
                        (float)\App\Domain\Inventory\Models\InventoryBalance::query()
                            ->where('tenant_id',$row->tenant_id)
                            ->where('company_id',$row->company_id)
                            ->where('branch_id',$row->branch_id)
                            ->where('warehouse_id',$row->warehouse_id)
                            ->where('product_id',$item->product_id)
                            ->value('average_cost');
                }),
                2
            );

            $this->reservations->fulfill($reservation);

            $shipment=SalesShipment::create([
                'tenant_id'=>$row->tenant_id,
                'company_id'=>$row->company_id,
                'branch_id'=>$row->branch_id,
                'warehouse_id'=>$row->warehouse_id,
                'sales_order_id'=>$row->sales_order_id,
                'sales_fulfillment_id'=>$row->id,
                'inventory_reservation_id'=>$reservation->id,
                'shipment_number'=>$this->numbers->next('sales_shipment','SHP'),
                'shipment_date'=>now()->toDateString(),
                'status'=>'shipped',
                'carrier_name'=>$carrierName,
                'tracking_number'=>$trackingNumber,
                'shipped_by'=>auth()->id(),
                'shipped_at'=>now(),
                'request_id'=>request()->attributes->get('request_id'),
                'notes'=>$notes,
            ]);

            $cogs=ErpAccount::where('tenant_id',$row->tenant_id)->where('company_id',$row->company_id)->where('code','5000')->where('is_active',true)->where('is_postable',true)->firstOrFail();
            $inventory=ErpAccount::where('tenant_id',$row->tenant_id)->where('company_id',$row->company_id)->where('code','1100')->where('is_active',true)->where('is_postable',true)->firstOrFail();

            if ($totalCost > 0) {
                $this->accounting->postSourceJournal(
                    'sales_shipment',
                    (string)$shipment->id,
                    'Sales shipment '.$shipment->shipment_number,
                    [
                        ['account_id'=>$cogs->id,'debit'=>$totalCost,'credit'=>0,'description'=>'Recognize cost of goods sold'],
                        ['account_id'=>$inventory->id,'debit'=>0,'credit'=>$totalCost,'description'=>'Reduce inventory asset'],
                    ],
                    (int)$row->branch_id
                );
            }

            $old=$row->only(['status']);
            $row->status='fulfilled';
            $row->save();

            $row->load(['items.product','salesOrder']);
            $this->audit->record('shipped','sales_shipment',$shipment,null,$shipment->toArray());
            $this->audit->record('fulfilled','sales_fulfillment',$row,$old,['status'=>'fulfilled']);

            return $shipment->fresh(['salesOrder','fulfillment','inventoryReservation','shipper']);
        });
    }

    private function assertContext(SalesFulfillment $fulfillment): void
    {
        $membership=$this->context->membership();

        if(!$membership ||
            (int)$fulfillment->tenant_id !== (int)$membership->tenant_id ||
            (int)$fulfillment->company_id !== (int)$membership->company_id ||
            (int)$fulfillment->branch_id !== (int)$membership->branch_id){
            throw ValidationException::withMessages([
                'fulfillment'=>'Fulfillment is outside the active ERP context.'
            ]);
        }
    }
}
