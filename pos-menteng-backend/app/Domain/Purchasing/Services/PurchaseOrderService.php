<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Core\Services\DocumentNumberService;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Models\{PurchaseOrder,PurchaseOrderItem,PurchaseRequisition,Supplier};
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumberService $numbers,
        private readonly AuditService $audit,
    ) {}

    public function create(
        Supplier $supplier,
        Warehouse $warehouse,
        array $items,
        ?PurchaseRequisition $requisition = null,
        ?string $expectedDate = null,
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?string $notes = null,
    ): PurchaseOrder {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context'=>'No active ERP context.']);
        }

        if (
            (int)$supplier->tenant_id !== (int)$membership->tenant_id ||
            (int)$supplier->company_id !== (int)$membership->company_id
        ) {
            throw ValidationException::withMessages(['supplier_id'=>'Supplier is outside the active company.']);
        }

        if ((int)$warehouse->branch_id !== (int)$membership->branch_id) {
            throw ValidationException::withMessages(['warehouse_id'=>'Warehouse is outside the active branch.']);
        }

        if ($requisition) {
            if (
                (int)$requisition->tenant_id !== (int)$membership->tenant_id ||
                (int)$requisition->company_id !== (int)$membership->company_id ||
                (int)$requisition->branch_id !== (int)$membership->branch_id
            ) {
                throw ValidationException::withMessages(['purchase_requisition_id'=>'Requisition is outside the active ERP context.']);
            }

            if ($requisition->status !== 'submitted') {
                throw ValidationException::withMessages(['purchase_requisition_id'=>'Only submitted requisitions can create purchase orders.']);
            }
        }

        $normalized = $this->normalizeItems($items, (int)$membership->tenant_id);

        return DB::transaction(function () use ($membership,$supplier,$warehouse,$normalized,$requisition,$expectedDate,$discountAmount,$taxAmount,$notes) {
            $subtotal = round(collect($normalized)->sum(fn ($i) => $i['quantity'] * $i['unit_cost']), 2);
            $grandTotal = round($subtotal - max(0,$discountAmount) + max(0,$taxAmount), 2);

            $order = PurchaseOrder::create([
                'tenant_id'=>$membership->tenant_id,
                'company_id'=>$membership->company_id,
                'branch_id'=>$membership->branch_id,
                'warehouse_id'=>$warehouse->id,
                'supplier_id'=>$supplier->id,
                'purchase_requisition_id'=>$requisition?->id,
                'order_number'=>$this->numbers->next('purchase_order','PO'),
                'status'=>'draft',
                'order_date'=>now()->toDateString(),
                'expected_date'=>$expectedDate,
                'subtotal'=>$subtotal,
                'discount_amount'=>max(0,$discountAmount),
                'tax_amount'=>max(0,$taxAmount),
                'grand_total'=>$grandTotal,
                'created_by'=>auth()->id(),
                'request_id'=>request()->attributes->get('request_id'),
                'notes'=>$notes,
            ]);

            foreach ($normalized as $item) {
                $discount = max(0,(float)$item['discount_amount']);
                $tax = max(0,(float)$item['tax_amount']);
                $lineBase = $item['quantity'] * $item['unit_cost'];
                $lineTotal = round($lineBase - $discount + $tax, 2);

                PurchaseOrderItem::create([
                    'purchase_order_id'=>$order->id,
                    'product_id'=>$item['product_id'],
                    'quantity'=>$item['quantity'],
                    'unit_cost'=>$item['unit_cost'],
                    'discount_amount'=>$discount,
                    'tax_amount'=>$tax,
                    'line_total'=>$lineTotal,
                    'received_quantity'=>0,
                    'notes'=>$item['notes'],
                ]);
            }

            $order->load(['supplier','warehouse','requisition','items.product','creator']);
            $this->audit->record('created','purchase_order',$order,null,$order->toArray());

            return $order;
        });
    }

    public function submit(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $this->assertContext($order);
            $row = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if ($row->status !== 'draft') {
                throw ValidationException::withMessages(['status'=>'Only draft purchase orders can be submitted.']);
            }
            if ($row->items->isEmpty()) {
                throw ValidationException::withMessages(['items'=>'Purchase order must contain at least one item.']);
            }

            $old=$row->only(['status']);
            $row->status='submitted';
            $row->submitted_by=auth()->id();
            $row->submitted_at=now();
            $row->save();

            $row->load(['supplier','warehouse','items.product','submitter']);
            $this->audit->record('submitted','purchase_order',$row,$old,['status'=>'submitted']);
            return $row;
        });
    }

    public function approve(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $this->assertContext($order);
            $row = PurchaseOrder::query()->with('items')->lockForUpdate()->findOrFail($order->id);

            if ($row->status !== 'submitted') {
                throw ValidationException::withMessages(['status'=>'Only submitted purchase orders can be approved.']);
            }

            $old=$row->only(['status']);
            $row->status='approved';
            $row->approved_by=auth()->id();
            $row->approved_at=now();
            $row->save();

            $row->load(['supplier','warehouse','items.product','approver']);
            $this->audit->record('approved','purchase_order',$row,$old,['status'=>'approved']);
            return $row;
        });
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        return DB::transaction(function () use ($order) {
            $this->assertContext($order);
            $row = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);

            if (! in_array($row->status,['draft','submitted'],true)) {
                throw ValidationException::withMessages(['status'=>'Only draft or submitted purchase orders can be cancelled.']);
            }

            $old=$row->only(['status']);
            $row->status='cancelled';
            $row->cancelled_by=auth()->id();
            $row->cancelled_at=now();
            $row->save();

            $row->load(['supplier','warehouse','items.product','canceller']);
            $this->audit->record('cancelled','purchase_order',$row,$old,['status'=>'cancelled']);
            return $row;
        });
    }

    private function normalizeItems(array $items, int $tenantId): array
    {
        $result=[];
        foreach ($items as $item) {
            $product=Product::findOrFail($item['product_id']);
            if ((int)$product->tenant_id !== $tenantId) {
                throw ValidationException::withMessages(['items'=>'One or more products are outside the active tenant.']);
            }

            $quantity=(float)$item['quantity'];
            $unitCost=(float)$item['unit_cost'];
            if ($quantity <= 0 || $unitCost < 0) {
                throw ValidationException::withMessages(['items'=>'Quantity must be greater than zero and unit cost cannot be negative.']);
            }

            $id=(string)$product->id;
            if (!isset($result[$id])) {
                $result[$id]=[
                    'product_id'=>$product->id,'quantity'=>0,'unit_cost'=>$unitCost,
                    'discount_amount'=>(float)($item['discount_amount']??0),
                    'tax_amount'=>(float)($item['tax_amount']??0),'notes'=>$item['notes']??null,
                ];
            }
            $result[$id]['quantity'] += $quantity;
            $result[$id]['unit_cost'] = $unitCost;
        }
        return array_values($result);
    }

    private function assertContext(PurchaseOrder $order): void
    {
        $membership=$this->context->membership();
        if (
            ! $membership ||
            (int)$order->tenant_id !== (int)$membership->tenant_id ||
            (int)$order->company_id !== (int)$membership->company_id ||
            (int)$order->branch_id !== (int)$membership->branch_id
        ) {
            throw ValidationException::withMessages(['order'=>'Purchase order is outside the active ERP context.']);
        }
    }
}
