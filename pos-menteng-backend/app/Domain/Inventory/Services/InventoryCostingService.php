<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Models\InventoryBalance;
use Illuminate\Validation\ValidationException;

class InventoryCostingService
{
    public const METHOD_WEIGHTED_AVERAGE = 'weighted_average';

    public function validateUnitCost(float $unitCost): void
    {
        if ($unitCost < 0) {
            throw ValidationException::withMessages([
                'unit_cost' => 'Unit cost cannot be negative.',
            ]);
        }
    }

    public function receiptAverageCost(
        float $currentQuantity,
        float $currentAverageCost,
        float $receivedQuantity,
        float $receivedUnitCost,
    ): float {
        $this->validateUnitCost($receivedUnitCost);

        if ($receivedQuantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Received quantity must be greater than zero.',
            ]);
        }

        $newQuantity = $currentQuantity + $receivedQuantity;

        if ($newQuantity <= 0) {
            return $receivedUnitCost;
        }

        return (($currentQuantity * $currentAverageCost) + ($receivedQuantity * $receivedUnitCost)) / $newQuantity;
    }

    public function issueUnitCost(InventoryBalance $balance): float
    {
        return (float) $balance->average_cost;
    }

    public function transferUnitCost(InventoryBalance $source, float $requestedUnitCost = 0): float
    {
        if ($requestedUnitCost > 0) {
            $this->validateUnitCost($requestedUnitCost);
            return $requestedUnitCost;
        }

        return (float) $source->average_cost;
    }
}
