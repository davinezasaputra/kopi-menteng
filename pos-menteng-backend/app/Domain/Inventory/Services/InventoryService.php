<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Organization\Models\Warehouse;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function receive(
        Warehouse $warehouse,
        Product $product,
        float $quantity,
        float $unitCost = 0,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null,
    ): InventoryBalance {
        return $this->move($warehouse, $product, abs($quantity), $unitCost, 'receive', $referenceType, $referenceId, $notes);
    }

    public function issue(
        Warehouse $warehouse,
        Product $product,
        float $quantity,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null,
    ): InventoryBalance {
        return $this->move($warehouse, $product, -abs($quantity), 0, 'sale_issue', $referenceType, $referenceId, $notes);
    }

    public function adjust(
        Warehouse $warehouse,
        Product $product,
        float $quantityDelta,
        float $unitCost = 0,
        ?string $notes = null,
    ): InventoryBalance {
        if ($quantityDelta == 0.0) {
            throw ValidationException::withMessages(['quantity' => 'Adjustment quantity cannot be zero.']);
        }

        return $this->move(
            $warehouse,
            $product,
            $quantityDelta,
            $unitCost,
            $quantityDelta > 0 ? 'adjustment_in' : 'adjustment_out',
            null,
            null,
            $notes,
        );
    }

    private function move(
        Warehouse $warehouse,
        Product $product,
        float $delta,
        float $unitCost,
        string $movementType,
        ?string $referenceType,
        ?string $referenceId,
        ?string $notes,
    ): InventoryBalance {
        $membership = $this->context->membership();

        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if ((int) $warehouse->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['warehouse_id' => 'Warehouse is outside the active branch.']);
        }

        if ((int) $product->tenant_id !== (int) $membership->tenant_id) {
            throw ValidationException::withMessages(['product_id' => 'Product is outside the active tenant.']);
        }

        return DB::transaction(function () use (
            $membership,
            $warehouse,
            $product,
            $delta,
            $unitCost,
            $movementType,
            $referenceType,
            $referenceId,
            $notes,
        ) {
            $balance = InventoryBalance::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = InventoryBalance::create([
                    'tenant_id' => $membership->tenant_id,
                    'company_id' => $membership->company_id,
                    'branch_id' => $membership->branch_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'quantity' => 0,
                    'reserved_quantity' => 0,
                    'average_cost' => 0,
                ]);
            }

            $before = (float) $balance->quantity;
            $after = $before + $delta;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient stock. Available: {$before}.",
                ]);
            }

            if ($delta > 0 && $unitCost > 0) {
                $oldValue = $before * (float) $balance->average_cost;
                $newValue = $delta * $unitCost;
                $balance->average_cost = $after > 0
                    ? ($oldValue + $newValue) / $after
                    : $unitCost;
            }

            $balance->quantity = $after;
            $balance->save();

            StockMovement::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'branch_id' => $membership->branch_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => $movementType,
                'quantity' => $delta,
                'unit_cost' => $unitCost,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => auth()->id(),
                'request_id' => request()->attributes->get('request_id'),
                'notes' => $notes,
                'created_at' => now(),
            ]);

            // Backward-compatible projection for the existing POS/catalog API.
            $legacyStock = InventoryBalance::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('product_id', $product->id)
                ->sum('quantity');

            $product->update(['stock' => max(0, (int) round((float) $legacyStock))]);

            return $balance->fresh();
        });
    }
}
