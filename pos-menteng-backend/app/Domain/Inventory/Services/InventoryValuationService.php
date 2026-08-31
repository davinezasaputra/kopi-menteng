<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Support\Tenancy\TenantContext;

class InventoryValuationService
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function summary(?int $warehouseId = null, ?string $productId = null): array
    {
        $query = InventoryBalance::query()
            ->where('tenant_id', $this->context->tenantId())
            ->where('company_id', $this->context->companyId())
            ->where('branch_id', $this->context->branchId())
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($productId, fn ($q) => $q->where('product_id', $productId));

        $rows = $query
            ->with(['warehouse', 'product'])
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get()
            ->map(function (InventoryBalance $balance): array {
                $quantity = (float) $balance->quantity;
                $averageCost = (float) $balance->average_cost;
                $reserved = (float) $balance->reserved_quantity;
                $available = (float) $balance->available_quantity;

                return [
                    'warehouse_id' => $balance->warehouse_id,
                    'warehouse_name' => $balance->warehouse?->name,
                    'product_id' => $balance->product_id,
                    'product_name' => $balance->product?->name,
                    'quantity' => $quantity,
                    'reserved_quantity' => $reserved,
                    'available_quantity' => $available,
                    'average_cost' => $averageCost,
                    'inventory_value' => round($quantity * $averageCost, 4),
                ];
            });

        return [
            'filters' => [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ],
            'items' => $rows->values()->all(),
            'total_quantity' => round($rows->sum('quantity'), 4),
            'total_reserved_quantity' => round($rows->sum('reserved_quantity'), 4),
            'total_available_quantity' => round($rows->sum('available_quantity'), 4),
            'total_inventory_value' => round($rows->sum('inventory_value'), 4),
        ];
    }
}
