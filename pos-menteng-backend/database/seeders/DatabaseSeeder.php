<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Membuat Akun Anda (Developer)
        User::create([
            'name' => 'Davin (Developer)',
            'email' => 'davin-eza@mahasiswa.ubb.ac.id',
            'password' => Hash::make('@DavinEza1213'), // Password harus di-hash
            'role' => 'developer',
            'pin' => null, // Developer tidak butuh PIN layar sentuh
        ]);

        // 2. Membuat Akun Kasir POS
        User::create([
            'name' => 'Kasir Satu',
            'email' => 'kasir1@menteng.com',
            'password' => Hash::make('kasir123'),
            'role' => 'kasir',
            'pin' => '123456', // PIN 6 digit untuk login cepat di React nanti
        ]);

        $this->call([
            ProductSeeder::class,
        ]);
    }
}