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
        return [
            'tenant_id' => null,
            'category_id' => null,
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'price' => fake()->numberBetween(10000, 100000),
            'stock' => 0,
            'image' => null,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            if ($product->category_id !== null) {
                return;
            }

            $tenantId = $product->tenant_id;

            $category = Category::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->first();

            if ($category) {
                $product->category_id = $category->getKey();
            }
        })->afterCreating(function (Product $product): void {
            if ($product->category_id !== null) {
                return;
            }

            $category = Category::create([
                'tenant_id' => $product->tenant_id,
                'name' => 'Factory Category ' . Str::upper(Str::random(6)),
            ]);

            $product->forceFill(['category_id' => $category->getKey()])->save();
            $product->refresh();
        });
    }

    public function forCategory(Category $category): static
    {
        return $this->state(fn () => [
            'tenant_id' => $category->tenant_id,
            'category_id' => $category->getKey(),
        ]);
    }
}
