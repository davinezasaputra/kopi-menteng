<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Accounting\Models\ErpAccount;
use App\Domain\Accounting\Services\ErpAccountingService;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\{GoodsReceipt,GoodsReceiptItem,PurchaseOrder,SupplierReturn,SupplierReturnItem};
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierReturnService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly InventoryService $inventory,
        private readonly ErpAccountingService $accounting,
        private readonly AuditService $audit,
    ) {}

    public function createAndPost(
        PurchaseOrder $order,
        GoodsReceipt $receipt,
        Warehouse $warehouse,
        array $items,
        ?string $reason = null,
        ?string $notes = null,
    ): SupplierReturn {
        $membership=$this->context->membership();

        if (!$membership) {
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if (
            (int)$order->tenant_id !== (int)$membership->tenant_id ||
            (int)$order->company_id !== (int)$membership->company_id ||
            (int)$order->branch_id !== (int)$membership->branch_id ||
            (int)$receipt->tenant_id !== (int)$membership->tenant_id ||
            (int)$receipt->company_id !== (int)$membership->company_id ||
            (int)$receipt->branch_id !== (int)$membership->branch_id
        ) {
            throw ValidationException::withMessages(['document'=>'Purchase order or goods receipt is outside the active ERP context.']);
        }

        if ((int)$receipt->purchase_order_id !== (int)$order->id) {
            throw ValidationException::withMessages(['goods_receipt_id'=>'Goods receipt does not belong to purchase order.']);
        }

        if ((int)$warehouse->id !== (int)$receipt->warehouse_id) {
            throw ValidationException::withMessages(['warehouse_id'=>'Return warehouse must match goods receipt warehouse.']);
        }

        $requestId=request()->attributes->get('request_id');
        return DB::transaction(function() use ($membership,$order,$receipt,$warehouse,$items,$reason,$notes,$requestId) {
            if($requestId){
                $existing=SupplierReturn::query()
                    ->where('tenant_id',$membership->tenant_id)
                    ->where('request_id',$requestId)
                    ->with(['supplier','warehouse','order','goodsReceipt','items.product'])
                    ->first();
                if($existing) return $existing;
            }

            $lockedReceipt=GoodsReceipt::query()->with('items')->lockForUpdate()->findOrFail($receipt->id);
            $receiptItems=$lockedReceipt->items->keyBy('id');

            $normalized=[];
            foreach($items as $item){
                $grItem=$receiptItems->get((int)$item['goods_receipt_item_id']);
                if(!$grItem){
                    throw ValidationException::withMessages(['items'=>'Invalid goods receipt item.']);
                }

                $qty=(float)$item['quantity'];
                if($qty<=0){
                    throw ValidationException::withMessages(['items'=>'Return quantity must be greater than zero.']);
                }

                $alreadyReturned=(float)SupplierReturnItem::query()
                    ->where('goods_receipt_item_id',$grItem->id)
                    ->sum('returned_quantity');

                $remaining=max(0,(float)$grItem->received_quantity-$alreadyReturned);
                if($qty>$remaining){
                    throw ValidationException::withMessages([
                        'items'=>"Return quantity exceeds remaining received quantity for product {$grItem->product_id}. Remaining: {$remaining}."
                    ]);
                }

                $normalized[]=[
                    'goods_receipt_item_id'=>$grItem->id,
                    'product_id'=>$grItem->product_id,
                    'quantity'=>$qty,
                    'unit_cost'=>(float)$grItem->unit_cost,
                ];
            }

            $return=SupplierReturn::create([
                'tenant_id'=>$membership->tenant_id,
                'company_id'=>$membership->company_id,
                'branch_id'=>$membership->branch_id,
                'warehouse_id'=>$warehouse->id,
                'supplier_id'=>$lockedReceipt->supplier_id,
                'purchase_order_id'=>$lockedReceipt->purchase_order_id,
                'goods_receipt_id'=>$lockedReceipt->id,
                'return_number'=>$this->numbers->next('supplier_return','SR'),
                'return_date'=>now()->toDateString(),
                'status'=>'posted',
                'created_by'=>auth()->id(),
                'request_id'=>$requestId,
                'reason'=>$reason,
                'notes'=>$notes,
            ]);

            $totalValue=0.0;
            foreach($normalized as $item){
                $product=Product::findOrFail($item['product_id']);
                $this->inventory->issue(
                    $warehouse,
                    $product,
                    $item['quantity'],
                    'supplier_return',
                    (string)$return->id,
                    $notes
                );

                $lineValue=round($item['quantity']*$item['unit_cost'],2);
                $totalValue+=$lineValue;

                SupplierReturnItem::create([
                    'supplier_return_id'=>$return->id,
                    'goods_receipt_item_id'=>$item['goods_receipt_item_id'],
                    'product_id'=>$item['product_id'],
                    'received_quantity'=>$receiptItems->get($item['goods_receipt_item_id'])->received_quantity,
                    'returned_quantity'=>$item['quantity'],
                    'unit_cost'=>$item['unit_cost'],
                    'line_value'=>$lineValue,
                ]);
            }

            $inventoryAccount=$this->accountByCode('1100');
            $apAccount=$this->accountByCode('2100');

            $this->accounting->postSourceJournal(
                'supplier_return',
                (string)$return->id,
                'Supplier return ' . $return->return_number,
                [
                    ['account_id'=>$apAccount->id,'debit'=>$totalValue,'credit'=>0,'description'=>'Supplier return reduction of AP'],
                    ['account_id'=>$inventoryAccount->id,'debit'=>0,'credit'=>$totalValue,'description'=>'Inventory returned to supplier'],
                ],
                (int)$return->branch_id,
                $return->return_date
            );

            $this->audit->record('posted','supplier_return',$return,null,$return->toArray());

            return $return->load(['supplier','warehouse','order','goodsReceipt','items.product']);
        });
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
