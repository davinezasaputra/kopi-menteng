<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $category = Category::query()->first();

        if (! $category) {
            $category = Category::create([
                'name' => 'Factory Category ' . Str::upper(Str::random(6)),
            ]);
        }

        return [
            'tenant_id' => null,
            'category_id' => $category->getKey(),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(10000, 100000),
            'stock' => 0,
            'image' => null,
            'is_active' => true,
        ];
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn () => [
            'category_id' => $category->getKey(),
        ]);
    }
}
