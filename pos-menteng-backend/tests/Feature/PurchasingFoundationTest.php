<?php

namespace Tests\Feature;

use Tests\TestCase;

class PurchasingFoundationTest extends TestCase
{
    public function test_purchasing_routes_are_exposed(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutesByName());

        $this->assertTrue(
            app('router')->getRoutes()->contains(
                fn ($route) => $route->uri() === 'api/purchasing/suppliers'
            )
        );
    }
}
