<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['name' => 'Coffee / Kopi'],
            ['name' => 'Non-Coffee'],
            ['name' => 'Main Course / Makanan Utama'],
            ['name' => 'Snack / Camilan'],
            ['name' => 'Dessert / Makanan Penutup']
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}