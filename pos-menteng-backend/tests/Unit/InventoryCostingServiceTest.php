<?php

namespace Tests\Unit;

use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Services\InventoryCostingService;
use PHPUnit\Framework\TestCase;

class InventoryCostingServiceTest extends TestCase
{
    private InventoryCostingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InventoryCostingService();
    }

    public function test_weighted_average_receipt_cost_is_calculated_correctly(): void
    {
        $average = $this->service->receiptAverageCost(
            currentQuantity: 10,
            currentAverageCost: 10000,
            receivedQuantity: 10,
            receivedUnitCost: 14000,
        );

        $this->assertSame(12000.0, $average);
    }

    public function test_first_receipt_sets_average_cost_to_receipt_cost(): void
    {
        $average = $this->service->receiptAverageCost(
            currentQuantity: 0,
            currentAverageCost: 0,
            receivedQuantity: 10,
            receivedUnitCost: 14000,
        );

        $this->assertSame(14000.0, $average);
    }

    public function test_issue_uses_current_average_cost(): void
    {
        $balance = new InventoryBalance(['average_cost' => 12500]);

        $this->assertSame(12500.0, $this->service->issueUnitCost($balance));
    }

    public function test_transfer_defaults_to_source_average_cost(): void
    {
        $balance = new InventoryBalance(['average_cost' => 15000]);

        $this->assertSame(15000.0, $this->service->transferUnitCost($balance));
    }
}
