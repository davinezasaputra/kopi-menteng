<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryReservationHardeningTest extends TestCase
{
    public function test_hardening_contract(): void
    {
        $service = file_get_contents(base_path('app/Domain/Inventory/Services/InventoryReservationService.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/Api/InventoryReservationController.php'));

        $this->assertStringContainsString('DocumentNumberService', $service);
        $this->assertStringContainsString('request_id', $service);
        $this->assertStringContainsString('releaseReservedStock', $service);
        $this->assertStringContainsString("'expired'", $service);
        $this->assertStringContainsString('public function expire', $service);
        $this->assertStringContainsString('public function expire', $controller);
    }
}
