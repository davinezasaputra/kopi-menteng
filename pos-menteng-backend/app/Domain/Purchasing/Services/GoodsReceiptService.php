<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Purchasing\Models\{GoodsReceipt,GoodsReceiptItem,PurchaseOrder,PurchaseOrderItem};
use App\Models\Product;
use App\Domain\Organization\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GoodsReceiptService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly AuditService $audit,
    ) {}

    public function receive(
        PurchaseOrder $order,
        Warehouse $warehouse,
        array $items,
        ?string $notes = null,
    ): GoodsReceipt {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if (
            (int)$order->tenant_id !== (int)$membership->tenant_id ||
            (int)$order->company_id !== (int)$membership->company_id ||
            (int)$order->branch_id !== (int)$membership->branch_id
        ) {
            throw ValidationException::withMessages(['purchase_order_id'=>'Purchase order is outside the active ERP context.']);
        }

        if ((int)$warehouse->branch_id !== (int)$membership->branch_id) {
            throw ValidationException::withMessages(['warehouse_id'=>'Warehouse is outside the active branch.']);
        }

        if ((int)$order->warehouse_id !== (int)$warehouse->id) {
            throw ValidationException::withMessages(['warehouse_id'=>'Goods receipt warehouse must match purchase order warehouse.']);
        }

        if ($order->status !== 'approved' && $order->status !== 'partially_received') {
            throw ValidationException::withMessages(['purchase_order_id'=>'Only approved or partially received purchase orders can be received.']);
        }

        $requestId = request()->attributes->get('request_id');
        $normalized = $this->normalizeItems($order, $items);

        return DB::transaction(function () use ($membership,$order,$warehouse,$normalized,$requestId,$notes) {
            if ($requestId) {
                $existing = GoodsReceipt::query()
                    ->where('tenant_id',$membership->tenant_id)
                    ->where('request_id',$requestId)
                    ->with(['supplier','warehouse','order','items.product'])
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $lockedOrder = PurchaseOrder::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (! in_array($lockedOrder->status,['approved','partially_received'],true)) {
                throw ValidationException::withMessages(['purchase_order_id'=>'Purchase order is not receivable in its current status.']);
            }

            $orderItems = $lockedOrder->items->keyBy('id');

            $receipt = GoodsReceipt::create([
                'tenant_id'=>$membership->tenant_id,
                'company_id'=>$membership->company_id,
                'branch_id'=>$membership->branch_id,
                'warehouse_id'=>$warehouse->id,
                'supplier_id'=>$lockedOrder->supplier_id,
                'purchase_order_id'=>$lockedOrder->id,
                'receipt_number'=>$this->numbers->next('goods_receipt','GR'),
                'receipt_date'=>now()->toDateString(),
                'status'=>'posted',
                'received_by'=>auth()->id(),
                'request_id'=>$requestId,
                'notes'=>$notes,
            ]);

            foreach ($normalized as $item) {
                /** @var PurchaseOrderItem $orderItem */
                $orderItem = $orderItems->get($item['purchase_order_item_id']);

                if (! $orderItem) {
                    throw ValidationException::withMessages(['items'=>'Purchase order item not found.']);
                }

                $ordered = (float)$orderItem->quantity;
                $alreadyReceived = (float)$orderItem->received_quantity;
                $remaining = $ordered - $alreadyReceived;

                if ($item['quantity'] > $remaining) {
                    throw ValidationException::withMessages([
                        'items'=>"Receipt quantity exceeds remaining PO quantity for product {$orderItem->product_id}. Remaining: {$remaining}.",
                    ]);
                }

                $this->inventory->receive(
                    $warehouse,
                    Product::findOrFail($item['product_id']),
                    $item['quantity'],
                    $item['unit_cost'],
                    'goods_receipt',
                    (string)$receipt->id,
                    $notes,
                );

                $orderItem->received_quantity = $alreadyReceived + $item['quantity'];
                $orderItem->save();

                GoodsReceiptItem::create([
                    'goods_receipt_id'=>$receipt->id,
                    'purchase_order_item_id'=>$orderItem->id,
                    'product_id'=>$orderItem->product_id,
                    'ordered_quantity'=>$ordered,
                    'received_quantity'=>$item['quantity'],
                    'unit_cost'=>$item['unit_cost'],
                    'line_value'=>round($item['quantity'] * $item['unit_cost'],2),
                ]);
            }

            $lockedOrder->status = $this->deriveOrderStatus($lockedOrder->fresh('items'));
            $lockedOrder->save();

            $receipt->load(['supplier','warehouse','order','items.product']);
            $this->audit->record('posted','goods_receipt',$receipt,null,$receipt->toArray());

            return $receipt;
        });
    }

    private function normalizeItems(PurchaseOrder $order, array $items): array
    {
        $orderItems = $order->items()->get()->keyBy('id');
        $result=[];

        foreach ($items as $item) {
            $id=(int)$item['purchase_order_item_id'];
            $row=$orderItems->get($id);

            if (! $row) {
                throw ValidationException::withMessages(['items'=>'Invalid purchase order item.']);
            }

            $quantity=(float)$item['quantity'];
            $unitCost=(float)$item['unit_cost'];

            if ($quantity <= 0 || $unitCost <= 0) {
                throw ValidationException::withMessages(['items'=>'Receipt quantity and unit cost must be greater than zero.']);
            }

            if (! isset($result[$id])) {
                $result[$id]=[
                    'purchase_order_item_id'=>$id,
                    'product_id'=>$row->product_id,
                    'quantity'=>0,
                    'unit_cost'=>$unitCost,
                ];
            }

            $result[$id]['quantity'] += $quantity;
            $result[$id]['unit_cost']=$unitCost;
        }

        return array_values($result);
    }

    private function deriveOrderStatus(PurchaseOrder $order): string
    {
        $items=$order->items;

        if ($items->every(fn (PurchaseOrderItem $item) => (float)$item->received_quantity >= (float)$item->quantity)) {
            return 'received';
        }

        if ($items->contains(fn (PurchaseOrderItem $item) => (float)$item->received_quantity > 0)) {
            return 'partially_received';
        }

        return 'approved';
    }
}
