<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryTransfer;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Organization\Models\Warehouse;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(private readonly TenantContext $context) {}

    public function receive(Warehouse $warehouse, Product $product, float $quantity, float $unitCost = 0, ?string $referenceType = null, ?string $referenceId = null, ?string $notes = null): InventoryBalance
    {
        return $this->move($warehouse, $product, abs($quantity), $unitCost, 'receive', $referenceType, $referenceId, $notes);
    }

    public function issue(Warehouse $warehouse, Product $product, float $quantity, ?string $referenceType = null, ?string $referenceId = null, ?string $notes = null): InventoryBalance
    {
        return $this->move($warehouse, $product, -abs($quantity), 0, 'sale_issue', $referenceType, $referenceId, $notes);
    }

    public function adjust(Warehouse $warehouse, Product $product, float $quantityDelta, float $unitCost = 0, ?string $notes = null): InventoryBalance
    {
        if ($quantityDelta == 0.0) {
            throw ValidationException::withMessages(['quantity' => 'Adjustment quantity cannot be zero.']);
        }
        return $this->move($warehouse, $product, $quantityDelta, $unitCost, $quantityDelta > 0 ? 'adjustment_in' : 'adjustment_out', null, null, $notes);
    }

    public function transfer(Warehouse $fromWarehouse, Warehouse $toWarehouse, Product $product, float $quantity, float $unitCost = 0, ?string $notes = null): array
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Transfer quantity must be greater than zero.']);
        }

        $membership = $this->context->membership();
        if (! $membership) {
            throw ValidationException::withMessages(['context' => 'No active ERP context.']);
        }

        if ((int) $fromWarehouse->branch_id !== (int) $membership->branch_id) {
            throw ValidationException::withMessages(['from_warehouse_id' => 'Source warehouse is outside the active branch.']);
        }

        if ((int) $fromWarehouse->branch?->company_id !== (int) $membership->company_id || (int) $toWarehouse->branch?->company_id !== (int) $membership->company_id) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'Warehouse is outside the active company.']);
        }

        if ((int) $product->tenant_id !== (int) $membership->tenant_id) {
            throw ValidationException::withMessages(['product_id' => 'Product is outside the active tenant.']);
        }

        if ((int) $fromWarehouse->id === (int) $toWarehouse->id) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'Destination warehouse must differ from source warehouse.']);
        }

        return DB::transaction(function () use ($membership, $fromWarehouse, $toWarehouse, $product, $quantity, $unitCost, $notes) {
            $firstId = min((int) $fromWarehouse->id, (int) $toWarehouse->id);
            $secondId = max((int) $fromWarehouse->id, (int) $toWarehouse->id);
            $balances = InventoryBalance::query()
                ->where('product_id', $product->id)
                ->whereIn('warehouse_id', [$firstId, $secondId])
                ->lockForUpdate()
                ->get()
                ->keyBy('warehouse_id');

            foreach ([$fromWarehouse, $toWarehouse] as $warehouse) {
                if (! $balances->has($warehouse->id)) {
                    $balances->put($warehouse->id, InventoryBalance::create([
                        'tenant_id' => $membership->tenant_id,
                        'company_id' => $warehouse->branch?->company_id ?? $membership->company_id,
                        'branch_id' => $warehouse->branch_id,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $product->id,
                        'quantity' => 0,
                        'reserved_quantity' => 0,
                        'average_cost' => 0,
                    ]));
                }
            }

            $source = $balances->get($fromWarehouse->id);
            $destination = $balances->get($toWarehouse->id);
            $sourceBefore = (float) $source->quantity;
            $destinationBefore = (float) $destination->quantity;
            if ($sourceBefore < $quantity) {
                throw ValidationException::withMessages(['quantity' => "Insufficient stock in source warehouse. Available: {$sourceBefore}."]);
            }

            $sourceAfter = $sourceBefore - $quantity;
            $destinationAfter = $destinationBefore + $quantity;
            $cost = $this->costing->transferUnitCost($source, $unitCost);

            $source->quantity = $sourceAfter;
            $source->available_quantity = $sourceAfter - (float) $source->reserved_quantity;
            $source->save();

            if ($cost > 0) {
                $destination->average_cost = $destinationAfter > 0
                    ? (($destinationBefore * (float) $destination->average_cost) + ($quantity * $cost)) / $destinationAfter
                    : $cost;
                $destination->last_cost = $cost;
            }
            $destination->quantity = $destinationAfter;
            $destination->available_quantity = $destinationAfter - (float) $destination->reserved_quantity;
            $destination->save();

            $requestId = request()->attributes->get('request_id');
            $transfer = InventoryTransfer::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'from_branch_id' => $fromWarehouse->branch_id,
                'from_warehouse_id' => $fromWarehouse->id,
                'to_branch_id' => $toWarehouse->branch_id,
                'to_warehouse_id' => $toWarehouse->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $cost,
                'transfer_number' => 'TRF-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'status' => 'completed',
                'created_by' => auth()->id(),
                'request_id' => $requestId,
                'notes' => $notes,
            ]);

            $base = [
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'product_id' => $product->id,
                'unit_cost' => $cost,
                'reference_type' => 'inventory_transfer',
                'reference_id' => $transfer->id,
                'created_by' => auth()->id(),
                'request_id' => $requestId,
                'notes' => $notes,
                'created_at' => now(),
            ];

            StockMovement::create($base + [
                'branch_id' => $fromWarehouse->branch_id,
                'warehouse_id' => $fromWarehouse->id,
                'movement_type' => 'transfer_out',
                'quantity' => -$quantity,
                'balance_before' => $sourceBefore,
                'balance_after' => $sourceAfter,
            ]);

            StockMovement::create($base + [
                'branch_id' => $toWarehouse->branch_id,
                'warehouse_id' => $toWarehouse->id,
                'movement_type' => 'transfer_in',
                'quantity' => $quantity,
                'balance_before' => $destinationBefore,
                'balance_after' => $destinationAfter,
            ]);

            $legacyStock = InventoryBalance::query()
                ->where('tenant_id', $membership->tenant_id)
                ->where('product_id', $product->id)
                ->sum('quantity');
            $product->update(['stock' => max(0, (int) round((float) $legacyStock))]);

            return [
                'transfer' => $transfer->fresh(['fromWarehouse', 'toWarehouse', 'product']),
                'source_balance' => $source->fresh(),
                'destination_balance' => $destination->fresh(),
            ];
        });
    }

    private function move(Warehouse $warehouse, Product $product, float $delta, float $unitCost, string $movementType, ?string $referenceType, ?string $referenceId, ?string $notes): InventoryBalance
    {
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

        return DB::transaction(function () use ($membership, $warehouse, $product, $delta, $unitCost, $movementType, $referenceType, $referenceId, $notes) {
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
                throw ValidationException::withMessages(['quantity' => "Insufficient stock. Available: {$before}."]);
            }
            $movementCost = $delta < 0
                ? $this->costing->issueUnitCost($balance)
                : $unitCost;

            if ($delta > 0) {
                if ($unitCost <= 0) {
                    throw ValidationException::withMessages(['unit_cost' => 'Unit cost is required for stock receipts and positive adjustments.']);
                }

                $balance->average_cost = $this->costing->receiptAverageCost(
                    currentQuantity: $before,
                    currentAverageCost: (float) $balance->average_cost,
                    receivedQuantity: $delta,
                    receivedUnitCost: $unitCost,
                );
                $balance->last_cost = $unitCost;
            }
            $balance->quantity = $after;
            $balance->available_quantity = $after - (float) $balance->reserved_quantity;
            $balance->save();

            StockMovement::create([
                'tenant_id' => $membership->tenant_id,
                'company_id' => $membership->company_id,
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'movement_type' => $movementType,
                'quantity' => $delta,
                'unit_cost' => $movementCost,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => auth()->id(),
                'request_id' => request()->attributes->get('request_id'),
                'notes' => $notes,
                'created_at' => now(),
            ]);

            $legacyStock = InventoryBalance::query()->where('tenant_id', $membership->tenant_id)->where('product_id', $product->id)->sum('quantity');
            $product->update(['stock' => max(0, (int) round((float) $legacyStock))]);
            return $balance->fresh();
        });
    }
}
