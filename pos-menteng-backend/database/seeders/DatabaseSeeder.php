<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'davin-eza@mahasiswa.ubb.ac.id'],
            [
                'name' => 'Davin (Developer)',
                'password' => Hash::make(env('SEED_DEVELOPER_PASSWORD', 'change-me-immediately')),
                'role' => 'developer',
                'pin' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir1@menteng.com'],
            [
                'name' => 'Kasir Satu',
                'password' => Hash::make(env('SEED_CASHIER_PASSWORD', 'change-me-immediately')),
                'role' => 'kasir',
                'pin' => env('SEED_CASHIER_PIN'),
            ]
        );

        $this->call([
            ErpMasterSeeder::class,
            EnterprisePermissionSyncSeeder::class,
        ]);
    }
}
