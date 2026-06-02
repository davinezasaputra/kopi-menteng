<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Membuat Data Kategori
        $kategoriKopi = Category::create(['name' => 'Kopi']);
        $kategoriNonKopi = Category::create(['name' => 'Non-Kopi']);
        $kategoriMakanan = Category::create(['name' => 'Makanan / Snack']);

        // 2. Membuat Data Produk dan Mengaitkannya dengan Kategori
        Product::create([
            'category_id' => $kategoriKopi->id,
            'name' => 'Kopi Susu Gula Aren',
            'description' => 'Espresso dengan susu segar dan gula aren asli',
            'price' => 25000,
            'stock' => 100,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $kategoriKopi->id,
            'name' => 'Americano',
            'description' => 'Espresso dengan air mineral (Hot/Ice)',
            'price' => 20000,
            'stock' => 100,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $kategoriNonKopi->id,
            'name' => 'Matcha Latte',
            'description' => 'Bubuk matcha premium dengan susu segar',
            'price' => 28000,
            'stock' => 50,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $kategoriMakanan->id,
            'name' => 'Kentang Goreng (French Fries)',
            'description' => 'Kentang goreng renyah dengan taburan garam',
            'price' => 18000,
            'stock' => 30,
            'is_active' => true,
        ]);
    }
}
