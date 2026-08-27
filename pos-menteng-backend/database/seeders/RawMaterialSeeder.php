<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RawMaterial;

class RawMaterialSeeder extends Seeder
{
    public function run()
    {
        $materials = [
            // --- BAHAN BAR (MINUMAN) ---
            [
                'name' => 'Biji Kopi Gayo (Arabica)',
                'category' => 'bar',
                'unit' => 'gram',
                'stock' => 5000, // 5 kg
            ],
            [
                'name' => 'Biji Kopi Temanggung (Robusta)',
                'category' => 'bar',
                'unit' => 'gram',
                'stock' => 5000,
            ],
            [
                'name' => 'Susu Full Cream UHT',
                'category' => 'bar',
                'unit' => 'ml',
                'stock' => 12000, // 12 Liter
            ],
            [
                'name' => 'Gula Aren Cair',
                'category' => 'bar',
                'unit' => 'ml',
                'stock' => 5000, // 5 Liter
            ],
            [
                'name' => 'Sirup Vanilla',
                'category' => 'bar',
                'unit' => 'ml',
                'stock' => 2000,
            ],
            [
                'name' => 'Paper Filter V60',
                'category' => 'bar',
                'unit' => 'pcs',
                'stock' => 200,
            ],
            [
                'name' => 'Gelas Plastik Cup (Es)',
                'category' => 'bar',
                'unit' => 'pcs',
                'stock' => 500,
            ],

            // --- BAHAN DAPUR (MAKANAN) ---
            [
                'name' => 'Kentang Goreng (Shoestring)',
                'category' => 'dapur',
                'unit' => 'gram',
                'stock' => 10000, // 10 kg
            ],
            [
                'name' => 'Minyak Goreng',
                'category' => 'dapur',
                'unit' => 'ml',
                'stock' => 10000, // 10 Liter
            ],
            [
                'name' => 'Daging Ayam Fillet',
                'category' => 'dapur',
                'unit' => 'gram',
                'stock' => 5000, // 5 kg
            ],
            [
                'name' => 'Saus Sambal',
                'category' => 'dapur',
                'unit' => 'ml',
                'stock' => 2000,
            ],
        ];

        foreach ($materials as $material) {
            // Karena model kita menggunakan UUID, kita cukup create dan ID akan ter-generate otomatis
            RawMaterial::create($material);
        }
    }
}