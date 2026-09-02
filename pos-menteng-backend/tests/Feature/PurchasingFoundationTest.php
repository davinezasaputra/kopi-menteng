<?php

namespace Tests\Feature;

use Tests\TestCase;

class PurchasingFoundationTest extends TestCase
{
    public function test_purchasing_routes_are_exposed(): void
    {
        $routes = app('router')->getRoutes()->getRoutes();

        $this->assertTrue(
            collect($routes)->contains(
                fn ($route) => $route->uri() === 'api/purchasing/suppliers'
            )
        );
    }
}
