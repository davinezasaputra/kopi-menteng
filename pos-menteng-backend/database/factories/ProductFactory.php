<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'tenant_id' => null,
            // products.category_id is NOT NULL in the legacy schema.
            // Tests can override this value when a tenant-specific category is needed.
            'category_id' => 1,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(10000, 100000),
            'stock' => 0,
            'image' => null,
            'is_active' => true,
        ];
    }
}
