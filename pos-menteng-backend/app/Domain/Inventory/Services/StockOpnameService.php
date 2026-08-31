<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryOpname;
use App\Domain\Organization\Models\Warehouse;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StockOpnameService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly InventoryService $inventory,
    ) {}

    public function create(Warehouse $warehouse, array $productIds, ?string $notes = null): InventoryOpname
    {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }
        $this->assertWarehouseAccess($warehouse, $membership->tenant_id, $membership->company_id, $membership->branch_id);

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('tenant_id', $membership->tenant_id)
            ->get();

        if ($products->count() !== count(array_unique($productIds))) {
            throw ValidationException::withMessages(['product_ids' => 'One or more products are outside the active tenant or do not exist.']);
        }

        return DB::transaction(function () use ($warehouse, $products, $membership, $notes) {
            $opname = InventoryOpname::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'branch_id' => $membership->branch_id,
                'warehouse_id' => $warehouse->id,
                'opname_number' => 'OPN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'opname_date' => now()->toDateString(),
                'status' => 'draft',
                'notes' => $notes,
                'created_by' => auth()->id(),
                'request_id' => request()->attributes->get('request_id'),
            ]);

            foreach ($products as $product) {
                $balance = InventoryBalance::query()
                    ->where('tenant_id', $membership->tenant_id)
                    ->where('company_id', $membership->company_id)
                    ->where('branch_id', $membership->branch_id)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->first();

                $systemQuantity = (float) ($balance?->quantity ?? 0);
                $unitCost = (float) ($balance?->average_cost ?? 0);

                $opname->items()->create([
                    'product_id' => $product->id,
                    'system_quantity' => $systemQuantity,
                    'counted_quantity' => null,
                    'variance' => null,
                    'unit_cost' => $unitCost,
                ]);
            }

            return $opname->load(['items.product', 'warehouse']);
        });
    }

    public function count(InventoryOpname $opname, array $items): InventoryOpname
    {
        $this->assertOpnameAccess($opname);
        if ($opname->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft stock opnames can be counted.']);
        }

        return DB::transaction(function () use ($opname, $items) {
            foreach ($items as $item) {
                $row = $opname->items()->whereKey($item['item_id'])->first();
                if (! $row) {
                    throw ValidationException::withMessages(['items' => "Opname item {$item['item_id']} not found."]);
                }
                $counted = (float) $item['counted_quantity'];
                $row->update([
                    'counted_quantity' => $counted,
                    'variance' => $counted - (float) $row->system_quantity,
                ]);
            }

            return $opname->load(['items.product', 'warehouse']);
        });
    }

    public function approve(InventoryOpname $opname): InventoryOpname
    {
        $this->assertOpnameAccess($opname);
        if ($opname->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft stock opnames can be approved.']);
        }

        return DB::transaction(function () use ($opname) {
            $opname = InventoryOpname::query()->whereKey($opname->id)->lockForUpdate()->firstOrFail();
            $items = $opname->items()->lockForUpdate()->get();

            foreach ($items as $item) {
                if ($item->counted_quantity === null) {
                    throw ValidationException::withMessages(['items' => "Product {$item->product_id} has not been counted."]);
                }

                $current = InventoryBalance::query()
                    ->where('warehouse_id', $opname->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();
                $currentQuantity = (float) ($current?->quantity ?? 0);

                if (abs($currentQuantity - (float) $item->system_quantity) > 0.0001) {
                    throw ValidationException::withMessages([
                        'conflict' => "Stock changed for product {$item->product_id}. Recreate the opname before approval.",
                    ]);
                }
            }

            foreach ($items as $item) {
                $variance = (float) $item->counted_quantity - (float) $item->system_quantity;
                if (abs($variance) <= 0.0001) {
                    continue;
                }
                $warehouse = $opname->warehouse;
                $product = $item->product;
                $this->inventory->adjust($warehouse, $product, $variance, (float) $item->unit_cost, 'Stock Opname ' . $opname->opname_number);
            }

            $opname->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            return $opname->fresh(['items.product', 'warehouse', 'approver']);
        });
    }

    public function cancel(InventoryOpname $opname): InventoryOpname
    {
        $this->assertOpnameAccess($opname);
        if ($opname->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft stock opnames can be cancelled.']);
        }

        $opname->update([
            'status' => 'cancelled',
            'cancelled_by' => auth()->id(),
            'cancelled_at' => now(),
        ]);

        return $opname->fresh(['items.product', 'warehouse', 'canceller']);
    }

    private function assertWarehouseAccess(Warehouse $warehouse, int $tenantId, int $companyId, int $branchId): void
    {
        $company = $warehouse->branch?->company_id;
        if ((int) $warehouse->branch_id !== $branchId || (int) $company !== $companyId) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is outside the active organization context.']);
        }
    }

    private function assertOpnameAccess(InventoryOpname $opname): void
    {
        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }
        if ((int) $opname->tenant_id !== (int) $membership->tenant_id
            || (int) $opname->company_id !== (int) $membership->company_id
            || (int) $opname->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['opname_id' => 'Stock opname is outside the active organization context.']);
        }
    }
}
